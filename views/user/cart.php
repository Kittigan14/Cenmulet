<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
 
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

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
$cart_count = count($cart_items);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/cart.css">
    <title>ตะกร้าพระเครื่อง - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fa-solid fa-cart-shopping" style="color:var(--primary);"></i>ตะกร้าพระเครื่อง</h1>
            <p>ตรวจสอบพระเครื่องก่อนดำเนินการสั่งเช่า</p>
        </div>

        <?php if (count($cart_items) > 0): ?>
            <div class="cart-layout">

                <!-- Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-thumb">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fa-solid fa-image"></i>
                                <?php endif; ?>
                            </div>

                            <div class="item-info">
                                <div class="item-cat"><?php echo htmlspecialchars($item['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?></div>
                                <div class="item-name"><?php echo htmlspecialchars($item['amulet_name']); ?></div>
                                <div class="item-store">
                                    <i class="fa-solid fa-store" style="font-size:11px;"></i>
                                    <?php echo htmlspecialchars($item['store_name'] ?? 'ร้านค้า'); ?>
                                </div>
                                <div class="item-price">฿<?php echo number_format($item['price'], 2); ?></div>
                            </div>

                            <div class="item-controls">
                                <form action="/user/update_cart.php" method="POST">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <div class="qty-row">
                                        <button type="submit" name="action" value="decrease" class="qty-btn">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <span class="qty-value"><?php echo $item['quantity']; ?></span>
                                        <button type="submit" name="action" value="increase" class="qty-btn"
                                                <?php echo ($item['quantity'] >= $item['stock']) ? 'disabled' : ''; ?>>
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
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

                <!-- Summary -->
                <div class="cart-summary">
                    <h2 class="summary-heading">
                        <i class="fa-solid fa-file-invoice"></i>
                        สรุปรายการเช่า
                    </h2>

                    <div class="summary-line">
                        <span class="label">จำนวน</span>
                        <span class="value"><?php echo $cart_count; ?> รายการ</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">ค่าจัดส่ง</span>
                        <span class="value free">ฟรี</span>
                    </div>
                    <div class="summary-line">
                        <span class="label">ราคารวม</span>
                        <span class="value">฿<?php echo number_format($total_price, 2); ?></span>
                    </div>

                    <div class="summary-total">
                        <span class="total-label">ยอดรวมทั้งหมด</span>
                        <span class="total-amount">฿<?php echo number_format($total_price, 2); ?></span>
                    </div>

                    <form action="/user/checkout.php" method="POST">
                        <button type="submit" class="btn-checkout">
                            <i class="fa-solid fa-credit-card"></i>
                            ดำเนินการสั่งเช่า
                        </button>
                    </form>

                    <a href="/views/user/home.php" class="btn-continue-shop">
                        <i class="fa-solid fa-arrow-left"></i>
                        เลือกพระต่อ
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-cart">
                <div class="big-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <h2>ตะกร้าพระเครื่องว่างเปล่า</h2>
                <p>คุณยังไม่มีพระเครื่องในตะกร้า</p>
                <a href="/views/user/home.php" class="btn btn-primary">
                    <i class="fa-solid fa-shopping-bag"></i>
                    เริ่มเลือกเช่าพระ
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>