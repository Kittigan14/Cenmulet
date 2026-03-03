<?php
/**
 * auth/register_seller_process.php
 * สร้าง seller account ด้วย status = 'pending' เพื่อรอ Admin อนุมัติ
 * 
 * Path: cenmulet/auth/register_seller_process.php
 * 
 * ต้องการ column เพิ่มใน table sellers:
 *   status ENUM('pending','approved','rejected') DEFAULT 'pending'
 *   reject_reason TEXT NULL
 *   reviewed_at DATETIME NULL
 */

session_start();
require_once __DIR__ . "/../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/auth/register_seller.php");
    exit;
}

/* ── Collect & Validate ───────────────── */
$store_name  = trim($_POST['store_name']  ?? '');
$address     = trim($_POST['address']     ?? '');
$fullname    = trim($_POST['fullname']    ?? '');
$tel         = trim($_POST['tel']         ?? '');
$id_per      = trim($_POST['id_per']      ?? '');
$username    = trim($_POST['username']    ?? '');
$password    = $_POST['password']         ?? '';
$pay_contax  = trim($_POST['pay_contax']  ?? '');

// Validate address components
$house_number  = trim($_POST['house_number']  ?? '');
$province      = trim($_POST['province']      ?? '');
$district      = trim($_POST['district']      ?? '');
$subdistrict   = trim($_POST['subdistrict']   ?? '');
$postal_code   = trim($_POST['postal_code']   ?? '');

if (!$store_name || !$address || !$fullname || !$tel || !$id_per || !$username || !$password) {
    header("Location: ../views/auth/register_seller.php?error=empty");
    exit;
}

// Validate address components are selected
if (!$house_number || !$province || !$district || !$subdistrict) {
    header("Location: ../views/auth/register_seller.php?error=empty");
    exit;
}

/* ── Check duplicate username ─────────── */
try {
    $stmt = $db->prepare("SELECT id FROM sellers WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        header("Location: ../views/auth/register_seller.php?error=username_exists");
        exit;
    }
} catch (PDOException $e) {
    error_log("Seller Register Error: " . $e->getMessage());
    header("Location: ../views/auth/register_seller.php?error=database");
    exit;
}

/* ── Handle file uploads ──────────────── */
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size      = 5 * 1024 * 1024; // 5 MB
$upload_dir    = $_SERVER['DOCUMENT_ROOT'] . '/../uploads/sellers/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function uploadFile(string $field, string $upload_dir, array $allowed_types, int $max_size): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$field];
    if (!in_array($file['type'], $allowed_types) || $file['size'] > $max_size) {
        return null;
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = uniqid($field . '_') . '_' . time() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $upload_dir . $name);
    return $name;
}

$img_store = uploadFile('img_store', $upload_dir, $allowed_types, $max_size);
$img_per   = uploadFile('img_per',   $upload_dir, $allowed_types, $max_size);

/* ── Insert seller (status = pending) ── */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("
        INSERT INTO sellers
            (store_name, address, fullname, tel, id_per, username, password,
             pay_contax, img_store, img_per, status)
        VALUES
            (:store_name, :address, :fullname, :tel, :id_per, :username, :password,
             :pay_contax, :img_store, :img_per, 'pending')
    ");
    $stmt->execute([
        ':store_name' => $store_name,
        ':address'    => $address,
        ':fullname'   => $fullname,
        ':tel'        => $tel,
        ':id_per'     => $id_per,
        ':username'   => $username,
        ':password'   => $hashed_password,
        ':pay_contax' => $pay_contax,
        ':img_store'  => $img_store,
        ':img_per'    => $img_per,
    ]);

    header("Location: ../views/auth/register_seller.php?success=pending");
    exit;

} catch (PDOException $e) {
    // clean up uploaded files on DB error
    if ($img_store && file_exists($upload_dir . $img_store)) unlink($upload_dir . $img_store);
    if ($img_per   && file_exists($upload_dir . $img_per))   unlink($upload_dir . $img_per);

    error_log("Seller Register DB Error: " . $e->getMessage());
    header("Location: ../views/auth/register_seller.php?error=database");
    exit;
}