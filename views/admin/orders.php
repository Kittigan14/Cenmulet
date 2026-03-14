<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

// ── Handle POST: update order ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_order') {
    $oid      = (int)($_POST['id']              ?? 0);
    $tracking = trim($_POST['tracking_number']  ?? '');
    $o_status = trim($_POST['order_status']     ?? '');
    $p_status = trim($_POST['pay_status']       ?? '');
    $address  = trim($_POST['address']          ?? '');

    $allowed_o = ['pending','confirmed','completed'];
    $allowed_p = ['waiting','confirmed','rejected'];

    if ($oid) {
        if (in_array($o_status, $allowed_o)) {
            $db->prepare("UPDATE orders SET status=:s, tracking_number=:t WHERE id=:id")
               ->execute([':s'=>$o_status, ':t'=>$tracking, ':id'=>$oid]);
            if ($address) {
                $db->prepare("UPDATE users SET address=:a WHERE id=(SELECT user_id FROM orders WHERE id=:id)")
                   ->execute([':a'=>$address, ':id'=>$oid]);
            }
        }
        if (in_array($p_status, $allowed_p)) {
            $db->prepare("UPDATE payments SET status=:s WHERE order_id=:id")
               ->execute([':s'=>$p_status, ':id'=>$oid]);
        }
    }
    header("Location: /views/admin/orders.php?success=updated"); exit;
}
// ─────────────────────────────────────────────────────────

$admin_id = $_SESSION['user_id'];

// แปลงวันที่เป็นปี พ.ศ.
function dateTH(string $format, $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    $year_ad = (int) date('Y', $timestamp);
    $year_be = $year_ad + 543;
    $formatted = date($format, $timestamp);
    return str_replace($year_ad, $year_be, $formatted);
}

$stmt = $db->prepare("SELECT id, fullname FROM admins WHERE id = :id");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$allowed = ['all','waiting','confirmed','completed'];
if (!in_array($filter, $allowed)) $filter = 'all';

$conditions = [];
$params     = [];
if ($search) {
    $conditions[] = "(u.fullname LIKE :q OR u.tel LIKE :q)";
    $params[':q'] = "%$search%";
}
if ($filter === 'waiting')   $conditions[] = "p.status = 'waiting'";
if ($filter === 'confirmed') $conditions[] = "p.status = 'confirmed' AND o.status != 'completed'";
if ($filter === 'completed') $conditions[] = "o.status = 'completed'";

