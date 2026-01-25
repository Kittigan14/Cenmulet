<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

$amulet_name = $_POST['amulet_name'] ?? '';
$source = $_POST['source'] ?? '';
$quantity = $_POST['quantity'] ?? 0;
$price = $_POST['price'] ?? 0;
$categoryId = $_POST['categoryId'] ?? null;

if (empty($amulet_name) || empty($source) || $quantity < 0 || $price < 0 || empty($categoryId)) {
    header("Location: /views/seller/add_product.php?error=empty");
    exit;
}

$image_name = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = $_FILES['image']['type'];
    $file_size = $_FILES['image']['size'];
    $max_size = 5 * 1024 * 1024;
    
    if (!in_array($file_type, $allowed_types)) {
        header("Location: /views/seller/add_product.php?error=invalid_type");
        exit;
    }
    
    if ($file_size > $max_size) {
        header("Location: /views/seller/add_product.php?error=file_too_large");
        exit;
    }
    
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/amulets/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $image_name = uniqid('amulet_') . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $image_name;
    
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
        header("Location: /views/seller/add_product.php?error=upload");
        exit;
    }
}

try {
    $stmt = $db->prepare("
        INSERT INTO amulets (amulet_name, source, quantity, price, image, sellerId, categoryId) 
        VALUES (:amulet_name, :source, :quantity, :price, :image, :sellerId, :categoryId)
    ");
    
    $result = $stmt->execute([
        ':amulet_name' => $amulet_name,
        ':source' => $source,
        ':quantity' => (int)$quantity,
        ':price' => (float)$price,
        ':image' => $image_name,
        ':sellerId' => $seller_id,
        ':categoryId' => $categoryId
    ]);
    
    if ($result) {
        header("Location: /views/seller/add_product.php?success=1");
        exit;
    } else {
        if ($image_name && file_exists($upload_dir . $image_name)) {
            unlink($upload_dir . $image_name);
        }
        header("Location: /views/seller/add_product.php?error=database");
        exit;
    }
    
} catch (PDOException $e) {
    if ($image_name && file_exists($upload_dir . $image_name)) {
        unlink($upload_dir . $image_name);
    }
    
    error_log("Add Product Error: " . $e->getMessage());
    
    header("Location: /views/seller/add_product.php?error=database");
    exit;
}
?>