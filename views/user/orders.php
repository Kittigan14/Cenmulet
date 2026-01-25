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
    <title>คำสั่งซื้อของฉัน - Cenmulet</title>
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

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 20px;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .order-number {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
        }

        .order-date {
            font-size: 14px;
            color: #6b7280;
        }

        .order-date i {
            margin-right: 5px;
        }

        .status-badge {
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-confirmed {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-completed {
            background: #d1fae5;
            color: #059669;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .order-body {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 30px;
            align-items: center;
        }

        .order-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
        }

        .detail-row i {
            width: 20px;
            color: #10b981;
        }

        .detail-row strong {
            color: #1a1a1a;
            min-width: 120px;
        }

        .detail-row span {
            color: #6b7280;
        }

        .order-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: flex-end;
        }

        .order-total {
            font-size: 28px;
            color: #10b981;
            font-weight: bold;
        }

        .btn-view {
            padding: 12px 25px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-view:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border-radius: 15px;
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
            margin-bottom: 30px;
        }

        .empty-state a {
            display: inline-block;
            padding: 12px 30px;
            background: #10b981;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .empty-state a:hover {
            background: #059669;
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .order-body {
                grid-template-columns: 1fr;
            }

            .order-actions {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>คำสั่งซื้อของฉัน</h1>
            <p>ตรวจสอบสถานะและรายละเอียดคำสั่งซื้อ</p>
        </div>

        <?php if (count($orders) > 0): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status_class = 'status-pending';
                    $status_text = 'รอการตรวจสอบ';
                    $status_icon = 'fa-clock';
                    
                    if ($order['payment_status'] === 'confirmed') {
                        $status_class = 'status-confirmed';
                        $status_text = 'ยืนยันการชำระเงิน';
                        $status_icon = 'fa-check-circle';
                    } elseif ($order['status'] === 'completed') {
                        $status_class = 'status-completed';
                        $status_text = 'จัดส่งสำเร็จ';
                        $status_icon = 'fa-check-double';
                    } elseif ($order['status'] === 'cancelled') {
                        $status_class = 'status-cancelled';
                        $status_text = 'ยกเลิก';
                        $status_icon = 'fa-times-circle';
                    }
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">
                                    <i class="fa-solid fa-receipt"></i>
                                    คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="order-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            <div class="status-badge <?php echo $status_class; ?>">
                                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </div>
                        </div>

                        <div class="order-body">
                            <div class="order-details">
                                <div class="detail-row">
                                    <i class="fa-solid fa-box"></i>
                                    <strong>จำนวนสินค้า:</strong>
                                    <span><?php echo $order['item_count']; ?> รายการ</span>
                                </div>
                                <div class="detail-row">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <strong>สถานะการชำระเงิน:</strong>
                                    <span>
                                        <?php 
                                        if ($order['payment_status'] === 'waiting') {
                                            echo 'รอการตรวจสอบ';
                                        } elseif ($order['payment_status'] === 'confirmed') {
                                            echo 'ยืนยันแล้ว';
                                        } else {
                                            echo 'ไม่ทราบสถานะ';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <i class="fa-solid fa-truck"></i>
                                    <strong>สถานะการจัดส่ง:</strong>
                                    <span>
                                        <?php 
                                        if ($order['status'] === 'pending') {
                                            echo 'รอดำเนินการ';
                                        } elseif ($order['status'] === 'completed') {
                                            echo 'จัดส่งสำเร็จ';
                                        } else {
                                            echo ucfirst($order['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="order-actions">
                                <div class="order-total">
                                    ฿<?php echo number_format($order['total_price'], 2); ?>
                                </div>
                                <a href="/views/user/order_detail.php?id=<?php echo $order['id']; ?>" class="btn-view">
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
                <i class="fa-solid fa-receipt"></i>
                <h2>ยังไม่มีคำสั่งซื้อ</h2>
                <p>คุณยังไม่เคยสั่งซื้อสินค้า</p>
                <a href="/views/user/home.php">
                    <i class="fa-solid fa-shopping-bag"></i>
                    เริ่มเลือกซื้อสินค้า
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>