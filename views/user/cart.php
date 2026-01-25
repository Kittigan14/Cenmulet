<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// ดึงสินค้าในตะกร้า
try {
    $stmt = $db->prepare("
        SELECT c.*, a.amulet_name, a.price, a.image, a.quantity as stock, 
               s.store_name, cat.category_name
        FROM cart c
        JOIN amulets a ON c.amulet_id = a.id
        LEFT JOIN sellers s ON a.sellerId = s.id
        LEFT JOIN categories cat ON a.categoryId = cat.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// คำนวณยอดรวม
$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

// นับจำนวนสินค้าในตะกร้า
$cart_count = count($cart_items);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>ตะกร้าสินค้า - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&family=Sriracha&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Kanit", sans-serif;
            background: #f9fafb;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 16px;
            color: #6b7280;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .cart-item {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 25px;
            align-items: center;
        }

        .item-image {
            width: 120px;
            height: 120px;
            background: #f3f4f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image i {
            font-size: 40px;
            color: #d1d5db;
        }

        .item-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item-category {
            font-size: 12px;
            color: #10b981;
            font-weight: 600;
            text-transform: uppercase;
        }

        .item-name {
            font-size: 18px;
            color: #1a1a1a;
            font-weight: 600;
        }

        .item-store {
            font-size: 13px;
            color: #6b7280;
        }

        .item-price {
            font-size: 20px;
            color: #10b981;
            font-weight: bold;
            margin-top: 5px;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: flex-end;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .qty-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            border-color: #10b981;
            color: #10b981;
        }

        .qty-input {
            width: 60px;
            height: 35px;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-remove {
            padding: 8px 20px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-remove:hover {
            background: #fecaca;
        }

        .cart-summary {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            height: fit-content;
            position: sticky;
            top: 120px;
        }

        .summary-title {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .summary-row span:first-child {
            color: #6b7280;
        }

        .summary-row span:last-child {
            color: #1a1a1a;
            font-weight: 600;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid #f3f4f6;
            font-size: 20px;
            font-weight: bold;
        }

        .summary-total span:first-child {
            color: #1a1a1a;
        }

        .summary-total span:last-child {
            color: #10b981;
        }

        .btn-checkout {
            width: 100%;
            padding: 16px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-checkout:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-continue {
            width: 100%;
            padding: 12px;
            background: #f3f4f6;
            color: #6b7280;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-continue:hover {
            background: #e5e7eb;
        }

        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .empty-cart i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .empty-cart p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
        }

        .empty-cart a {
            padding: 12px 30px;
            background: #10b981;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            

            i {
                font-size: 24px;
                margin: 0 10px 0 0;
            }
        }

        .empty-cart a:hover {
            background: #059669;
        }

        @media (max-width: 1024px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: relative;
                top: 0;
            }
        }

        @media (max-width: 768px) {
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .item-actions {
                align-items: center;
            }

            .item-image {
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>ตะกร้าสินค้า</h1>
            <p>ตรวจสอบสินค้าก่อนสั่งซื้อ</p>
        </div>

        <?php if (count($cart_items) > 0): ?>
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fa-solid fa-image"></i>
                                <?php endif; ?>
                            </div>

                            <div class="item-info">
                                <div class="item-category">
                                    <?php echo htmlspecialchars($item['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?>
                                </div>
                                <div class="item-name"><?php echo htmlspecialchars($item['amulet_name']); ?></div>
                                <div class="item-store">จาก: <?php echo htmlspecialchars($item['store_name'] ?? 'ร้านค้า'); ?></div>
                                <div class="item-price">฿<?php echo number_format($item['price'], 2); ?></div>
                            </div>

                            <div class="item-actions">
                                <form action="/user/update_cart.php" method="POST" class="quantity-control">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="action" value="decrease" class="qty-btn">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <input type="number" class="qty-input" value="<?php echo $item['quantity']; ?>" readonly>
                                    <button type="submit" name="action" value="increase" class="qty-btn" 
                                            <?php echo ($item['quantity'] >= $item['stock']) ? 'disabled' : ''; ?>>
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </form>

                                <form action="/user/remove_from_cart.php" method="POST">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn-remove">
                                        <i class="fa-solid fa-trash"></i> ลบ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">สรุปคำสั่งซื้อ</h2>
                    
                    <div class="summary-row">
                        <span>จำนวนสินค้า</span>
                        <span><?php echo $cart_count; ?> รายการ</span>
                    </div>

                    <div class="summary-row">
                        <span>ราคารวม</span>
                        <span>฿<?php echo number_format($total_price, 2); ?></span>
                    </div>

                    <div class="summary-total">
                        <span>ยอดรวมทั้งหมด</span>
                        <span>฿<?php echo number_format($total_price, 2); ?></span>
                    </div>

                    <form action="/user/checkout.php" method="POST">
                        <button type="submit" class="btn-checkout">
                            <i class="fa-solid fa-credit-card"></i>
                            ดำเนินการสั่งซื้อ
                        </button>
                    </form>

                    <a href="/views/user/home.php" class="btn-continue">
                        <i class="fa-solid fa-arrow-left"></i>
                        เลือกสินค้าต่อ
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fa-solid fa-cart-shopping"></i>
                <h2>ตะกร้าสินค้าว่างเปล่า</h2>
                <p>คุณยังไม่มีสินค้าในตะกร้า</p>
                <a href="/views/user/home.php">
                    <i class="fa-solid fa-shopping-bag"></i>
                    เริ่มเลือกซื้อสินค้า
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>