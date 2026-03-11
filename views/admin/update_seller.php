<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/admin/sellers.php"); exit;
}

$id         = (int)($_POST['id']         ?? 0);
$store_name = trim($_POST['store_name']  ?? '');
$fullname   = trim($_POST['fullname']    ?? '');
$username   = trim($_POST['username']    ?? '');
$tel        = trim($_POST['tel']         ?? '');
$pay_bank   = trim($_POST['pay_bank']    ?? '');
$pay_contax = trim($_POST['pay_contax']  ?? '');
$id_per     = preg_replace('/\D/', '', $_POST['id_per'] ?? '');
$address    = trim($_POST['address']     ?? '');

if (!$id || !$store_name || !$fullname || !$username) {
    header("Location: /views/admin/sellers.php?error=invalid"); exit;
}

// ตรวจ username ซ้ำ (ยกเว้น seller ตัวเอง)
$check = $db->prepare("SELECT id FROM sellers WHERE username = :u AND id != :id");
$check->execute([':u' => $username, ':id' => $id]);
if ($check->fetch()) {
    header("Location: /views/admin/sellers.php?error=duplicate"); exit;
}

try {
    $stmt = $db->prepare("
        UPDATE sellers
        SET store_name  = :store_name,
            fullname    = :fullname,
            username    = :username,
            tel         = :tel,
            pay_bank    = :pay_bank,
            pay_contax  = :pay_contax,
            id_per      = :id_per,
            address     = :address
        WHERE id = :id
    ");
    $stmt->execute([
        ':store_name'  => $store_name,
        ':fullname'    => $fullname,
        ':username'    => $username,
        ':tel'         => $tel,
        ':pay_bank'    => $pay_bank,
        ':pay_contax'  => $pay_contax,
        ':id_per'      => $id_per,
        ':address'     => $address,
        ':id'          => $id,
    ]);
    header("Location: /views/admin/sellers.php?done=updated"); exit;
} catch (Exception $e) {
    error_log("Update Seller Error: " . $e->getMessage());
    header("Location: /views/admin/sellers.php?error=database"); exit;
}
