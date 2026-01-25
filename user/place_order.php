<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$fullname = $_POST['fullname'] ?? '';
$tel = $_POST['tel'] ?? '';
$address = $_POST['address'] ?? '';

if (!$fullname || !$tel || !$address) {
    header("Location: /user/checkout.php?error=missing_info");
    exit;
}

$slip_image = null;
if (isset($_FILES['slip']) && $_FILES['slip']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg', 'jpeg', 'png'];
    $filename = $_FILES['slip']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        header("Location: /user/checkout.php?error=invalid_file");
        exit;
    }
    
    if ($_FILES['slip']['size'] > 5242880) {
        header("Location: /user/checkout.php?error=file_too_large");
        exit;
    }
    
    $slip_image = uniqid() . '_' . time() . '.' . $ext;
    $upload_path = __DIR__ . "/../uploads/slips/";
    
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0777, true);
    }
    
    if (!move_uploaded_file($_FILES['slip']['tmp_name'], $upload_path . $slip_image)) {
        header("Location: /user/checkout.php?error=upload_failed");
        exit;
    }
} else {
    header("Location: /user/checkout.php?error=no_slip");
    exit;
}

try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        SELECT c.*, a.price, a.quantity as stock, a.amulet_name
        FROM cart c
        JOIN amulets a ON c.amulet_id = a.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cart_items) === 0) {
        $db->rollBack();
        header("Location: /views/user/cart.php?error=empty_cart");
        exit;
    }
    
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $db->rollBack();
            header("Location: /user/checkout.php?error=insufficient_stock");
            exit;
        }
    }
    
    $total_price = 0;
    foreach ($cart_items as $item) {
        $total_price += $item['price'] * $item['quantity'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO orders (user_id, total_price, status, created_at)
        VALUES (:user_id, :total_price, 'pending', datetime('now'))
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':total_price' => $total_price
    ]);
    
    $order_id = $db->lastInsertId();
    
    foreach ($cart_items as $item) {
        $stmt = $db->prepare("
            INSERT INTO order_items (order_id, amulet_id, price, quantity)
            VALUES (:order_id, :amulet_id, :price, :quantity)
        ");
        $stmt->execute([
            ':order_id' => $order_id,
            ':amulet_id' => $item['amulet_id'],
            ':price' => $item['price'],
            ':quantity' => $item['quantity']
        ]);
        
        $stmt = $db->prepare("
            UPDATE amulets 
            SET quantity = quantity - :quantity
            WHERE id = :amulet_id
        ");
        $stmt->execute([
            ':quantity' => $item['quantity'],
            ':amulet_id' => $item['amulet_id']
        ]);
    }
    
    $stmt = $db->prepare("
        INSERT INTO payments (order_id, slip_image, status, created_at)
        VALUES (:order_id, :slip_image, 'waiting', datetime('now'))
    ");
    $stmt->execute([
        ':order_id' => $order_id,
        ':slip_image' => $slip_image
    ]);
    
    $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    
    $db->commit();
    
    header("Location: /views/user/order_success.php?order_id=$order_id");
    exit;
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Place Order Error: " . $e->getMessage());
    
    if ($slip_image && file_exists(__DIR__ . "/../uploads/slips/" . $slip_image)) {
        unlink(__DIR__ . "/../uploads/slips/" . $slip_image);
    }
    
    header("Location: /user/checkout.php?error=database");
    exit;
}
?>