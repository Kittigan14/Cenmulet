<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$tel = $_POST['tel'] ?? '';
$address = $_POST['address'] ?? '';
$id_per = $_POST['id_per'] ?? '';

if (empty($fullname) || empty($username) || empty($password) || empty($tel) || empty($address) || empty($id_per)) {
    header("Location: /views/auth/register_user.php?error=empty");
    exit;
}

try {
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    
    if ($stmt->fetch()) {
        header("Location: /views/auth/register_user.php?error=username_exists");
        exit;
    }
} catch (PDOException $e) {
    header("Location: /views/auth/register_user.php?error=database");
    exit;
}

$image_name = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $file_type = $_FILES['image']['type'];
    
    if (in_array($file_type, $allowed_types)) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/users/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = uniqid('user_') . '.' . $file_extension;
        $upload_path = $upload_dir . $image_name;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_name = null;
        }
    }
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("
        INSERT INTO users (fullname, username, password, tel, address, image, id_per) 
        VALUES (:fullname, :username, :password, :tel, :address, :image, :id_per)
    ");
    
    $stmt->execute([
        ':fullname' => $fullname,
        ':username' => $username,
        ':password' => $hashed_password,
        ':tel' => $tel,
        ':address' => $address,
        ':image' => $image_name,
        ':id_per' => $id_per
    ]);
    
    header("Location: /views/auth/login.php?success=registered");
    exit;
    
} catch (PDOException $e) {
    if ($image_name && file_exists($upload_dir . $image_name)) {
        unlink($upload_dir . $image_name);
    }
    
    header("Location: /views/auth/register_user.php?error=database");
    exit;
}
?>