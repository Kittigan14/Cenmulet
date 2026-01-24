<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header("Location: /views/auth/login.php?error=empty");
    exit;
}

$tables = [
    'users' => '/views/user/home.php',
    'sellers' => '/views/seller/dashboard.php',
    'admins' => '/views/admin/dashboard.php'
];

$user = null;
$role = null;

foreach ($tables as $table => $redirect_path) {
    try {
        $stmt = $db->prepare("SELECT * FROM $table WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $user = $result;
            $role = rtrim($table, 's');
            break;
        }
    } catch (PDOException $e) {
        continue;
    }
}

if (!$user) {
    header("Location: /views/auth/login.php?error=user_not_found");
    exit;
}

if (!password_verify($password, $user['password'])) {
    header("Location: /views/auth/login.php?error=wrong_password");
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $role;
$_SESSION['username'] = $user['username'];
$_SESSION['fullname'] = $user['fullname'] ?? '';

$redirect = $tables[$role . 's'];
header("Location: $redirect");
exit;
?>