<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

// ── Handle POST: update seller ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_seller') {
    $id         = (int)($_POST['id']         ?? 0);
    $store_name = trim($_POST['store_name']  ?? '');
    $fullname   = trim($_POST['fullname']    ?? '');
    $username   = trim($_POST['username']    ?? '');
    $tel        = trim($_POST['tel']         ?? '');
    $pay_bank   = trim($_POST['pay_bank']    ?? '');
    $pay_contax = trim($_POST['pay_contax']  ?? '');
    $id_per     = preg_replace('/\D/', '', $_POST['id_per'] ?? '');
    $address    = trim($_POST['address']     ?? '');

    if ($id && $store_name && $fullname && $username) {
        $check = $db->prepare("SELECT id FROM sellers WHERE username = :u AND id != :id");
        $check->execute([':u' => $username, ':id' => $id]);
        if ($check->fetch()) {
            header("Location: /views/admin/sellers.php?error=duplicate"); exit;
        }
        $stmt = $db->prepare("
            UPDATE sellers SET
                store_name = :store_name, fullname = :fullname, username = :username,
                tel = :tel, pay_bank = :pay_bank, pay_contax = :pay_contax,
                id_per = :id_per, address = :address
            WHERE id = :id
        ");
        $stmt->execute([
            ':store_name' => $store_name, ':fullname' => $fullname, ':username' => $username,
            ':tel' => $tel, ':pay_bank' => $pay_bank, ':pay_contax' => $pay_contax,
            ':id_per' => $id_per, ':address' => $address, ':id' => $id,
        ]);
    }
    header("Location: /views/admin/sellers.php?done=updated"); exit;
}
// ───────────────────────────────────────────────────────

$admin_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['search'] ?? '');
$allowed = ['all','approved','pending','rejected'];
if (!in_array($filter, $allowed)) $filter = 'all';

