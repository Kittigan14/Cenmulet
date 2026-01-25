<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$order_id = $_POST['order_id'] ?? null;

if (!$order_id) {
    header("Location: /views/admin/orders.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = 'confirmed' 
        WHERE order_id = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
    
    header("Location: /views/admin/orders.php?success=approved");
    exit;
    
} catch (PDOException $e) {
    error_log("Approve Payment Error: " . $e->getMessage());
    header("Location: /views/admin/orders.php?error=database");
    exit;
}
?>