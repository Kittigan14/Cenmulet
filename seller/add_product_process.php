<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

$amulet_name = $_POST['amulet_name'] ?? '';
$source      = $_POST['source']      ?? '';
$quantity    = $_POST['quantity']    ?? 0;
$price       = $_POST['price']       ?? 0;
$categoryId  = $_POST['categoryId']  ?? null;

if (empty($amulet_name) || empty($source) || $quantity < 0 || $price < 0 || empty($categoryId)) {
    header("Location: /views/seller/add_product.php?error=empty");
    exit;
}

// ตรวจสอบจำนวนรูปภาพ (ขั้นต่ำ 5 รูป)
$uploaded_files = $_FILES['images'] ?? null;
$valid_files    = [];

if ($uploaded_files) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $max_size = 5 * 1024 * 1024;

    foreach ($uploaded_files['error'] as $idx => $err) {
        if ($err !== UPLOAD_ERR_OK) continue;

        $file_type = $uploaded_files['type'][$idx];
        $file_size = $uploaded_files['size'][$idx];

        if (!in_array($file_type, $allowed_types)) continue;
        if ($file_size > $max_size) continue;

        $valid_files[] = [
            'tmp_name'  => $uploaded_files['tmp_name'][$idx],
            'name'      => $uploaded_files['name'][$idx],
        ];
    }
}

if (count($valid_files) < 5) {
    header("Location: /views/seller/add_product.php?error=min_images");
    exit;
}

// อัปโหลดรูปภาพ
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/amulets/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$saved_images = [];
foreach ($valid_files as $file) {
    $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename   = uniqid('amulet_') . '_' . time() . rand(100, 999) . '.' . $ext;
    $dest       = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $saved_images[] = $filename;
    }
}

if (count($saved_images) < 5) {
    // ลบรูปที่อัปโหลดไปแล้ว
    foreach ($saved_images as $f) {
        @unlink($upload_dir . $f);
    }
    header("Location: /views/seller/add_product.php?error=upload");
    exit;
}

// บันทึกลงฐานข้อมูล
try {
    $db->beginTransaction();

    // บันทึก amulet (รูปแรกเป็น main image)
    $stmt = $db->prepare("
        INSERT INTO amulets (amulet_name, source, quantity, price, image, sellerId, categoryId)
        VALUES (:amulet_name, :source, :quantity, :price, :image, :sellerId, :categoryId)
    ");
    $stmt->execute([
        ':amulet_name' => $amulet_name,
        ':source'      => $source,
        ':quantity'    => (int)$quantity,
        ':price'       => (float)$price,
        ':image'       => $saved_images[0],
        ':sellerId'    => $seller_id,
        ':categoryId'  => $categoryId,
    ]);
    $amulet_id = $db->lastInsertId();

    // บันทึกรูปภาพทุกรูปใน amulet_images
    $stmt2 = $db->prepare("
        INSERT INTO amulet_images (amulet_id, image, sort_order)
        VALUES (:amulet_id, :image, :sort_order)
    ");
    foreach ($saved_images as $order => $img) {
        $stmt2->execute([
            ':amulet_id'  => $amulet_id,
            ':image'      => $img,
            ':sort_order' => $order,
        ]);
    }

    $db->commit();
    header("Location: /views/seller/add_product.php?success=1");
    exit;

} catch (PDOException $e) {
    $db->rollBack();
    foreach ($saved_images as $f) {
        @unlink($upload_dir . $f);
    }
    error_log("Add Product Error: " . $e->getMessage());
    header("Location: /views/seller/add_product.php?error=database");
    exit;
}
?>