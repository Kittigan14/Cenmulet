<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) { header("Location: /views/user/home.php"); exit; }

try {
    $stmt = $db->prepare("
        SELECT o.*, p.slip_image, p.status as payment_status
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = :order_id AND o.user_id = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { header("Location: /views/user/home.php"); exit; }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function dateTH(string $format, $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    $year_ad = (int) date('Y', $timestamp);   // ดึงปี ค.ศ. เช่น 2026
    $year_be = $year_ad + 543;                // บวก 543 → 2569
    $formatted = date($format, $timestamp);   // แปลงเป็น string ปกติก่อน
    return str_replace($year_ad, $year_be, $formatted); // แทนปีใน string
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/order_success.css">
    <title>สั่งเช่าสำเร็จ - Cenmulet</title>
</head>
<body class="success-page">
    <div class="success-card">

        <div class="success-icon-wrap">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1>สั่งเช่าสำเร็จ!</h1>

        <p class="order-ref">
            หมายเลขคำสั่งเช่า:
            <strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong>
        </p>

        <div class="pending-pill">
            <i class="fa-solid fa-clock"></i>
            รอการตรวจสอบ
        </div>

        <p class="success-msg">
            ขอบคุณที่สั่งเช่ากับเรา เราได้รับคำสั่งเช่าของคุณเรียบร้อยแล้ว<br>
            และกำลังตรวจสอบการชำระเงิน
        </p>

        <div class="success-summary">
            <div class="s-row">
                <span>วันที่สั่งเช่า</span>
                <span><?php echo dateTH('d/m/Y H:i', strtotime($order['created_at'])); ?> น.</span>
            </div>
            <div class="s-row">
                <span>สถานะการชำระเงิน</span>
                <span style="color:var(--warning-dark);">รอการตรวจสอบ</span>
            </div>
            <div class="s-row">
                <span>ยอดรวมทั้งหมด</span>
                <span>฿<?php echo number_format($order['total_price'], 2); ?></span>
            </div>
        </div>

        <div class="success-actions">
            <a href="/views/user/orders.php" class="btn btn-primary">
                <i class="fa-solid fa-receipt"></i>
                ดูคำสั่งเช่าของฉัน
            </a>
            <a href="/views/user/home.php" class="btn btn-secondary">
                <i class="fa-solid fa-home"></i>
                กลับหน้าแรก
            </a>
        </div>

        <div class="info-box success-info-box">
            <h3>
                <i class="fa-solid fa-info-circle"></i>
                ขั้นตอนต่อไป
            </h3>
            <p>
                • เราจะตรวจสอบหลักฐานการโอนเงินของคุณภายใน 24 ชั่วโมง<br>
                • หลังจากได้รับการยืนยันการชำระเงิน เราจะจัดส่งสินค้าให้คุณทันที<br>
                • คุณสามารถตรวจสอบสถานะคำสั่งเช่าได้ที่ "คำสั่งเช่าของฉัน"
            </p>
        </div>

    </div>
</body>
</html>