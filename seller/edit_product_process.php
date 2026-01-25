<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

$product_id = $_POST['product_id'] ?? null;
$amulet_name = $_POST['amulet_name'] ?? '';
$source = $_POST['source'] ?? '';
$quantity = $_POST['quantity'] ?? 0;
$price = $_POST['price'] ?? 0;
$categoryId = $_POST['categoryId'] ?? null;
$old_image = $_POST['old_image'] ?? null;

if (!$product_id || empty($amulet_name) || empty($source) || $quantity < 0 || $price < 0 || empty($categoryId)) {
    header("Location: /views/seller/edit_product.php?id=$product_id&error=empty");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: /views/seller/products.php?error=unauthorized");
        exit;
    }
} catch (PDOException $e) {
    header("Location: /views/seller/products.php?error=database");
    exit;
}

$image_name = $old_image;

if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = $_FILES['image']['type'];
    $file_size = $_FILES['image']['size'];
    $max_size = 5 * 1024 * 1024;
    
    if (!in_array($file_type, $allowed_types)) {
        header("Location: /views/seller/edit_product.php?id=$product_id&error=invalid_type");
        exit;
    }
    
    if ($file_size > $max_size) {
        header("Location: /views/seller/edit_product.php?id=$product_id&error=file_too_large");
        exit;
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/amulets/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $image_name = uniqid('amulet_') . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $image_name;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
        if ($old_image && file_exists($upload_dir . $old_image)) {
            unlink($upload_dir . $old_image);
        }
    } else {
        header("Location: /views/seller/edit_product.php?id=$product_id&error=upload");
        exit;
    }
}

try {
    $stmt = $db->prepare("
        UPDATE amulets 
        SET amulet_name = :amulet_name,
            source = :source,
            quantity = :quantity,
            price = :price,
            image = :image,
            categoryId = :categoryId
        WHERE id = :id AND sellerId = :seller_id
    ");
    
    $result = $stmt->execute([
        ':amulet_name' => $amulet_name,
        ':source' => $source,
        ':quantity' => (int)$quantity,
        ':price' => (float)$price,
        ':image' => $image_name,
        ':categoryId' => $categoryId,
        ':id' => $product_id,
        ':seller_id' => $seller_id
    ]);
    
    if ($result) {
        header("Location: /views/seller/edit_product.php?id=$product_id&success=1");
        exit;
    } else {
        header("Location: /views/seller/edit_product.php?id=$product_id&error=database");
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Edit Product Error: " . $e->getMessage());
    
    header("Location: /views/seller/edit_product.php?id=$product_id&error=database");
    exit;
}
?>