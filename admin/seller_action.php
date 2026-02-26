<?php
/**
 * admin/seller_action.php
 * Admin อนุมัติ / ปฏิเสธ / ยกเลิกสิทธิ์ seller
 */
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/admin/approve_sellers.php");
    exit;
}

$seller_id     = (int)($_POST['seller_id']     ?? 0);
$action        = $_POST['action']              ?? '';
$reject_reason = trim($_POST['reject_reason']  ?? '');

if (!$seller_id || !in_array($action, ['approve', 'reject', 'revoke'])) {
    header("Location: /views/admin/approve_sellers.php?error=invalid");
    exit;
}

try {
    switch ($action) {
        case 'approve':
            $stmt = $db->prepare("
                UPDATE sellers
                SET status = 'approved', reject_reason = NULL, reviewed_at = datetime('now')
                WHERE id = :id
            ");
            $stmt->execute([':id' => $seller_id]);
            header("Location: /views/admin/approve_sellers.php?done=approved");
            break;

        case 'reject':
            if (!$reject_reason) {
                header("Location: /views/admin/approve_sellers.php?error=no_reason");
                exit;
            }
            $stmt = $db->prepare("
                UPDATE sellers
                SET status = 'rejected', reject_reason = :reason, reviewed_at = datetime('now')
                WHERE id = :id
            ");
            $stmt->execute([':reason' => $reject_reason, ':id' => $seller_id]);
            header("Location: /views/admin/approve_sellers.php?done=rejected");
            break;

        case 'revoke':
            $stmt = $db->prepare("
                UPDATE sellers
                SET status = 'pending', reviewed_at = NULL
                WHERE id = :id
            ");
            $stmt->execute([':id' => $seller_id]);
            header("Location: /views/admin/approve_sellers.php?done=revoked");
            break;
    }
    exit;

} catch (PDOException $e) {
    error_log("Seller Action Error: " . $e->getMessage());
    header("Location: /views/admin/approve_sellers.php?error=database");
    exit;
}