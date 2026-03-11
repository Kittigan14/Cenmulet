<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/admin/users.php"); exit;
}

$id       = (int)($_POST['id'] ?? 0);
$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$tel      = trim($_POST['tel']      ?? '');
$id_per   = preg_replace('/\D/', '', $_POST['id_per'] ?? '');
$address  = trim($_POST['address']  ?? '');

if (!$id || !$fullname || !$username) {
    header("Location: /views/admin/users.php?error=1"); exit;
}

// ตรวจ username ซ้ำ (ยกเว้น user ตัวเอง)
$check = $db->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
$check->execute([':u' => $username, ':id' => $id]);
if ($check->fetch()) {
    header("Location: /views/admin/users.php?error=duplicate"); exit;
}

try {
    $stmt = $db->prepare("
        UPDATE users
        SET fullname = :fullname,
            username = :username,
            tel      = :tel,
            id_per   = :id_per,
            address  = :address
        WHERE id = :id
    ");
    $stmt->execute([
        ':fullname' => $fullname,
        ':username' => $username,
        ':tel'      => $tel,
        ':id_per'   => $id_per,
        ':address'  => $address,
        ':id'       => $id,
    ]);
    header("Location: /views/admin/users.php?success=updated"); exit;
} catch (Exception $e) {
    header("Location: /views/admin/users.php?error=1"); exit;
}
