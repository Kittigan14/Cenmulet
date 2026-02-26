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
        SELECT o.*, p.slip_image, p.status as payment_status,
               COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = :user_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/orders.css">
    <title>คำสั่งซื้อของฉัน - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>คำสั่งซื้อของฉัน</h1>
            <p>ตรวจสอบสถานะและรายละเอียดคำสั่งซื้อทั้งหมด</p>
        </div>

        <?php if (count($orders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order):
                    $status_class = 'status-pending';
                    $status_text  = 'รอการตรวจสอบ';
                    $status_icon  = 'fa-clock';

                    if ($order['payment_status'] === 'confirmed') {
                        $status_class = 'status-confirmed';
                        $status_text  = 'ยืนยันการชำระเงิน';
                        $status_icon  = 'fa-check-circle';
                    }
                    if ($order['status'] === 'completed') {
                        $status_class = 'status-completed';
                        $status_text  = 'จัดส่งสำเร็จ';
                        $status_icon  = 'fa-check-double';
                    } elseif ($order['status'] === 'cancelled') {
                        $status_class = 'status-cancelled';
                        $status_text  = 'ยกเลิก';
                        $status_icon  = 'fa-times-circle';
                    }

                    if ($order['payment_status'] === 'waiting') {
                        $payment_label = 'รอการตรวจสอบ';
                    } elseif ($order['payment_status'] === 'confirmed') {
                        $payment_label = 'ยืนยันแล้ว';
                    } else {
                        $payment_label = 'ไม่ทราบสถานะ';
                    }

                    if ($order['status'] === 'pending') {
                        $shipping_label = 'รอดำเนินการ';
                    } elseif ($order['status'] === 'completed') {
                        $shipping_label = 'จัดส่งสำเร็จ';
                    } elseif ($order['status'] === 'cancelled') {
                        $shipping_label = 'ยกเลิก';
                    } else {
                        $shipping_label = ucfirst($order['status']);
                    }
                ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div class="order-meta">
                                <div class="order-num">
                                    <i class="fa-solid fa-receipt"></i>
                                    คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="order-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </div>

                        <div class="order-card-body">
                            <div class="order-details">
                                <div class="detail-row">
                                    <div class="d-icon"><i class="fa-solid fa-box"></i></div>
                                    <span class="d-label">จำนวนสินค้า</span>
                                    <span class="d-value"><?php echo $order['item_count']; ?> รายการ</span>
                                </div>
                                <div class="detail-row">
                                    <div class="d-icon"><i class="fa-solid fa-credit-card"></i></div>
                                    <span class="d-label">สถานะการชำระเงิน</span>
                                    <span class="d-value"><?php echo $payment_label; ?></span>
                                </div>
                                <div class="detail-row">
                                    <div class="d-icon"><i class="fa-solid fa-truck"></i></div>
                                    <span class="d-label">สถานะการจัดส่ง</span>
                                    <span class="d-value"><?php echo $shipping_label; ?></span>
                                </div>
                            </div>

                            <div class="order-card-actions">
                                <div class="order-total-price">
                                    ฿<?php echo number_format($order['total_price'], 2); ?>
                                </div>
                                <a href="/views/user/order_detail.php?id=<?php echo $order['id']; ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-eye"></i>
                                    ดูรายละเอียด
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><i class="fa-solid fa-receipt"></i></div>
                <h2>ยังไม่มีคำสั่งซื้อ</h2>
                <p>คุณยังไม่เคยสั่งซื้อสินค้า</p>
                <a href="/views/user/home.php" class="btn btn-primary">
                    <i class="fa-solid fa-shopping-bag"></i>
                    เริ่มเลือกซื้อสินค้า
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>