<?php
session_start();
require_once "../config/db.php";

$username = $_POST['username'];
$password = $_POST['password'];
$role     = $_POST['role'];

switch ($role) {
    case 'seller':
        $table = 'sellers';
        $redirect = '/view/seller/dashboard.php';
        break;

    case 'user':
        $table = 'users';
        $redirect = '/view/user/home.php';
        break;

    case 'admin':
        $table = 'admins';
        $redirect = '/view/admin/dashboard.php';
        break;

    default:
        header("Location: /view/auth/login.php?error=1");
        exit;
}

$stmt = $db->prepare("SELECT * FROM $table WHERE username = :u");
$stmt->execute([':u' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $role;

    header("Location: $redirect");
    exit;
}

header("Location: /view/auth/login.php?error=1");
exit;
?>