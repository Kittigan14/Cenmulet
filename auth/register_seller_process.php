<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$fullname = $_POST['fullname'] ?? '';
$store_name = $_POST['store_name'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$address = $_POST['address'] ?? '';
$tel = $_POST['tel'] ?? '';
$pay_contax = $_POST['pay_contax'] ?? '';
$id_per = $_POST['id_per'] ?? '';

if (empty($fullname) || empty($store_name) || empty($username) || empty($password) || empty($address) || empty($tel) || empty($id_per)) {
    header("Location: /views/auth/register_seller.php?error=empty");
    exit;
}

try {
    $stmt = $db->prepare("SELECT id FROM sellers WHERE username = :username");
    $stmt->execute([':username' => $username]);
    
    if ($stmt->fetch()) {
        header("Location: /views/auth/register_seller.php?error=username_exists");
        exit;
    }
} catch (PDOException $e) {
    header("Location: /views/auth/register_seller.php?error=database");
    exit;
}

$img_store_name = null;
if (isset($_FILES['img_store']) && $_FILES['img_store']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = $_FILES['img_store']['type'];
    
    if (in_array($file_type, $allowed_types)) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/stores/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['img_store']['name'], PATHINFO_EXTENSION);
        $img_store_name = uniqid('store_') . '.' . $file_extension;
        $upload_path = $upload_dir . $img_store_name;
        
        if (!move_uploaded_file($_FILES['img_store']['tmp_name'], $upload_path)) {
            $img_store_name = null;
        }
    }
}

$img_per_name = null;
if (isset($_FILES['img_per']) && $_FILES['img_per']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = $_FILES['img_per']['type'];
    
    if (in_array($file_type, $allowed_types)) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/id_cards/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['img_per']['name'], PATHINFO_EXTENSION);
        $img_per_name = uniqid('id_') . '.' . $file_extension;
        $upload_path = $upload_dir . $img_per_name;
        
        if (!move_uploaded_file($_FILES['img_per']['tmp_name'], $upload_path)) {
            $img_per_name = null;
        }
    }
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("
        INSERT INTO sellers (fullname, store_name, username, password, address, img_store, tel, pay_contax, id_per, img_per) 
        VALUES (:fullname, :store_name, :username, :password, :address, :img_store, :tel, :pay_contax, :id_per, :img_per)
    ");
    
    $stmt->execute([
        ':fullname' => $fullname,
        ':store_name' => $store_name,
        ':username' => $username,
        ':password' => $hashed_password,
        ':address' => $address,
        ':img_store' => $img_store_name,
        ':tel' => $tel,
        ':pay_contax' => $pay_contax,
        ':id_per' => $id_per,
        ':img_per' => $img_per_name
    ]);
    
    header("Location: /views/auth/login.php?success=registered");
    exit;
    
} catch (PDOException $e) {
    if ($img_store_name && file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/stores/' . $img_store_name)) {
        unlink($_SERVER['DOCUMENT_ROOT'] . '/uploads/stores/' . $img_store_name);
    }
    if ($img_per_name && file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/id_cards/' . $img_per_name)) {
        unlink($_SERVER['DOCUMENT_ROOT'] . '/uploads/id_cards/' . $img_per_name);
    }
    
    header("Location: /views/auth/register_seller.php?error=database");
    exit;
}
?>