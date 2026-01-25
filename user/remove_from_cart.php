<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$cart_id = $_POST['cart_id'] ?? null;

if (!$cart_id) {
    header("Location: /views/user/cart.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id");
    $stmt->execute([
        ':cart_id' => $cart_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    header("Location: /views/user/cart.php?removed=1");
    exit;
    
} catch (PDOException $e) {
    error_log("Remove Cart Error: " . $e->getMessage());
    header("Location: /views/user/cart.php?error=database");
    exit;
}
?>