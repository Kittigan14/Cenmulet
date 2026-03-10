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
    // Atomic toggle: no race condition, single DB round-trip
    $stmt = $db->prepare(
        "UPDATE amulets SET is_hidden = 1 - COALESCE(is_hidden, 0) WHERE id = :id"
    );
    $stmt->execute([':id' => $product_id]);

    if ($stmt->rowCount() === 0) {
        header("Location: /views/admin/products.php?error=notfound");
        exit;
    }

    // Read back new state for redirect message
    $row = $db->prepare("SELECT is_hidden FROM amulets WHERE id = :id");
    $row->execute([':id' => $product_id]);
    $new_hidden = (int)$row->fetch(PDO::FETCH_ASSOC)['is_hidden'];

    $msg = $new_hidden ? 'hidden' : 'shown';
    header("Location: /views/admin/products.php?success=$msg");
    exit;

} catch (PDOException $e) {
    error_log("Admin Toggle Visibility Error: " . $e->getMessage());
    header("Location: /views/admin/products.php?error=database");
    exit;
}
