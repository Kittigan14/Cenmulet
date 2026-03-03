<?php
/**
 * admin/category_action.php
 * Admin: เพิ่ม/แก้ไข/ลบ หมวดหมู่
 */
session_start();
require_once __DIR__ . "/../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/admin/categories.php");
    exit;
}

$action = $_POST['action'] ?? '';
$redirect = "/views/admin/categories.php";

try {
    switch ($action) {

        case 'add':
            $name = trim($_POST['category_name'] ?? '');
            if (empty($name)) {
                header("Location: $redirect?error=empty"); exit;
            }
            // ตรวจ duplicate
            $check = $db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(:name)");
            $check->execute([':name' => $name]);
            if ($check->fetchColumn() > 0) {
                header("Location: $redirect?error=duplicate"); exit;
            }
            $stmt = $db->prepare("INSERT INTO categories (category_name) VALUES (:name)");
            $stmt->execute([':name' => $name]);
            header("Location: $redirect?success=added");
            exit;

        case 'edit':
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            if (!$id || empty($name)) {
                header("Location: $redirect?error=empty"); exit;
            }
            // ตรวจ duplicate (ยกเว้นตัวเอง)
            $check = $db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(:name) AND id != :id");
            $check->execute([':name' => $name, ':id' => $id]);
            if ($check->fetchColumn() > 0) {
                header("Location: $redirect?error=duplicate"); exit;
            }
            $stmt = $db->prepare("UPDATE categories SET category_name = :name WHERE id = :id");
            $stmt->execute([':name' => $name, ':id' => $id]);
            header("Location: $redirect?success=edited");
            exit;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                header("Location: $redirect?error=db"); exit;
            }
            // ตรวจว่ามีสินค้าอยู่ไหม
            $check = $db->prepare("SELECT COUNT(*) FROM amulets WHERE categoryId = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                header("Location: $redirect?error=has_items"); exit;
            }
            $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            header("Location: $redirect?success=deleted");
            exit;

        default:
            header("Location: $redirect");
            exit;
    }
} catch (PDOException $e) {
    error_log("Category Action Error: " . $e->getMessage());
    header("Location: $redirect?error=db");
    exit;
}
?>