$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$orders = $db->prepare("
    SELECT o.id, o.user_id, o.total_price, o.status, o.created_at,
           o.tracking_number, o.shipped_at,
           u.fullname as buyer_name, u.tel as buyer_tel, u.address as buyer_address,
           p.slip_image, p.status as pay_status, p.confirmed_at,
           p.transfer_amount, p.transfer_time,
           COUNT(DISTINCT oi.id) as item_count,
           GROUP_CONCAT(DISTINCT s.store_name) as store_names
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payments p ON o.id = p.order_id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN amulets a ON oi.amulet_id = a.id
    LEFT JOIN sellers s ON a.sellerId = s.id
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute($params);
$orders = $orders->fetchAll(PDO::FETCH_ASSOC);

// stats
$n_all       = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$n_waiting   = $db->query("SELECT COUNT(*) FROM payments WHERE status='waiting'")->fetchColumn();
$n_confirmed = $db->query("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN payments p ON o.id=p.order_id WHERE p.status='confirmed' AND o.status!='completed'")->fetchColumn();
$n_completed = $db->query("SELECT COUNT(*) FROM orders WHERE status='completed'")->fetchColumn();
$total_rev   = $db->query("SELECT COALESCE(SUM(total_price),0) FROM orders WHERE status='completed'")->fetchColumn();
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการการเช่า - Cenmulet Admin</title>
    <style>
        .slip-thumb { width:48px;height:48px;border-radius:8px;object-fit:cover;cursor:zoom-in;border:2px solid #e5e7eb;transition:transform .2s; }
        .slip-thumb:hover { transform:scale(1.1); }
        #slipModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;align-items:center;justify-content:center; }
        #slipModal img { max-width:90vw;max-height:85vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5); }
        /* Order detail modal */
        #detailModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:998;align-items:center;justify-content:center; }
        .detail-box { background:#fff;border-radius:16px;padding:28px;max-width:560px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2); }
    </style>
</head>
<body class="admin">
<div class="dashboard-container">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <div class="top-bar">
        <h1><i class="fa-solid fa-cart-shopping"></i> จัดการการเช่า</h1>
        <div style="display:flex;align-items:center;gap:12px">
            <strong style="color:#10b981;font-size:16px">฿<?php echo number_format($total_rev, 2); ?></strong>
            <a href="/views/admin/report.php?type=orders" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-print"></i> พิมพ์รายงาน
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span>แก้ไขข้อมูลการเช่าเรียบร้อยแล้ว</span></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-mini" style="margin-bottom:20px">
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_all; ?></div><div class="stat-mini-label">ทั้งหมด</div></div>
            <div class="stat-mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fa-solid fa-list"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_waiting; ?></div><div class="stat-mini-label">รอยืนยันชำระ</div></div>
            <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-clock"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_confirmed; ?></div><div class="stat-mini-label">กำลังจัดส่ง</div></div>
            <div class="stat-mini-icon" style="background:#dbeafe;color:#3b82f6"><i class="fa-solid fa-truck"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_completed; ?></div><div class="stat-mini-label">เสร็จสิ้น</div></div>
            <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-double"></i></div>
        </div>
    </div>

    <!-- Filter + Search -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center">
        <div class="filter-tabs" style="margin-bottom:0">
            <a href="?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='all'       ? 'active':'' ?>">ทั้งหมด <span class="tab-count"><?php echo $n_all; ?></span></a>
            <a href="?filter=waiting<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='waiting'   ? 'active':'' ?>"><i class="fa-solid fa-clock"></i> รอยืนยัน <span class="tab-count"><?php echo $n_waiting; ?></span></a>
            <a href="?filter=confirmed<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='confirmed' ? 'active':'' ?>"><i class="fa-solid fa-truck"></i> กำลังจัดส่ง <span class="tab-count"><?php echo $n_confirmed; ?></span></a>
            <a href="?filter=completed<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='completed' ? 'active':'' ?>"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น <span class="tab-count"><?php echo $n_completed; ?></span></a>
        </div>
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="ค้นหาชื่อลูกค้า, เบอร์โทร..."
                   style="flex:1;padding:9px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i></button>
            <?php if ($search): ?>
            <a href="?filter=<?php echo $filter; ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
        <?php if (count($orders) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>การเช่า</th>
                    <th>ผู้เช่า</th>
                    <th>ร้านค้า</th>
                    <th>ที่อยู่จัดส่ง</th>
                    <th>ยอดรวม</th>
                    <th>สลิป</th>
                    <th>สถานะชำระ</th>
                    <th>สถานะส่ง</th>
                    <th>เลขพัสดุ</th>
                    <th>วันที่</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td>
                    <strong>#<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    <div style="font-size:12px;color:#9ca3af"><?php echo $o['item_count']; ?> รายการ</div>
                </td>
                <td>
                    <div style="font-weight:500"><?php echo htmlspecialchars($o['buyer_name']); ?></div>
                    <div style="font-size:12px;color:#6b7280"><?php echo htmlspecialchars($o['buyer_tel']); ?></div>
                </td>
                <td>
                    <?php
                    $stores = array_filter(explode(',', $o['store_names'] ?? ''));
                    foreach ($stores as $_st): ?>
                    <span style="display:inline-flex;align-items:center;gap:3px;background:#ede9fe;color:#6d28d9;padding:2px 7px;border-radius:99px;font-size:11px;font-weight:600;margin:1px">
                        <i class="fa-solid fa-store" style="font-size:9px"></i>
                        <?php echo htmlspecialchars(trim($_st)); ?>
                    </span>
                    <?php endforeach; ?>
                </td>
                <td style="font-size:12px;color:#6b7280;max-width:130px;word-break:break-word">
                    <?php echo htmlspecialchars($o['buyer_address'] ?? '-'); ?>
                </td>
                <td><strong style="color:#10b981">฿<?php echo number_format($o['total_price'], 2); ?></strong></td>
                <td>
                    <?php if (!empty($o['slip_image'])): ?>
                    <img src="/uploads/slips/<?php echo htmlspecialchars($o['slip_image']); ?>"
                         class="slip-thumb"
                         onclick="openSlip('/uploads/slips/<?php echo htmlspecialchars($o['slip_image']); ?>')"
                         alt="สลิป">
                    <?php else: ?>
                    <span style="color:#d1d5db;font-size:12px">ไม่มี</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['pay_status'] === 'waiting'): ?>
                        <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> รอยืนยัน</span>
                    <?php elseif ($o['pay_status'] === 'confirmed'): ?>
                        <span class="badge badge-info"><i class="fa-solid fa-check"></i> ยืนยันแล้ว</span>
                    <?php elseif ($o['pay_status'] === 'rejected'): ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-times"></i> ปฏิเสธ</span>
                    <?php else: ?>
                        <span style="color:#9ca3af;font-size:12px">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['status'] === 'completed'): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>
                    <?php elseif ($o['pay_status'] === 'confirmed'): ?>
                        <span class="badge badge-info"><i class="fa-solid fa-truck"></i> กำลังส่ง</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($o['tracking_number'])): ?>
                    <span style="font-family:monospace;font-size:11px;background:#f0fdf4;color:#059669;padding:3px 7px;border-radius:6px;border:1px solid #a7f3d0">
                        <?php echo htmlspecialchars($o['tracking_number']); ?>
                    </span>
                    <?php else: ?><span style="color:#d1d5db;font-size:11px">-</span><?php endif; ?>
                </td>
                <td style="font-size:12px;color:#6b7280;white-space:nowrap">
                    <?php echo dateTH('d/m/Y', strtotime($o['created_at'])); ?><br>
                    <span style="color:#9ca3af"><?php echo dateTH('H:i', strtotime($o['created_at'])); ?></span>
                </td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <button class="btn-icon view" title="ดูรายละเอียด"
                                onclick="openDetail(<?php echo $o['id']; ?>)">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="btn-icon" title="แก้ไข"
                                style="background:#e0e7ff;color:#6366f1;border-color:#c7d2fe"
                                onclick="openDetail(<?php echo $o['id']; ?>);setTimeout(switchOrderEdit,50)">
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
            <i class="fa-solid fa-cart-shopping"></i>
            <h2>ไม่พบการเช่า</h2>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>

<!-- Slip Modal -->
<div id="slipModal" onclick="closeSlip()">
    <img id="slipImg" src="" alt="สลิป">
</div>

<!-- Order Detail Modal -->
<div id="detailModal" onclick="if(event.target===this)closeDetail()">
    <div class="detail-box" id="detailBox">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 id="detailTitle" style="font-size:18px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-receipt" style="color:#6366f1"></i>
                รายละเอียดการเช่า
            </h3>
            <button onclick="closeDetail()" style="background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer">×</button>
        </div>
        <!-- View mode -->
        <div id="detailViewMode">
            <div id="detailContent" style="color:#6b7280;text-align:center;padding:20px">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:28px;margin-bottom:10px;display:block"></i>
                กำลังโหลด...
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                <button onclick="closeDetail()" class="btn btn-secondary">ปิด</button>
                <button onclick="switchOrderEdit()" class="btn btn-primary">
                    <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                </button>
            </div>
        </div>
        <!-- Edit mode -->
        <div id="detailEditMode" style="display:none">
            <form action="/views/admin/orders.php" method="POST">
                <input type="hidden" name="_action" value="update_order">
                <input type="hidden" name="id" id="eo_id">
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:13px;color:#6b7280;font-weight:600;margin-bottom:4px">
                        <i class="fa-solid fa-truck"></i> เลขพัสดุ
                    </label>
                    <input type="text" name="tracking_number" id="eo_tracking"
                        placeholder="กรอกเลขพัสดุ"
                        style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                    <div>
                        <label style="display:block;font-size:13px;color:#6b7280;font-weight:600;margin-bottom:4px">
                            <i class="fa-solid fa-cart-shopping"></i> สถานะการเช่า
                        </label>
                        <select name="order_status" id="eo_ostatus"
                            style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box">
                            <option value="pending">รอดำเนินการ</option>
                            <option value="confirmed">กำลังจัดส่ง</option>
                            <option value="completed">เสร็จสิ้น</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;color:#6b7280;font-weight:600;margin-bottom:4px">
                            <i class="fa-solid fa-money-bill"></i> สถานะชำระเงิน
                        </label>
                        <select name="pay_status" id="eo_pstatus"
                            style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box">
                            <option value="waiting">รอยืนยัน</option>
                            <option value="confirmed">ยืนยันแล้ว</option>
                            <option value="rejected">ปฏิเสธ</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:13px;color:#6b7280;font-weight:600;margin-bottom:4px">
                        <i class="fa-solid fa-location-dot"></i> ที่อยู่จัดส่ง
                    </label>
                    <textarea name="address" id="eo_address" rows="3"
                        style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box;resize:vertical"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                    <button type="button" onclick="switchOrderView()" class="btn btn-secondary">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preload order items data -->
<script>
const ordersData = <?php
    // ดึง order items ทั้งหมดสำหรับ orders ที่แสดงในหน้านี้
    if (count($orders) > 0) {
        $ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items_stmt = $db->prepare("
            SELECT oi.order_id, oi.quantity, oi.price,
                   a.amulet_name, a.image
            FROM order_items oi
            JOIN amulets a ON oi.amulet_id = a.id
            WHERE oi.order_id IN ($placeholders)
        ");
        $items_stmt->execute($ids);
        $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by order_id
        $grouped = [];
        foreach ($all_items as $item) {
            $grouped[$item['order_id']][] = $item;
        }

        // Map order data
        $order_map = [];
        foreach ($orders as $o) {
            $order_map[$o['id']] = [
                'id'              => $o['id'],
                'buyer'           => $o['buyer_name'],
                'tel'             => $o['buyer_tel'],
                'address'         => $o['buyer_address'],
                'total'           => $o['total_price'],
                'pay'             => $o['pay_status'],
                'status'          => $o['status'],
                'date'            => $o['created_at'],
                'slip'            => $o['slip_image'],
                'transfer_amount' => $o['transfer_amount'],
                'transfer_time'   => $o['transfer_time'],
                'tracking'        => $o['tracking_number'] ?? '',
                'items'           => $grouped[$o['id']] ?? [],
            ];
        }
        echo json_encode($order_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    } else {
        echo '{}';
    }
?>;


function openSlip(src) {
    document.getElementById('slipImg').src = src;
    document.getElementById('slipModal').style.display = 'flex';
}
function closeSlip() { document.getElementById('slipModal').style.display = 'none'; }

// XSS-safe HTML escaping for user-supplied strings in innerHTML
function esc(s) {
    if (s == null) return '';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}

var _currentOrder = null;

function openDetail(id) {
    const o = ordersData[id];
    if (!o) return;
    _currentOrder = o;
    document.getElementById('detailViewMode').style.display = 'block';
    document.getElementById('detailEditMode').style.display = 'none';
    document.getElementById('detailTitle').innerHTML = '<i class="fa-solid fa-receipt" style="color:#6366f1"></i> รายละเอียดการเช่า';

    const payLabels = {
        waiting:  '<span class="badge badge-warning"><i class="fa-solid fa-clock"></i> รอยืนยัน</span>',
        confirmed:'<span class="badge badge-info"><i class="fa-solid fa-check"></i> ยืนยันแล้ว</span>',
        rejected: '<span class="badge badge-danger"><i class="fa-solid fa-times"></i> ปฏิเสธ</span>',
    };
    const statusLabel = o.status === 'completed'
        ? '<span class="badge badge-success"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>'
        : '<span class="badge badge-warning"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>';

    const date = new Date(o.date).toLocaleString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', calendar: 'buddhist' });

    let itemsHtml = o.items.map(item => `
        <div style="display:flex;align-items:center;gap:12px;padding:10px;background:#f9fafb;border-radius:8px;margin-bottom:8px">
            ${item.image
                ? `<img src="/uploads/amulets/${esc(item.image)}" style="width:44px;height:44px;border-radius:6px;object-fit:cover">`
                : `<div style="width:44px;height:44px;border-radius:6px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af"><i class="fa-solid fa-image"></i></div>`
            }
            <div style="flex:1">
                <div style="font-weight:600;color:#1a1a1a;font-size:14px">${esc(item.amulet_name)}</div>
                <div style="font-size:12px;color:#6b7280">จำนวน: ${item.quantity} × ฿${Number(item.price).toLocaleString('th-TH', {minimumFractionDigits:2})}</div>
            </div>
            <div style="font-weight:700;color:#10b981">฿${(item.quantity * item.price).toLocaleString('th-TH', {minimumFractionDigits:2})}</div>
        </div>
    `).join('');

    document.getElementById('detailContent').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <div style="background:#f9fafb;padding:14px;border-radius:10px">
                <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">การเช่า</div>
                <div style="font-weight:700;font-size:16px">#${String(o.id).padStart(6,'0')}</div>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">${o.date ? new Date(o.date).toLocaleString('th-TH', {year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',calendar:'buddhist'}) : '-'}</div>
            </div>
            <div style="background:#f9fafb;padding:14px;border-radius:10px">
                <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">ผู้เช่า</div>
                <div style="font-weight:600">${esc(o.buyer)}</div>
                <div style="font-size:12px;color:#6b7280">${esc(o.tel)}</div>
            </div>
        </div>
        <div style="background:#f9fafb;padding:14px;border-radius:10px;margin-bottom:16px">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">ที่อยู่จัดส่ง</div>
            <div style="font-size:14px">${esc(o.address) || '-'}</div>
        </div>
        <div style="margin-bottom:16px">
            <div style="font-size:13px;font-weight:700;color:#374151;margin-bottom:10px">รายการพระเครื่อง</div>
            ${itemsHtml || '<p style="color:#9ca3af;font-size:13px">ไม่พบรายการ</p>'}
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:#ecfdf5;border-radius:10px;margin-bottom:12px">
            <span style="font-weight:600">ยอดรวมทั้งหมด</span>
            <span style="font-weight:700;font-size:18px;color:#10b981">฿${Number(o.total).toLocaleString('th-TH', {minimumFractionDigits:2})}</span>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:12px">
            <div style="flex:1;text-align:center">${payLabels[o.pay] || '-'}</div>
            <div style="flex:1;text-align:center">${statusLabel}</div>
        </div>
        ${(o.transfer_amount !== null && o.transfer_amount !== undefined) || o.transfer_time ? `
        <div style="background:#f0fdf4;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;margin-bottom:12px">
            <div style="font-size:11px;color:#059669;font-weight:700;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                <i class="fa-solid fa-money-bill-wave"></i> ข้อมูลการโอนเงิน
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap">
                <div>
                    <div style="font-size:11px;color:#9ca3af">จำนวนเงินที่โอน</div>
                    <div style="font-weight:700;color:#10b981;font-size:15px">
                        ${o.transfer_amount !== null && o.transfer_amount !== undefined ? '฿' + Number(o.transfer_amount).toLocaleString('th-TH', {minimumFractionDigits:2}) : '-'}
                    </div>
                </div>
                <div>
                    <div style="font-size:11px;color:#9ca3af">เวลาที่โอน</div>
                    <div style="font-weight:600;font-size:13px">
                        ${o.transfer_time ? new Date(o.transfer_time).toLocaleString('th-TH', {year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',calendar:'buddhist'}) : '-'}
                    </div>
                </div>
            </div>
        </div>` : ''}
        ${o.slip ? `
        <div style="margin-top:14px;text-align:center">
            <div style="font-size:12px;color:#9ca3af;margin-bottom:8px">สลิปการโอนเงิน</div>
            <img src="/uploads/slips/${o.slip}" style="max-width:100%;border-radius:10px;cursor:zoom-in"
                 onclick="openSlip('/uploads/slips/${o.slip}')">
        </div>` : ''}
    `;

    document.getElementById('detailModal').style.display = 'flex';
}

function switchOrderEdit() {
    const o = _currentOrder;
    document.getElementById('detailTitle').innerHTML = '<i class="fa-solid fa-pen-to-square" style="color:#6366f1"></i> แก้ไขการเช่า';
    document.getElementById('eo_id').value       = o.id;
    document.getElementById('eo_tracking').value = o.tracking || '';
    document.getElementById('eo_ostatus').value  = o.status  || 'pending';
    document.getElementById('eo_pstatus').value  = o.pay     || 'waiting';
    document.getElementById('eo_address').value  = o.address || '';
    document.getElementById('detailViewMode').style.display = 'none';
    document.getElementById('detailEditMode').style.display = 'block';
}

function switchOrderView() {
    document.getElementById('detailTitle').innerHTML = '<i class="fa-solid fa-receipt" style="color:#6366f1"></i> รายละเอียการเช่า';
    document.getElementById('detailViewMode').style.display = 'block';
    document.getElementById('detailEditMode').style.display = 'none';
}

function closeDetail() { document.getElementById('detailModal').style.display = 'none'; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeSlip(); closeDetail(); } });
</script>
</body>
</html>