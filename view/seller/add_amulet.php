<?php
session_start();
require_once "../config/db.php";

if ($_SESSION['role'] !== 'seller') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("
        INSERT INTO amulets
        (amulet_name, price, quantity, sellerId, categoryId)
        VALUES (:n, :p, :q, :s, :c)
    ");

    $stmt->execute([
        ':n' => $_POST['name'],
        ':p' => $_POST['price'],
        ':q' => $_POST['quantity'],
        ':s' => $_SESSION['user_id'],
        ':c' => $_POST['category']
    ]);

    echo "เพิ่มพระสำเร็จ";
}
?>
