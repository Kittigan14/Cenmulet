<?php
/**
 * views/admin/approve_sellers.php
 * Admin ดู รายชื่อ seller ที่รอการอนุมัติ และอนุมัติ/ปฏิเสธ
 */
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// นับ pending
$count_pending  = $db->query("SELECT COUNT(*) FROM sellers WHERE status = 'pending'")->fetchColumn();
$count_approved = $db->query("SELECT COUNT(*) FROM sellers WHERE status = 'approved'")->fetchColumn();
$count_rejected = $db->query("SELECT COUNT(*) FROM sellers WHERE status = 'rejected'")->fetchColumn();

// Filter tab
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE s.status = :status";

try {
    $sql = "SELECT s.* FROM sellers s $where ORDER BY s.id DESC";
    $stmt = $db->prepare($sql);
    if ($filter !== 'all') $stmt->bindValue(':status', $filter);
    $stmt->execute();
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>อนุมัติผู้ขาย - Cenmulet Admin</title>
</head>
<body class="admin">
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar admin">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="">
            <h2>Cenmulet</h2>
            <p>ผู้ดูแลระบบ</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($admin['fullname']); ?></h3>
            <p><i class="fa-solid fa-shield-halved"></i> Admin</p>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-sep">เมนูหลัก</li>
            <li><a href="/views/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/admin/users.php"><i class="fa-solid fa-users"></i> จัดการผู้ใช้</a></li>
            <li><a href="/views/admin/approve_sellers.php" class="active"><i class="fa-solid fa-store"></i> อนุมัติผู้ขาย
                <?php if ($count_pending > 0): ?>
                <span style="background:#ef4444;color:#fff;border-radius:99px;padding:1px 8px;font-size:11px;margin-left:auto"><?php echo $count_pending; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="/views/admin/products.php"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
            <li><a href="/views/admin/categories.php"><i class="fa-solid fa-tags"></i> หมวดหมู่</a></li>
            <li><a href="/views/admin/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
            <li class="menu-sep">ระบบ</li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <div class="top-bar">
            <h1><i class="fa-solid fa-store"></i> อนุมัติผู้ขาย</h1>
        </div>

        <?php if (isset($_GET['done'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo $_GET['done'] === 'approved' ? 'อนุมัติผู้ขายเรียบร้อยแล้ว' : 'ปฏิเสธคำขอเรียบร้อยแล้ว'; ?></span>
        </div>
        <?php endif; ?>

        <!-- Stats mini -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value"><?php echo $count_pending; ?></div>
                    <div class="stat-mini-label">รอการอนุมัติ</div>
                </div>
                <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-clock"></i></div>
            </div>
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value"><?php echo $count_approved; ?></div>
                    <div class="stat-mini-label">อนุมัติแล้ว</div>
                </div>
                <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-circle"></i></div>
            </div>
            <div class="stat-mini">
                <div>
                    <div class="stat-mini-value"><?php echo $count_rejected; ?></div>
                    <div class="stat-mini-label">ปฏิเสธ</div>
                </div>
                <div class="stat-mini-icon" style="background:#fee2e2;color:#ef4444"><i class="fa-solid fa-times-circle"></i></div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <a href="?filter=pending"  class="filter-tab <?php echo $filter==='pending'  ? 'active' : ''; ?>"><i class="fa-solid fa-clock"></i> รอดำเนินการ <span class="tab-count"><?php echo $count_pending; ?></span></a>
            <a href="?filter=approved" class="filter-tab <?php echo $filter==='approved' ? 'active' : ''; ?>"><i class="fa-solid fa-check"></i> อนุมัติแล้ว <span class="tab-count"><?php echo $count_approved; ?></span></a>
            <a href="?filter=rejected" class="filter-tab <?php echo $filter==='rejected' ? 'active' : ''; ?>"><i class="fa-solid fa-times"></i> ปฏิเสธ <span class="tab-count"><?php echo $count_rejected; ?></span></a>
            <a href="?filter=all"      class="filter-tab <?php echo $filter==='all'      ? 'active' : ''; ?>"><i class="fa-solid fa-list"></i> ทั้งหมด</a>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <?php if (count($sellers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ร้านค้า</th>
                            <th>ผู้สมัคร</th>
                            <th>เบอร์โทร</th>
                            <th>บัตรประชาชน</th>
                            <th>เอกสาร</th>
                            <th>สถานะ</th>
                            <th>วันที่สมัคร</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sellers as $s): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?php if ($s['img_store']): ?>
                                    <img src="/uploads/sellers/<?php echo htmlspecialchars($s['img_store']); ?>"
                                         style="width:42px;height:42px;border-radius:8px;object-fit:cover;border:2px solid #e5e7eb">
                                    <?php else: ?>
                                    <div class="no-image"><i class="fa-solid fa-store"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight:600"><?php echo htmlspecialchars($s['store_name']); ?></div>
                                        <div style="font-size:12px;color:#6b7280"><?php echo htmlspecialchars($s['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($s['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($s['tel']); ?></td>
                            <td style="font-size:13px"><?php echo htmlspecialchars($s['id_per']); ?></td>
                            <td>
                                <?php if ($s['img_per']): ?>
                                <a href="/uploads/sellers/<?php echo htmlspecialchars($s['img_per']); ?>"
                                   target="_blank" class="btn btn-sm btn-secondary" style="font-size:12px">
                                    <i class="fa-solid fa-id-card"></i> บัตรประชาชน
                                </a>
                                <?php else: ?>
                                <span style="color:#9ca3af;font-size:13px">ไม่มีเอกสาร</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badges = [
                                    'pending'  => ['badge-warning', 'fa-clock',        'รอการอนุมัติ'],
                                    'approved' => ['badge-success', 'fa-check-circle',  'อนุมัติแล้ว'],
                                    'rejected' => ['badge-danger',  'fa-times-circle',  'ปฏิเสธ'],
                                ];
                                [$cls, $icon, $label] = $badges[$s['status']] ?? ['badge-warning','fa-clock','ไม่ทราบ'];
                                ?>
                                <span class="badge <?php echo $cls; ?>">
                                    <i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $label; ?>
                                </span>
                            </td>
                            <td style="font-size:13px;color:#6b7280">
                                <?php echo '-'; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                <?php if ($s['status'] === 'pending'): ?>
                                    <form action="/views/admin/seller_action.php" method="POST" style="display:inline">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                onclick="return confirm('อนุมัติผู้ขาย <?php echo htmlspecialchars(addslashes($s['store_name'])); ?> ?')">
                                            <i class="fa-solid fa-check"></i> อนุมัติ
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger"
                                            onclick="openRejectModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['store_name'])); ?>')">
                                        <i class="fa-solid fa-times"></i> ปฏิเสธ
                                    </button>
                                <?php elseif ($s['status'] === 'approved'): ?>
                                    <form action="/views/admin/seller_action.php" method="POST" style="display:inline">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="revoke">
                                        <button type="submit" class="btn btn-sm btn-secondary"
                                                onclick="return confirm('ยกเลิกสิทธิ์ผู้ขายนี้?')">
                                            <i class="fa-solid fa-ban"></i> ยกเลิกสิทธิ์
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="/views/admin/seller_action.php" method="POST" style="display:inline">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-info">
                                            <i class="fa-solid fa-rotate-left"></i> อนุมัติใหม่
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
                    <h2>ไม่มีรายการ</h2>
                    <p>ไม่พบผู้ขายในหมวดหมู่นี้</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2)">
        <h3 style="margin-bottom:16px;font-size:18px;display:flex;align-items:center;gap:8px">
            <i class="fa-solid fa-times-circle" style="color:#ef4444"></i>
            ปฏิเสธคำขอสมัคร
        </h3>
        <p style="color:#6b7280;margin-bottom:16px;font-size:14px">ร้าน: <strong id="rejectStoreName"></strong></p>
        <form action="/views/admin/seller_action.php" method="POST">
            <input type="hidden" name="seller_id" id="rejectSellerId">
            <input type="hidden" name="action" value="reject">
            <div style="margin-bottom:16px">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">เหตุผลการปฏิเสธ</label>
                <textarea name="reject_reason" rows="3" placeholder="ระบุเหตุผล..." required
                    style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="closeRejectModal()" class="btn btn-secondary btn-sm">ยกเลิก</button>
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fa-solid fa-times"></i> ยืนยันปฏิเสธ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id, name) {
    document.getElementById('rejectSellerId').value  = id;
    document.getElementById('rejectStoreName').textContent = name;
    const m = document.getElementById('rejectModal');
    m.style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>
</body>
</html>