$conditions = [];
$params     = [];
if ($filter !== 'all') { $conditions[] = "status = :status"; $params[':status'] = $filter; }
if ($search)           { $conditions[] = "(store_name LIKE :q OR fullname LIKE :q OR username LIKE :q)"; $params[':q'] = "%$search%"; }
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$stmt = $db->prepare("SELECT * FROM sellers $where ORDER BY id DESC");
$stmt->execute($params);
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$n_all      = $db->query("SELECT COUNT(*) FROM sellers")->fetchColumn();
$n_approved = $db->query("SELECT COUNT(*) FROM sellers WHERE status='approved'")->fetchColumn();
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
$n_rejected = $db->query("SELECT COUNT(*) FROM sellers WHERE status='rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการผู้ขาย - Cenmulet Admin</title>
    <style>
        #editSellerModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:998;align-items:center;justify-content:center; }
        .edit-box { background:#fff;border-radius:16px;padding:28px;max-width:540px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2); }
        .edit-box label { display:block;font-size:13px;color:#6b7280;margin-bottom:4px;font-weight:600; }
        .edit-box input, .edit-box textarea, .edit-box select {
            width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;
            font-family:inherit;font-size:14px;box-sizing:border-box;transition:border-color .2s;
        }
        .edit-box input:focus, .edit-box textarea:focus, .edit-box select:focus { outline:none;border-color:#6366f1; }
        .edit-box textarea { resize:vertical;min-height:70px; }
        .form-group { margin-bottom:14px; }
        .form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
        tr.clickable-row { cursor:pointer; }
        tr.clickable-row:hover td { background:#f5f3ff !important; }
    </style>
</head>
<body class="admin">
<div class="dashboard-container">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <div class="top-bar">
        <h1><i class="fa-solid fa-store"></i> จัดการผู้ขาย</h1>
        <?php if ($pending_sellers > 0): ?>
        <a href="/views/admin/approve_sellers.php?filter=pending" class="btn btn-primary">
            <i class="fa-solid fa-clock"></i> รออนุมัติ <?php echo $pending_sellers; ?> ราย
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['done']) && $_GET['done'] === 'updated'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span>แก้ไขข้อมูลผู้ขายเรียบร้อยแล้ว</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <span>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</span></div>
    <?php endif; ?>

    <?php if ($pending_sellers > 0): ?>
    <div class="pending-banner">
        <i class="fa-solid fa-store"></i>
        <div style="flex:1">
            <h3>มีผู้สมัครขาย <?php echo $pending_sellers; ?> ราย รอการอนุมัติ</h3>
        </div>
        <a href="/views/admin/approve_sellers.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-arrow-right"></i> ไปอนุมัติ
        </a>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-mini" style="margin-bottom:20px">
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_all; ?></div><div class="stat-mini-label">ทั้งหมด</div></div>
            <div class="stat-mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fa-solid fa-store"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_approved; ?></div><div class="stat-mini-label">อนุมัติแล้ว</div></div>
            <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-circle"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $pending_sellers; ?></div><div class="stat-mini-label">รออนุมัติ</div></div>
            <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-clock"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $n_rejected; ?></div><div class="stat-mini-label">ปฏิเสธ</div></div>
            <div class="stat-mini-icon" style="background:#fee2e2;color:#ef4444"><i class="fa-solid fa-times-circle"></i></div>
        </div>
    </div>

    <!-- Filter + Search -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center">
        <div class="filter-tabs" style="margin-bottom:0">
            <a href="?filter=all<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='all'      ? 'active':'' ?>">ทั้งหมด <span class="tab-count"><?php echo $n_all; ?></span></a>
            <a href="?filter=approved<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='approved' ? 'active':'' ?>">อนุมัติแล้ว <span class="tab-count"><?php echo $n_approved; ?></span></a>
            <a href="?filter=pending<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='pending'  ? 'active':'' ?>">รออนุมัติ <span class="tab-count"><?php echo $pending_sellers; ?></span></a>
            <a href="?filter=rejected<?php echo $search ? '&search='.urlencode($search) : ''; ?>"
               class="filter-tab <?php echo $filter==='rejected' ? 'active':'' ?>">ปฏิเสธ <span class="tab-count"><?php echo $n_rejected; ?></span></a>
        </div>
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="ค้นหาชื่อร้าน, ชื่อผู้ขาย..."
                   style="flex:1;padding:9px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i></button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
        <?php if (count($sellers) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ร้านค้า</th>
                    <th>เจ้าของ</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>ช่องทางชำระเงิน</th>
                    <th>ที่อยู่</th>
                    <th>เอกสาร</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sellers as $s): ?>
            <?php $sB64 = base64_encode(json_encode([
                'id'         => (int)$s['id'],
                'store_name' => $s['store_name'],
                'fullname'   => $s['fullname'],
                'username'   => $s['username'],
                'tel'        => $s['tel'] ?? '',
                'id_per'     => $s['id_per'] ?? '',
                'address'    => $s['address'] ?? '',
                'pay_bank'   => $s['pay_bank'] ?? '',
                'pay_contax' => $s['pay_contax'] ?? '',
                'status'     => $s['status'],
            ], JSON_UNESCAPED_UNICODE)); ?>
            <tr class="clickable-row" onclick="openEdit('<?php echo $sB64; ?>')"
                style="cursor:pointer">
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <?php if (!empty($s['img_store'])): ?>
                        <img src="/uploads/sellers/<?php echo htmlspecialchars($s['img_store']); ?>"
                             style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:2px solid #e5e7eb">
                        <?php else: ?>
                        <div class="no-image"><i class="fa-solid fa-store"></i></div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600"><?php echo htmlspecialchars($s['store_name']); ?></div>
                            <div style="font-size:12px;color:#9ca3af">@<?php echo htmlspecialchars($s['username']); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($s['fullname']); ?></td>
                <td><?php echo htmlspecialchars($s['tel']); ?></td>
                <td style="font-size:13px;letter-spacing:.5px"><?php echo htmlspecialchars($s['id_per'] ?? '-'); ?></td>
                <td style="font-size:13px">
                    <?php if (!empty($s['pay_bank'])): ?>
                        <div style="font-weight:600;color:#374151"><?php echo htmlspecialchars($s['pay_bank']); ?></div>
                        <div style="color:#6b7280"><?php echo htmlspecialchars($s['pay_contax'] ?? '-'); ?></div>
                    <?php else: ?>
                        <?php echo htmlspecialchars($s['pay_contax'] ?? '-'); ?>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;max-width:160px;word-break:break-word"><?php echo htmlspecialchars($s['address'] ?? '-'); ?></td>
                <td>
                    <?php if (!empty($s['img_per'])): ?>
                    <a href="/uploads/sellers/<?php echo htmlspecialchars($s['img_per']); ?>"
                       target="_blank" class="btn btn-sm btn-secondary" style="font-size:11px;padding:4px 10px">
                        <i class="fa-solid fa-id-card"></i> ดูบัตร
                    </a>
                    <?php else: ?>
                    <span style="color:#d1d5db;font-size:12px">ไม่มี</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $badges = [
                        'approved' => ['badge-success','fa-check-circle','อนุมัติแล้ว'],
                        'pending'  => ['badge-warning','fa-clock','รออนุมัติ'],
                        'rejected' => ['badge-danger', 'fa-times-circle','ปฏิเสธ'],
                    ];
                    [$cls,$icon,$label] = $badges[$s['status']] ?? ['badge-warning','fa-clock','ไม่ทราบ'];
                    ?>
                    <span class="badge <?php echo $cls; ?>">
                        <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $label; ?>
                    </span>
                    <?php if ($s['status'] === 'pending'): ?>
                    <div style="margin-top:5px">
                        <a href="/views/admin/approve_sellers.php?filter=pending"
                           class="btn btn-sm btn-primary" style="font-size:11px;padding:4px 10px">
                            <i class="fa-solid fa-check"></i> อนุมัติ
                        </a>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="no-row-click">
                    <div style="display:flex;flex-direction:column;gap:5px">
                        <!-- ปุ่มแก้ไข -->
                        <button onclick="event.stopPropagation();openEdit('<?php echo $sB64; ?>')"
                            class="btn-icon" title="แก้ไข"
                            style="background:#e0e7ff;color:#6366f1;border-color:#c7d2fe">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <!-- ปุ่มอนุมัติ/ถอน/ปฏิเสธ -->
                        <?php if ($s['status'] === 'pending'): ?>
                        <form method="POST" action="/views/admin/seller_action.php">
                            <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="redirect_to" value="/views/admin/sellers.php">
                            <button type="submit" class="btn-icon" title="อนุมัติ"
                                style="background:#d1fae5;color:#059669;border-color:#a7f3d0">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </form>
                        <?php elseif ($s['status'] === 'approved'): ?>
                        <form method="POST" action="/views/admin/seller_action.php"
                              onsubmit="return confirm('ถอนสิทธิ์ผู้ขายรายนี้?')">
                            <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="redirect_to" value="/views/admin/sellers.php">
                            <button type="submit" class="btn-icon" title="ถอนสิทธิ์"
                                style="background:#fef3c7;color:#d97706;border-color:#fde68a">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-store"></i>
            <h2>ไม่พบข้อมูล</h2>
            <p>ลองเปลี่ยนตัวกรองหรือคำค้นหา</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>

<!-- Edit Seller Modal -->
<div id="editSellerModal" onclick="if(event.target===this)closeEdit()">
    <div class="edit-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-pen-to-square" style="color:#6366f1"></i> แก้ไขข้อมูลผู้ขาย
            </h3>
            <button onclick="closeEdit()" style="background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer">×</button>
        </div>
        <form action="/views/admin/sellers.php" method="POST">
            <input type="hidden" name="_action" value="update_seller">
            <input type="hidden" name="id" id="es_id">

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-store"></i> ชื่อร้านค้า</label>
                    <input type="text" name="store_name" id="es_store_name" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-at"></i> ชื่อผู้ใช้</label>
                    <input type="text" name="username" id="es_username" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" id="es_fullname" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-phone"></i> เบอร์โทรศัพท์</label>
                    <input type="text" name="tel" id="es_tel">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-building-columns"></i> ธนาคาร</label>
                    <input type="text" name="pay_bank" id="es_pay_bank" placeholder="เช่น ธนาคารกสิกรไทย">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-credit-card"></i> เลขบัญชี / พร้อมเพย์</label>
                    <input type="text" name="pay_contax" id="es_pay_contax">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-id-card"></i> เลขบัตรประชาชน</label>
                <input type="text" name="id_per" id="es_id_per" maxlength="13" placeholder="13 หลัก">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-location-dot"></i> ที่อยู่</label>
                <textarea name="address" id="es_address"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeEdit()" class="btn btn-secondary">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(b64) {
    var s = JSON.parse(decodeURIComponent(escape(atob(b64))));
    document.getElementById('es_id').value         = s.id;
    document.getElementById('es_store_name').value = s.store_name;
    document.getElementById('es_username').value   = s.username;
    document.getElementById('es_fullname').value   = s.fullname;
    document.getElementById('es_tel').value        = s.tel;
    document.getElementById('es_pay_bank').value   = s.pay_bank;
    document.getElementById('es_pay_contax').value = s.pay_contax;
    document.getElementById('es_id_per').value     = s.id_per;
    document.getElementById('es_address').value    = s.address;
    document.getElementById('editSellerModal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('editSellerModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});
</script>
</body>
</html>