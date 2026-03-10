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

        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                header("Location: $redirect?error=db"); exit;
            }
            // Atomic toggle — single round-trip
            $stmt = $db->prepare(
                "UPDATE categories SET is_hidden = 1 - COALESCE(is_hidden, 0) WHERE id = :id"
            );
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                header("Location: $redirect?error=db"); exit;
            }
            // Read back new state for redirect message
            $row = $db->prepare("SELECT is_hidden FROM categories WHERE id = :id");
            $row->execute([':id' => $id]);
            $new_hidden = (int)$row->fetch(PDO::FETCH_ASSOC)['is_hidden'];
            $msg = $new_hidden ? 'hidden' : 'shown';
            header("Location: $redirect?success=$msg");
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
