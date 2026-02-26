<?php
/**
 * seller/confirm_payment.php
 * Seller ยืนยัน หรือ ปฏิเสธ การชำระเงิน
 */
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /views/seller/orders.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$order_id  = (int)($_POST['order_id'] ?? 0);
$action    = $_POST['action'] ?? '';

if (!$order_id || !in_array($action, ['confirm', 'reject'])) {
    header("Location: /views/seller/orders.php?error=invalid");
    exit;
}

try {
    // ตรวจสอบว่า order นี้มีสินค้าของ seller จริงๆ
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE oi.order_id = :order_id AND a.sellerId = :seller_id
    ");
    $stmt->execute([':order_id' => $order_id, ':seller_id' => $seller_id]);
    if ($stmt->fetchColumn() === '0') {
        header("Location: /views/seller/orders.php?error=unauthorized");
        exit;
    }

    // ตรวจสอบว่า payment ยังเป็น 'waiting'
    $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment || $payment['status'] !== 'waiting') {
        header("Location: /views/seller/orders.php?error=already_processed");
        exit;
    }

    if ($action === 'confirm') {
        // อัปเดต payment status เป็น confirmed
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'confirmed', confirmed_at = datetime('now')
            WHERE order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);

        header("Location: /views/seller/orders.php?success=confirmed");
    } else {
        // ปฏิเสธ - คืนสินค้า stock
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'rejected', confirmed_at = datetime('now')
            WHERE order_id = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);

        // คืน stock
        $stmt = $db->prepare("SELECT amulet_id, quantity FROM order_items WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $stmt2 = $db->prepare("UPDATE amulets SET quantity = quantity + :qty WHERE id = :id");
            $stmt2->execute([':qty' => $item['quantity'], ':id' => $item['amulet_id']]);
        }

        $db->commit();
        header("Location: /views/seller/orders.php?success=rejected");
    }
    exit;

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Seller Confirm Payment Error: " . $e->getMessage());
    header("Location: /views/seller/orders.php?error=database");
    exit;
}