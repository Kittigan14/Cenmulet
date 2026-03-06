<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

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
                    <th>เบอร์โทร</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>ช่องทางชำระเงิน</th>
                    <th>ที่อยู่</th>
                    <th>เอกสาร</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sellers as $s): ?>
            <tr>
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
</body>
</html>