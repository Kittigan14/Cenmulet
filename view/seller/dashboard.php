<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../auth/login.php");
    exit;
}
?>

<h1>Seller Dashboard</h1>
<a href="add_amulet.php">➕ เพิ่มพระเครื่อง</a>
