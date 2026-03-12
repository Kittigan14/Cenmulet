<?php
/**
 * views/seller/orders.php
 * Seller ดูคำสั่งเช่าและยืนยัน/ตรวจสอบการชำระเงิน
 */
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT id, fullname, store_name, pay_contax FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowed = ['all','waiting','confirmed','completed'];
if (!in_array($filter, $allowed)) $filter = 'all';

// ดึงคำสั่งเช่าที่มีพระเครื่องของ seller นี้
try {
    $filter_sql = '';
    if ($filter === 'waiting')   $filter_sql = "AND p.status = 'waiting'";
    if ($filter === 'confirmed') $filter_sql = "AND p.status = 'confirmed' AND o.status != 'completed'";
    if ($filter === 'completed') $filter_sql = "AND o.status = 'completed'";

    $stmt = $db->prepare("
        SELECT DISTINCT
            o.id, o.total_price, o.status, o.created_at,
            u.fullname as buyer_name, u.tel as buyer_tel, u.address as buyer_address,
            p.slip_image, p.status as payment_status, p.confirmed_at,
            p.transfer_amount, p.transfer_time,
            COUNT(DISTINCT oi.id) as item_count,
            o.tracking_number, o.shipped_at
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE a.sellerId = :seller_id
        $filter_sql
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // นับแต่ละสถานะ
    $count_all       = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN amulets a ON oi.amulet_id=a.id WHERE a.sellerId=:id");
    $count_waiting   = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN amulets a ON oi.amulet_id=a.id LEFT JOIN payments p ON o.id=p.order_id WHERE a.sellerId=:id AND p.status='waiting'");
    $count_confirmed = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN amulets a ON oi.amulet_id=a.id LEFT JOIN payments p ON o.id=p.order_id WHERE a.sellerId=:id AND p.status='confirmed' AND o.status!='completed'");
    $count_completed = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN amulets a ON oi.amulet_id=a.id WHERE a.sellerId=:id AND o.status='completed'");

    foreach ([$count_all,$count_waiting,$count_confirmed,$count_completed] as $s) {
        $s->execute([':id' => $seller_id]);
    }
    $n_all       = $count_all->fetchColumn();
    $n_waiting   = $count_waiting->fetchColumn();
    $n_confirmed = $count_confirmed->fetchColumn();
    $n_completed = $count_completed->fetchColumn();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>คำสั่งเช่า - Cenmulet Seller</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');
        body { font-family: "Kanit", sans-serif; background: #f3f4f6; }

        .slip-thumb {
            width: 52px; height: 52px;
            border-radius: 8px;
            object-fit: cover;
            cursor: zoom-in;
            border: 2px solid #e5e7eb;
            transition: transform .2s;
        }
        .slip-thumb:hover { transform: scale(1.08); }

        /* Lightbox */
        #slipModal {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.75);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        #slipModal img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .confirm-form button { font-family: inherit; }

        /* Edit Modal */
        #editModal {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        #editModal.open { display: flex; }
        .edit-modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 30px 32px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 24px 60px rgba(0,0,0,.2);
            animation: slideUp .25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        .edit-modal-box h3 {
            font-size: 18px; font-weight: 700;
            color: #1f2937; margin: 0 0 20px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 12px;
        }
        .edit-field { margin-bottom: 16px; }
        .edit-field label {
            display: block; font-size: 13px;
            color: #6b7280; margin-bottom: 5px;
        }
        .edit-field input, .edit-field select, .edit-field textarea {
            width: 100%; padding: 9px 12px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px; font-size: 14px;
            font-family: inherit; box-sizing: border-box;
            transition: border-color .2s;
        }
        .edit-field input:focus, .edit-field select:focus, .edit-field textarea:focus {
            outline: none; border-color: #6366f1;
        }
        .edit-modal-actions {
            display: flex; gap: 10px;
            justify-content: flex-end; margin-top: 22px;
        }
        .btn-edit { background: #6366f1; color: #fff; }
        .btn-edit:hover { background: #4f46e5; }
        .btn-cancel-modal { background: #f3f4f6; color: #374151; }
        .btn-cancel-modal:hover { background: #e5e7eb; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="">
            <h2>Cenmulet</h2>
            <p>แดชบอร์ดผู้ขาย</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
            <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
            <li><a href="/views/seller/orders.php" class="active"><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่า</a></li>
            <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
            <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <div class="top-bar">
            <h1><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่า</h1>
            <a href="/views/seller/report.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-print"></i> รายงานการขาย
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span>
                <?php
                $msgs = [
                    'confirmed' => 'ยืนยันการชำระเงินเรียบร้อยแล้ว',
                    'rejected'  => 'ปฏิเสธการชำระเงินเรียบร้อยแล้ว',
                ];
                echo $msgs[$_GET['success']] ?? 'ดำเนินการสำเร็จ';
                ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>
            <?php
            $errs = [
                'no_tracking'      => '<strong>ต้องกรอกเลขพัสดุก่อนกดยืนยันการชำระเงิน</strong> – กรุณากรอกเลขพัสดุในช่องวันที่ก่อน',
                'already_processed'=> 'คำสั่งเช่านี้ได้รับการดำเนินการไปแล้ว',
                'invalid'          => 'คำขอไม่ถูกต้อง กรุณาลองใหม่',
                'unauthorized'     => 'ไม่มีสิทธิ์ดำเนินการนี้',
                'database'         => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            ];
            echo $errs[$_GET['error']] ?? 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
            ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Stats mini -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $n_all; ?></div><div class="stat-mini-label">ทั้งหมด</div></div>
                <div class="stat-mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fa-solid fa-list"></i></div>
            </div>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $n_waiting; ?></div><div class="stat-mini-label">รอยืนยันชำระ</div></div>
                <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-clock"></i></div>
            </div>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $n_confirmed; ?></div><div class="stat-mini-label">ยืนยันแล้ว</div></div>
                <div class="stat-mini-icon" style="background:#dbeafe;color:#3b82f6"><i class="fa-solid fa-check"></i></div>
            </div>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $n_completed; ?></div><div class="stat-mini-label">เสร็จสิ้น</div></div>
                <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-double"></i></div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="?filter=all"       class="filter-tab <?php echo $filter==='all'       ? 'active':'' ?>"><i class="fa-solid fa-list"></i> ทั้งหมด <span class="tab-count"><?php echo $n_all; ?></span></a>
            <a href="?filter=waiting"   class="filter-tab <?php echo $filter==='waiting'   ? 'active':'' ?>"><i class="fa-solid fa-clock"></i> รอยืนยัน <span class="tab-count"><?php echo $n_waiting; ?></span></a>
            <a href="?filter=confirmed" class="filter-tab <?php echo $filter==='confirmed' ? 'active':'' ?>"><i class="fa-solid fa-truck"></i> กำลังจัดส่ง <span class="tab-count"><?php echo $n_confirmed; ?></span></a>
            <a href="?filter=completed" class="filter-tab <?php echo $filter==='completed' ? 'active':'' ?>"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น <span class="tab-count"><?php echo $n_completed; ?></span></a>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <?php if (count($orders) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>คำสั่งเช่า</th>
                            <th>ผู้เช่า</th>
                            <th>ยอดรวม</th>
                            <th>สลิปโอนเงิน</th>
                            <th>จำนวนเงินที่โอน</th>
                            <th>เวลาที่โอน</th>
                            <th>สถานะชำระเงิน</th>
                            <th>สถานะคำสั่งเช่า</th>
                            <th>วันที่</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                <div style="font-size:12px;color:#9ca3af"><?php echo $order['item_count']; ?> รายการ</div>
                            </td>
                            <td>
                                <div style="font-weight:500"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                <div style="font-size:12px;color:#6b7280"><?php echo htmlspecialchars($order['buyer_tel']); ?></div>
                            </td>
                            <td><strong style="color:#10b981">฿<?php echo number_format($order['total_price'], 2); ?></strong></td>
                            <td>
                                <?php if ($order['slip_image']): ?>
                                <img src="/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>"
                                     class="slip-thumb"
                                     onclick="openSlip('/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>')"
                                     alt="สลิป">
                                <?php else: ?>
                                <span style="color:#9ca3af;font-size:13px">ไม่มีสลิป</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;font-weight:600;color:#10b981">
                                <?php echo $order['transfer_amount'] !== null ? '฿' . number_format((float)$order['transfer_amount'], 2) : '<span style="color:#9ca3af">-</span>'; ?>
                            </td>
                            <td style="font-size:12px;color:#6b7280;white-space:nowrap">
                                <?php echo $order['transfer_time'] ? date('d/m/Y H:i', strtotime($order['transfer_time'])) : '<span style="color:#9ca3af">-</span>'; ?>
                            </td>
                            <td>
                                <?php if ($order['payment_status'] === 'waiting'): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> รอยืนยัน</span>
                                <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                                    <span class="badge badge-info"><i class="fa-solid fa-check"></i> ยืนยันแล้ว</span>
                                <?php elseif ($order['payment_status'] === 'rejected'): ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-times"></i> ปฏิเสธ</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['status'] === 'completed'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>
                                <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                                    <span class="badge badge-info"><i class="fa-solid fa-truck"></i> กำลังจัดส่ง</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($order['tracking_number'])): ?>
                                <div style="font-family:monospace;font-size:12px;background:#f0fdf4;color:#059669;padding:4px 9px;border-radius:6px;border:1px solid #a7f3d0;margin-bottom:5px;font-weight:700">
                                    <i class="fa-solid fa-truck" style="font-size:10px"></i>
                                    <?php echo htmlspecialchars($order['tracking_number']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (in_array($order['payment_status'], ['waiting', 'confirmed']) && $order['status'] !== 'completed'): ?>
                                <form action="/seller/update_tracking.php" method="POST"
                                      style="display:flex;gap:5px;align-items:center">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <input type="text" name="tracking_number"
                                           value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                           placeholder="<?php echo $order['payment_status']==='waiting' ? 'กรอกก่อนยืนยัน *' : 'กรอกเลขพัสดุ'; ?>"
                                           style="width:130px;padding:5px 8px;border:2px solid <?php echo ($order['payment_status']==='waiting' && empty($order['tracking_number'])) ? '#f59e0b' : '#e5e7eb'; ?>;border-radius:6px;font-size:12px;font-family:inherit">
                                    <button type="submit" class="btn btn-sm btn-primary"
                                            style="padding:5px 10px;font-size:12px"
                                            title="บันทึกเลขพัสดุ">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                </form>
                                <?php if ($order['payment_status']==='waiting' && empty($order['tracking_number'])): ?>
                                <div style="font-size:11px;color:#f59e0b;margin-top:3px">
                                    <i class="fa-solid fa-triangle-exclamation"></i> บันทึกก่อนยืนยัน
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <span style="font-size:11px;color:#d1d5db">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:13px;color:#6b7280;white-space:nowrap">
                                <?php echo date('d/m/Y', strtotime($order['created_at'])); ?><br>
                                <span style="color:#9ca3af"><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                <?php if ($order['payment_status'] === 'waiting'): ?>
                                    <!-- ยืนยันการชำระเงิน -->
                                    <form class="confirm-form" action="/views/seller/confirm_payment.php" method="POST">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                onclick="return confirm('ยืนยันการชำระเงินสำหรับ Order #<?php echo str_pad($order['id'],6,'0',STR_PAD_LEFT); ?> ?')">
                                            <i class="fa-solid fa-check"></i> ยืนยันชำระ
                                        </button>
                                    </form>
                                    <form class="confirm-form" action="/views/seller/confirm_payment.php" method="POST">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('ปฏิเสธการชำระเงินนี้?')">
                                            <i class="fa-solid fa-times"></i> ปฏิเสธ
                                        </button>
                                    </form>
                                <?php elseif ($order['payment_status'] === 'confirmed' && $order['status'] !== 'completed'): ?>
                                    <span style="font-size:13px;color:#6b7280">รอลูกค้ายืนยันรับ</span>
                                <?php elseif ($order['status'] === 'completed'): ?>
                                    <span style="font-size:13px;color:#10b981"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>
                                <?php endif; ?>
                                    <a href="/views/seller/order_detail.php?id=<?php echo $order['id']; ?>"
                                       class="btn-icon view" title="ดูรายละเอียด">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <button type="button"
                                        class="btn-icon"
                                        style="background:#e0e7ff;color:#6366f1;border:none;padding:7px 10px;border-radius:8px;cursor:pointer;font-size:14px"
                                        title="แก้ไขข้อมูล"
                                        onclick="openEdit({
                                            id:   '<?php echo $order['id']; ?>',
                                            name: '<?php echo addslashes(htmlspecialchars($order['buyer_name'])); ?>',
                                            tel:  '<?php echo addslashes(htmlspecialchars($order['buyer_tel'])); ?>',
                                            addr: '<?php echo addslashes(htmlspecialchars($order['buyer_address'])); ?>',
                                            tracking: '<?php echo addslashes(htmlspecialchars($order['tracking_number'] ?? '')); ?>',
                                            status: '<?php echo $order['status']; ?>',
                                            payment_status: '<?php echo $order['payment_status']; ?>'
                                        })">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <h2>ยังไม่มีคำสั่งเช่า</h2>
                    <p>เมื่อมีลูกค้าสั่งเช่าพระเครื่องของคุณ จะแสดงที่นี่</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Edit Order Modal -->
<div id="editModal" onclick="handleModalOverlayClick(event)">
    <div class="edit-modal-box">
        <h3><i class="fa-solid fa-pen-to-square" style="color:#6366f1;margin-right:8px"></i>แก้ไขข้อมูลคำสั่งเช่า <span id="editOrderNum" style="color:#6366f1"></span></h3>
        <form action="/views/seller/update_order.php" method="POST">
            <input type="hidden" name="order_id" id="editOrderId">

            <div class="edit-field">
                <label><i class="fa-solid fa-user" style="margin-right:4px"></i>ชื่อผู้เช่า</label>
                <input type="text" name="buyer_name" id="editBuyerName" placeholder="ชื่อผู้เช่า">
            </div>
            <div class="edit-field">
                <label><i class="fa-solid fa-phone" style="margin-right:4px"></i>เบอร์โทรศัพท์</label>
                <input type="text" name="buyer_tel" id="editBuyerTel" placeholder="เบอร์โทรศัพท์">
            </div>
            <div class="edit-field">
                <label><i class="fa-solid fa-location-dot" style="margin-right:4px"></i>ที่อยู่จัดส่ง</label>
                <textarea name="buyer_address" id="editBuyerAddr" rows="3" placeholder="ที่อยู่จัดส่ง"></textarea>
            </div>
            <div class="edit-field">
                <label><i class="fa-solid fa-truck" style="margin-right:4px"></i>เลขพัสดุ</label>
                <input type="text" name="tracking_number" id="editTracking" placeholder="กรอกเลขพัสดุ">
            </div>
            <div class="edit-field">
                <label><i class="fa-solid fa-circle-dot" style="margin-right:4px"></i>สถานะคำสั่งเช่า</label>
                <select name="order_status" id="editStatus">
                    <option value="pending">รอดำเนินการ</option>
                    <option value="confirmed">ยืนยันแล้ว</option>
                    <option value="completed">เสร็จสิ้น</option>
                </select>
            </div>
            <div class="edit-modal-actions">
                <button type="button" class="btn btn-sm btn-cancel-modal" onclick="closeEdit()">
                    <i class="fa-solid fa-xmark"></i> ยกเลิก
                </button>
                <button type="submit" class="btn btn-sm btn-edit">
                    <i class="fa-solid fa-floppy-disk"></i> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Slip Lightbox -->
<div id="slipModal" onclick="closeSlip()">
    <img id="slipImg" src="" alt="สลิปการโอนเงิน">
</div>

<script>
function openSlip(src) {
    document.getElementById('slipImg').src = src;
    const m = document.getElementById('slipModal');
    m.style.display = 'flex';
}
function closeSlip() {
    document.getElementById('slipModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeSlip(); closeEdit(); }
});

function openEdit(data) {
    document.getElementById('editOrderId').value   = data.id;
    document.getElementById('editOrderNum').textContent = '#' + String(data.id).padStart(6, '0');
    document.getElementById('editBuyerName').value  = data.name;
    document.getElementById('editBuyerTel').value   = data.tel;
    document.getElementById('editBuyerAddr').value  = data.addr;
    document.getElementById('editTracking').value   = data.tracking;
    document.getElementById('editStatus').value     = data.status;
    document.getElementById('editModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
    document.body.style.overflow = '';
}
function handleModalOverlayClick(e) {
    if (e.target === document.getElementById('editModal')) closeEdit();
}
</script>
</body>
</html>