<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$product_id = (int)($_GET['id'] ?? 0);

if (!$product_id) {
    header("Location: /views/admin/products.php?error=invalid");
    exit;
}

try {
    $stmt = $db->prepare("SELECT id, is_hidden FROM amulets WHERE id = :id");
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: /views/admin/products.php?error=notfound");
        exit;
    }

    $new_hidden = $product['is_hidden'] ? 0 : 1;

    $stmt = $db->prepare("UPDATE amulets SET is_hidden = :hidden WHERE id = :id");
    $stmt->execute([':hidden' => $new_hidden, ':id' => $product_id]);

    $msg = $new_hidden ? 'hidden' : 'shown';
    header("Location: /views/admin/products.php?success=$msg");
    exit;

} catch (PDOException $e) {
    error_log("Admin Toggle Visibility Error: " . $e->getMessage());
    header("Location: /views/admin/products.php?error=database");
    exit;
}
