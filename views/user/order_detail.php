<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: /views/user/orders.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("
        SELECT o.*, p.slip_image, p.status as payment_status
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = :order_id AND o.user_id = :user_id
    ");
    $stmt->execute([
        ':order_id' => $order_id,
        ':user_id' => $user_id
    ]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: /views/user/orders.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("
        SELECT oi.*, a.amulet_name, a.image, c.category_name, s.store_name
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers s ON a.sellerId = s.id
        WHERE oi.order_id = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>รายละเอียดคำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?> - Cenmulet</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #1a1a1a;
        }

        .status-badge {
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
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

        .order-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #10b981;
        }

        .order-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .order-item:last-child {
            margin-bottom: 0;
        }

        .item-image {
            width: 100px;
            height: 100px;
            background: #e5e7eb;
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
            font-size: 35px;
            color: #9ca3af;
        }

        .item-info {
            flex: 1;
        }

        .item-category {
            font-size: 12px;
            color: #10b981;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .item-name {
            font-size: 16px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .item-store {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-quantity {
            font-size: 14px;
            color: #6b7280;
        }

        .item-price {
            font-size: 18px;
            color: #10b981;
            font-weight: bold;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 15px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row strong {
            color: #374151;
        }

        .info-row span {
            color: #6b7280;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            border-bottom: 1px solid #f3f4f6;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            margin-top: 15px;
            border-top: 2px solid #e5e7eb;
            font-size: 22px;
            font-weight: bold;
        }

        .summary-total span:last-child {
            color: #10b981;
        }

        .slip-image {
            width: 100%;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s;
        }

        .slip-image:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .status-timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 30px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -29px;
            top: 0;
            width: 2px;
            height: 100%;
            background: #e5e7eb;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-dot {
            position: absolute;
            left: -36px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #e5e7eb;
        }

        .timeline-dot.active {
            background: #10b981;
            box-shadow: 0 0 0 4px #d1fae5;
        }

        .timeline-content h4 {
            font-size: 15px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .timeline-content p {
            font-size: 13px;
            color: #6b7280;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #f3f4f6;
            color: #6b7280;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #e5e7eb;
        }

        .btn-confirm-delivery {
            width: 100%;
            padding: 14px 20px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .btn-confirm-delivery:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .confirm-notice {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #92400e;
            line-height: 1.6;
        }

        .confirm-notice i {
            color: #f59e0b;
            margin-right: 8px;
        }

        @media (max-width: 1024px) {
            .order-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span>/</span>
            <a href="/views/user/orders.php">คำสั่งซื้อของฉัน</a>
            <span>/</span>
            <span>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
        </div>

        <?php if (isset($_GET['delivery_confirmed'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>ยืนยันการรับสินค้าสำเร็จ! ขอบคุณที่ใช้บริการ</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php 
                    switch($_GET['error']) {
                        case 'payment_not_confirmed':
                            echo 'กรุณารอการยืนยันการชำระเงินก่อน';
                            break;
                        case 'already_confirmed':
                            echo 'คำสั่งซื้อนี้ได้รับการยืนยันแล้ว';
                            break;
                        default:
                            echo 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <h1>คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h1>
                <p style="color: #6b7280; margin-top: 8px;">
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?>
                </p>
            </div>
            <?php
            $status_class = 'status-pending';
            $status_text = 'รอการตรวจสอบ';
            $status_icon = 'fa-clock';
            
            if ($order['payment_status'] === 'confirmed') {
                $status_class = 'status-confirmed';
                $status_text = 'ยืนยันการชำระเงิน';
                $status_icon = 'fa-check-circle';
            }
            if ($order['status'] === 'completed') {
                $status_class = 'status-completed';
                $status_text = 'จัดส่งสำเร็จ';
                $status_icon = 'fa-check-double';
            } elseif ($order['status'] === 'cancelled') {
                $status_class = 'status-cancelled';
                $status_text = 'ยกเลิก';
                $status_icon = 'fa-times-circle';
            }
            ?>
            <div class="status-badge <?php echo $status_class; ?>">
                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                <?php echo $status_text; ?>
            </div>
        </div>

        <div class="order-layout">
            <div>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-box"></i>
                        รายการสินค้า
                    </h2>

                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <?php if ($item['image']): ?>
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fa-solid fa-image"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-category"><?php echo htmlspecialchars($item['category_name'] ?? 'ไม่ระบุ'); ?></div>
                                <div class="item-name"><?php echo htmlspecialchars($item['amulet_name']); ?></div>
                                <div class="item-store">จาก: <?php echo htmlspecialchars($item['store_name'] ?? 'ร้านค้า'); ?></div>
                                <div class="item-details">
                                    <span class="item-quantity">จำนวน: <?php echo $item['quantity']; ?> ชิ้น</span>
                                    <span class="item-price">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-list-check"></i>
                        สถานะการสั่งซื้อ
                    </h2>

                    <div class="status-timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot active"></div>
                            <div class="timeline-content">
                                <h4>สั่งซื้อสำเร็จ</h4>
                                <p><?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot <?php echo ($order['payment_status'] === 'confirmed' || $order['status'] === 'completed') ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h4>ยืนยันการชำระเงิน</h4>
                                <p>
                                    <?php 
                                    if ($order['payment_status'] === 'confirmed') {
                                        echo 'ยืนยันแล้ว';
                                    } else {
                                        echo 'รอการตรวจสอบ';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="timeline-item">
                            <div class="timeline-dot <?php echo $order['status'] === 'completed' ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h4>จัดส่งสินค้า</h4>
                                <p>
                                    <?php 
                                    if ($order['status'] === 'completed') {
                                        echo 'จัดส่งสำเร็จ';
                                    } else {
                                        echo 'รอดำเนินการ';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-file-invoice"></i>
                        สรุปคำสั่งซื้อ
                    </h2>

                    <div class="summary-row">
                        <span>จำนวนสินค้า</span>
                        <span><?php echo count($order_items); ?> รายการ</span>
                    </div>

                    <div class="summary-row">
                        <span>ค่าจัดส่ง</span>
                        <span style="color: #10b981;">ฟรี</span>
                    </div>

                    <div class="summary-total">
                        <span>ยอดรวมทั้งหมด</span>
                        <span>฿<?php echo number_format($order['total_price'], 2); ?></span>
                    </div>
                </div>

                <?php if ($order['payment_status'] === 'confirmed' && $order['status'] !== 'completed'): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-truck"></i>
                        ยืนยันการรับสินค้า
                    </h2>

                    <div class="confirm-notice">
                        <i class="fa-solid fa-info-circle"></i>
                        กรุณายืนยันเมื่อได้รับสินค้าเรียบร้อยแล้ว<br>
                        เมื่อยืนยันแล้วจะไม่สามารถยกเลิกได้
                    </div>

                    <form action="/user/confirm_delivery.php" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าได้รับสินค้าเรียบร้อยแล้ว?')">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="btn-confirm-delivery">
                            <i class="fa-solid fa-check-circle"></i>
                            ยืนยันการรับสินค้า
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($order['slip_image']): ?>
                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-receipt"></i>
                        หลักฐานการโอนเงิน
                    </h2>
                    <img src="/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>" 
                         alt="Slip" 
                         class="slip-image"
                         onclick="window.open(this.src, '_blank')">
                </div>
                <?php endif; ?>

                <div class="section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-info-circle"></i>
                        ข้อมูลเพิ่มเติม
                    </h2>

                    <div class="info-row">
                        <strong>สถานะการชำระเงิน</strong>
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

                    <div class="info-row">
                        <strong>สถานะการจัดส่ง</strong>
                        <span>
                            <?php 
                            if ($order['status'] === 'pending') {
                                echo 'รอดำเนินการ';
                            } elseif ($order['status'] === 'completed') {
                                echo 'จัดส่งสำเร็จ';
                            } elseif ($order['status'] === 'cancelled') {
                                echo 'ยกเลิก';
                            } else {
                                echo ucfirst($order['status']);
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <a href="/views/user/orders.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i>
                    กลับไปหน้าคำสั่งซื้อ
                </a>
            </div>
        </div>
    </div>
</body>
</html>