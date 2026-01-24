<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

// ตรวจสอบว่า login และเป็น user หรือไม่
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

// ดึงสินค้าทั้งหมด
try {
    $stmt = $db->query("
        SELECT a.*, c.category_name, s.store_name 
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers s ON a.sellerId = s.id
        WHERE a.quantity > 0
        ORDER BY a.id DESC
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// นับจำนวนสินค้าในตะกร้า
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $cart_count = 0;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>หน้าแรก - Cenmulet</title>
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

        .navbar {
            width: 100%;
            height: 100px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-left ul {
            list-style: none;
            display: flex;
            gap: 25px;
        }

        .nav-left a {
            text-decoration: none;
            color: #1a1a1a;
            font-size: 15px;
            transition: color 0.3s;
        }

        .nav-left a:hover {
            color: #10b981;
        }

        .logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }

        .logo h2 {
            font-family: "Sriracha", cursive;
            font-size: 28px;
            color: #444547;
            margin-bottom: 5px;
        }

        .logo p {
            font-size: 12px;
            color: #6b7280;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f3f4f6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-button:hover {
            background: #e5e7eb;
        }

        .user-button i {
            font-size: 18px;
            color: #1a1a1a;
        }

        .user-button span {
            font-size: 14px;
            color: #1a1a1a;
        }

        .cart-icon {
            position: relative;
            font-size: 22px;
            color: #1a1a1a;
            cursor: pointer;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
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

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            width: 100%;
            height: 250px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            font-size: 48px;
            color: #d1d5db;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            font-size: 12px;
            color: #10b981;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .product-source {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 15px;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-price {
            font-size: 24px;
            color: #10b981;
            font-weight: bold;
        }

        .btn-add-cart {
            padding: 10px 20px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-cart:hover {
            background: #059669;
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 16px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-left">
            <ul>
                <li><a href="/views/user/home.php">หน้าแรก</a></li>
                <li><a href="/views/user/orders.php">คำสั่งซื้อของฉัน</a></li>
            </ul>
        </div>

        <div class="logo">
            <h2>Cenmulet</h2>
            <p>ตลาดพระเครื่อง</p>
        </div>

        <div class="nav-right">
            <a href="/views/user/cart.php">
                <div class="cart-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </div>
            </a>

            <div class="user-menu">
                <div class="user-button">
                    <i class="fa-solid fa-user"></i>
                    <span><?php echo htmlspecialchars($user['fullname']); ?></span>
                </div>
            </div>

            <a href="/auth/logout.php" style="text-decoration: none;">
                <div class="user-button">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </div>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>พระเครื่องทั้งหมด</h1>
            <p>เลือกเช่าพระเครื่องที่คุณต้องการ</p>
        </div>

        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['image']): ?>
                                <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="">
                            <?php else: ?>
                                <i class="fa-solid fa-image"></i>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <div class="product-category">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?>
                            </div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['amulet_name']); ?></h3>
                            <p class="product-source">จาก: <?php echo htmlspecialchars($product['store_name'] ?? 'ร้านค้า'); ?></p>

                            <div class="product-footer">
                                <div class="product-price">
                                    ฿<?php echo number_format($product['price'], 2); ?>
                                </div>
                                <form action="/views/user/add_to_cart.php" method="POST">
                                    <input type="hidden" name="amulet_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-plus"></i>
                                        เพิ่ม
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <h2>ยังไม่มีสินค้า</h2>
                <p>กรุณารอผู้ขายเพิ่มสินค้า</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>