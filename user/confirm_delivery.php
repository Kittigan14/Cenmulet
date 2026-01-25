<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_POST['order_id'] ?? null;

if (!$order_id) {
    header("Location: /views/user/orders.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT o.*, p.status as payment_status
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
        header("Location: /views/user/orders.php?error=not_found");
        exit;
    }
    
    if ($order['payment_status'] !== 'confirmed') {
        header("Location: /views/user/order_detail.php?id=$order_id&error=payment_not_confirmed");
        exit;
    }
    
    if ($order['status'] === 'completed') {
        header("Location: /views/user/order_detail.php?id=$order_id&error=already_confirmed");
        exit;
    }
    
    $stmt = $db->prepare("UPDATE orders SET status = 'completed' WHERE id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    
    header("Location: /views/user/order_detail.php?id=$order_id&delivery_confirmed=1");
    exit;
    
} catch (PDOException $e) {
    error_log("Confirm Delivery Error: " . $e->getMessage());
    header("Location: /views/user/order_detail.php?id=$order_id&error=database");
    exit;
}
?>