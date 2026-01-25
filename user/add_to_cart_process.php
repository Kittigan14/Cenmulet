<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$amulet_id = $_POST['amulet_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;

if (!$amulet_id || $quantity < 1) {
    header("Location: /views/user/home.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM amulets WHERE id = :id");
    $stmt->execute([':id' => $amulet_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: /views/user/home.php?error=not_found");
        exit;
    }
    
    if ($product['quantity'] < $quantity) {
        header("Location: /views/user/product_detail.php?id=$amulet_id&error=stock");
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM cart WHERE user_id = :user_id AND amulet_id = :amulet_id");
    $stmt->execute([
        ':user_id' => $user_id,
        ':amulet_id' => $amulet_id
    ]);
    $existing_cart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_cart) {
        $new_quantity = $existing_cart['quantity'] + $quantity;
        
        if ($new_quantity > $product['quantity']) {
            header("Location: /views/user/product_detail.php?id=$amulet_id&error=stock_exceed");
            exit;
        }
        
        $stmt = $db->prepare("UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND amulet_id = :amulet_id");
        $stmt->execute([
            ':quantity' => $new_quantity,
            ':user_id' => $user_id,
            ':amulet_id' => $amulet_id
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO cart (user_id, amulet_id, quantity) VALUES (:user_id, :amulet_id, :quantity)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':amulet_id' => $amulet_id,
            ':quantity' => $quantity
        ]);
    }
    
    header("Location: /views/user/product_detail.php?id=$amulet_id&added=1");
    exit;
    
} catch (PDOException $e) {
    error_log("Add to Cart Error: " . $e->getMessage());
    
    header("Location: /views/user/product_detail.php?id=$amulet_id&error=database");
    exit;
}
?>