<?php
/**
 * auth/login_process.php
 * ตรวจสอบ seller ที่ status = 'pending' หรือ 'rejected' ไม่ให้ login ได้
 */
session_start();
require_once __DIR__ . "/../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/auth/login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    header("Location: /views/auth/login.php?error=empty");
    exit;
}

// ลำดับการตรวจสอบ: admin → seller → user
$tables = [
    'admins'  => 'admin',
    'sellers' => 'seller',
    'users'   => 'user',
];

foreach ($tables as $table => $role) {
    try {
        $stmt = $db->prepare("SELECT * FROM $table WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record && password_verify($password, $record['password'])) {

            // ตรวจสอบสถานะ seller ก่อน login
            if ($role === 'seller') {
                $status = $record['status'] ?? 'approved';

                if ($status === 'pending') {
                    header("Location: /views/auth/login.php?error=seller_pending");
                    exit;
                }
                if ($status === 'rejected') {
                    $reason = urlencode($record['reject_reason'] ?? '');
                    header("Location: /views/auth/login.php?error=seller_rejected&reason=$reason");
                    exit;
                }
            }

            // เริ่ม session
            $_SESSION['user_id']  = $record['id'];
            $_SESSION['role']     = $role;
            $_SESSION['username'] = $record['username'];

            // Redirect ตาม role
            $redirects = [
                'admin'  => '/views/admin/dashboard.php',
                'seller' => '/views/seller/dashboard.php',
                'user'   => '/views/user/home.php',
            ];
            header("Location: " . $redirects[$role]);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
    }
}

// ไม่พบ username ในทุก table
try {
    // เช็คว่า username มีอยู่ในระบบหรือเปล่า
    foreach (['admins','sellers','users'] as $table) {
        $stmt = $db->prepare("SELECT id FROM $table WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        if ($stmt->fetch()) {
            header("Location: /views/auth/login.php?error=wrong_password&username=" . urlencode($username));
            exit;
        }
    }
} catch (PDOException $e) {
    error_log("Login Check Error: " . $e->getMessage());
}

header("Location: /views/auth/login.php?error=user_not_found");
exit;