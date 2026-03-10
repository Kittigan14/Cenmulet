<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id  = $_SESSION['user_id'];
$product_id = (int)($_GET['id'] ?? 0);

if (!$product_id) {
    header("Location: /views/seller/products.php?error=invalid");
    exit;
}

try {
    // ตรวจสอบว่าสินค้านี้เป็นของ seller นี้
    $stmt = $db->prepare("SELECT id, is_hidden FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: /views/seller/products.php?error=unauthorized");
        exit;
    }

    $new_hidden = $product['is_hidden'] ? 0 : 1;

    $stmt = $db->prepare("UPDATE amulets SET is_hidden = :hidden WHERE id = :id AND sellerId = :seller_id");
    $stmt->execute([':hidden' => $new_hidden, ':id' => $product_id, ':seller_id' => $seller_id]);

    $msg = $new_hidden ? 'hidden' : 'shown';
    header("Location: /views/seller/products.php?success=$msg");
    exit;

} catch (PDOException $e) {
    error_log("Toggle Visibility Error: " . $e->getMessage());
    header("Location: /views/seller/products.php?error=database");
    exit;
}
