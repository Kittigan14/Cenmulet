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
    // Atomic toggle with ownership check — single DB round-trip
    $stmt = $db->prepare(
        "UPDATE amulets SET is_hidden = 1 - COALESCE(is_hidden, 0)
         WHERE id = :id AND sellerId = :seller_id"
    );
    $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);

    if ($stmt->rowCount() === 0) {
        // Either product not found or doesn't belong to this seller
        header("Location: /views/seller/products.php?error=unauthorized");
        exit;
    }

    // Read back new state for redirect message
    $row = $db->prepare("SELECT is_hidden FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $row->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    $new_hidden = (int)$row->fetch(PDO::FETCH_ASSOC)['is_hidden'];

    $msg = $new_hidden ? 'hidden' : 'shown';
    header("Location: /views/seller/products.php?success=$msg");
    exit;

} catch (PDOException $e) {
    error_log("Toggle Visibility Error: " . $e->getMessage());
    header("Location: /views/seller/products.php?error=database");
    exit;
}
