<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$cart_id = $_POST['cart_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$cart_id || !$action) {
    header("Location: /views/user/cart.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT c.*, a.quantity as stock 
        FROM cart c
        JOIN amulets a ON c.amulet_id = a.id
        WHERE c.id = :cart_id AND c.user_id = :user_id
    ");
    $stmt->execute([
        ':cart_id' => $cart_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cart_item) {
        header("Location: /views/user/cart.php?error=not_found");
        exit;
    }
    
    $new_quantity = $cart_item['quantity'];
    
    if ($action === 'increase') {
        if ($new_quantity < $cart_item['stock']) {
            $new_quantity++;
        } else {
            header("Location: /views/user/cart.php?error=max_stock");
            exit;
        }
    } elseif ($action === 'decrease') {
        if ($new_quantity > 1) {
            $new_quantity--;
        } else {
            $stmt = $db->prepare("DELETE FROM cart WHERE id = :cart_id");
            $stmt->execute([':cart_id' => $cart_id]);
            header("Location: /views/user/cart.php?removed=1");
            exit;
        }
    }
    
    $stmt = $db->prepare("UPDATE cart SET quantity = :quantity WHERE id = :cart_id");
    $stmt->execute([
        ':quantity' => $new_quantity,
        ':cart_id' => $cart_id
    ]);
    
    header("Location: /views/user/cart.php?updated=1");
    exit;
    
} catch (PDOException $e) {
    error_log("Update Cart Error: " . $e->getMessage());
    header("Location: /views/user/cart.php?error=database");
    exit;
}
?>