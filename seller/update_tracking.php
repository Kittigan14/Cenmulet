<?php
/**
 * seller/update_tracking.php
 * Seller กรอกเลขพัสดุและส่งให้ผู้ซื้อ
 */
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/seller/orders.php"); exit;
}

$seller_id      = $_SESSION['user_id'];
$order_id       = (int)($_POST['order_id']       ?? 0);
$tracking_number = trim($_POST['tracking_number'] ?? '');

if (!$order_id || $tracking_number === '') {
    header("Location: /views/seller/orders.php?error=missing_tracking"); exit;
}

try {
    // ตรวจสอบว่า order นี้มีสินค้าของ seller จริง
    $check = $db->prepare("
        SELECT COUNT(*) FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE o.id = :oid AND a.sellerId = :sid
    ");
    $check->execute([':oid' => $order_id, ':sid' => $seller_id]);
    if ($check->fetchColumn() == 0) {
        header("Location: /views/seller/orders.php?error=unauthorized"); exit;
    }

    // บันทึกเลขพัสดุ + เวลาที่ส่ง
    $stmt = $db->prepare("
        UPDATE orders
        SET tracking_number = :tracking,
            shipped_at = datetime('now')
        WHERE id = :oid
    ");
    $stmt->execute([':tracking' => $tracking_number, ':oid' => $order_id]);

    header("Location: /views/seller/orders.php?done=tracking&order=" . $order_id);

} catch (PDOException $e) {
    error_log("Update Tracking Error: " . $e->getMessage());
    header("Location: /views/seller/orders.php?error=database");
}
exit;