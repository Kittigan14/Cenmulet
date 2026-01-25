<?php
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: /views/seller/products.php?error=missing_id");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: /views/seller/products.php?error=unauthorized");
        exit;
    }
} catch (PDOException $e) {
    header("Location: /views/seller/products.php?error=database");
    exit;
}

try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("DELETE FROM cart WHERE amulet_id = :product_id");
    $stmt->execute([':product_id' => $product_id]);
    
    $stmt = $db->prepare("DELETE FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $result = $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    
    if ($result) {
        if ($product['image']) {
            $image_path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/amulets/' . $product['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $db->commit();
        
        header("Location: /views/seller/products.php?success=deleted");
        exit;
    } else {
        $db->rollBack();
        header("Location: /views/seller/products.php?error=delete_failed");
        exit;
    }
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Delete Product Error: " . $e->getMessage());
    
    header("Location: /views/seller/products.php?error=database");
    exit;
}
?>