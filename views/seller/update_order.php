<?php
/**
 * views/seller/update_order.php
 * รับข้อมูลจาก Edit Modal แล้ว UPDATE ฐานข้อมูล
 */
session_start();
require_once __DIR__ . "/../../config/db.php";

// ตรวจสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/seller/orders.php");
    exit;
}

$seller_id      = $_SESSION['user_id'];
$order_id       = (int)($_POST['order_id'] ?? 0);
$buyer_name     = trim($_POST['buyer_name']      ?? '');
$buyer_tel      = trim($_POST['buyer_tel']       ?? '');
$buyer_address  = trim($_POST['buyer_address']   ?? '');
$tracking       = trim($_POST['tracking_number'] ?? '');
$order_status   = trim($_POST['order_status']    ?? '');

$allowed_status = ['pending', 'confirmed', 'completed'];
if (!$order_id || !in_array($order_status, $allowed_status)) {
    header("Location: /views/seller/orders.php?error=invalid");
    exit;
}

try {
    // ตรวจว่า order นี้เป็นของ seller จริง
    $check = $db->prepare("
        SELECT o.id, o.user_id FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE o.id = :order_id AND a.sellerId = :seller_id
        LIMIT 1
    ");
    $check->execute([':order_id' => $order_id, ':seller_id' => $seller_id]);
    $order = $check->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: /views/seller/orders.php?error=unauthorized");
        exit;
    }

    // UPDATE ข้อมูลผู้เช่า (ตาราง users)
    $user_fields = [];
    $user_params = [':user_id' => $order['user_id']];

    if ($buyer_name !== '') {
        $user_fields[] = "fullname = :fullname";
        $user_params[':fullname'] = $buyer_name;
    }
    if ($buyer_tel !== '') {
        $user_fields[] = "tel = :tel";
        $user_params[':tel'] = $buyer_tel;
    }
    if ($buyer_address !== '') {
        $user_fields[] = "address = :address";
        $user_params[':address'] = $buyer_address;
    }

    if ($user_fields) {
        $sql = "UPDATE users SET " . implode(', ', $user_fields) . " WHERE id = :user_id";
        $db->prepare($sql)->execute($user_params);
    }

    // UPDATE orders (status + tracking_number)
    $shipped_at = null;
    if ($order_status === 'completed') {
        $shipped_at = date('Y-m-d H:i:s');
    }

    $stmt = $db->prepare("
        UPDATE orders
        SET status = :status,
            tracking_number = :tracking,
            shipped_at = COALESCE(:shipped_at, shipped_at)
        WHERE id = :order_id
    ");
    $stmt->execute([
        ':status'     => $order_status,
        ':tracking'   => $tracking ?: null,
        ':shipped_at' => $shipped_at,
        ':order_id'   => $order_id,
    ]);

    header("Location: /views/seller/orders.php?success=updated");
    exit;

} catch (PDOException $e) {
    error_log("update_order error: " . $e->getMessage());
    header("Location: /views/seller/orders.php?error=database");
    exit;
}
