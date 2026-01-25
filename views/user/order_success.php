<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: /views/user/home.php");
    exit;
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
        ':user_id' => $_SESSION['user_id']
    ]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: /views/user/home.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สั่งซื้อสำเร็จ - Cenmulet</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .success-container {
            max-width: 600px;
            width: 100%;
            background: #fff;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            font-size: 50px;
            color: #10b981;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 15px;
        }

        .order-number {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .order-number strong {
            color: #10b981;
            font-size: 20px;
        }

        .message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .order-summary {
            background: #f9fafb;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid #e5e7eb;
            font-size: 20px;
            font-weight: bold;
        }

        .summary-row span:first-child {
            color: #6b7280;
        }

        .summary-row span:last-child {
            color: #1a1a1a;
            font-weight: 600;
        }

        .summary-row:last-child span:last-child {
            color: #10b981;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #fef3c7;
            color: #d97706;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #10b981;
            color: #fff;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .info-box h3 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box p {
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1>สั่งซื้อสำเร็จ!</h1>
        
        <p class="order-number">
            หมายเลขคำสั่งซื้อ: <strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong>
        </p>

        <div class="status-badge">
            <i class="fa-solid fa-clock"></i>
            รอการตรวจสอบ
        </div>

        <p class="message">
            ขอบคุณที่สั่งซื้อกับเรา เราได้รับคำสั่งซื้อของคุณเรียบร้อยแล้ว<br>
            และกำลังตรวจสอบการชำระเงิน
        </p>

        <div class="order-summary">
            <div class="summary-row">
                <span>วันที่สั่งซื้อ</span>
                <span><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?> น.</span>
            </div>
            <div class="summary-row">
                <span>สถานะการชำระเงิน</span>
                <span style="color: #d97706;">รอการตรวจสอบ</span>
            </div>
            <div class="summary-row">
                <span>ยอดรวมทั้งหมด</span>
                <span>฿<?php echo number_format($order['total_price'], 2); ?></span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="/views/user/orders.php" class="btn btn-primary">
                <i class="fa-solid fa-receipt"></i>
                ดูคำสั่งซื้อของฉัน
            </a>
            <a href="/views/user/home.php" class="btn btn-secondary">
                <i class="fa-solid fa-home"></i>
                กลับหน้าแรก
            </a>
        </div>

        <div class="info-box">
            <h3>
                <i class="fa-solid fa-info-circle"></i>
                ขั้นตอนต่อไป
            </h3>
            <p>
                • เราจะตรวจสอบหลักฐานการโอนเงินของคุณภายใน 24 ชั่วโมง<br>
                • หลังจากได้รับการยืนยันการชำระเงิน เราจะจัดส่งสินค้าให้คุณทันที<br>
                • คุณสามารถตรวจสอบสถานะคำสั่งซื้อได้ที่ "คำสั่งซื้อของฉัน"
            </p>
        </div>
    </div>
</body>
</html>