<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

// แปลงวันที่เป็นปี พ.ศ.
function dateTH(string $format, $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    $year_ad = (int) date('Y', $timestamp);
    $year_be = $year_ad + 543;
    $formatted = date($format, $timestamp);
    return str_replace($year_ad, $year_be, $formatted);
}

try {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $total_users    = $db->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
    $total_sellers  = $db->query("SELECT COUNT(*) as c FROM sellers WHERE status = 'approved'")->fetch()['c'];
    $pending_sellers= $db->query("SELECT COUNT(*) as c FROM sellers WHERE status = 'pending'")->fetch()['c'];
    $total_products = $db->query("SELECT COUNT(*) as c FROM amulets")->fetch()['c'];
    $total_orders   = $db->query("SELECT COUNT(*) as c FROM orders")->fetch()['c'];

    $pending_payments  = $db->query("SELECT COUNT(*) as c FROM payments WHERE status = 'waiting'")->fetch()['c'];
    $confirmed_payments= $db->query("SELECT COUNT(*) as c FROM payments WHERE status = 'confirmed'")->fetch()['c'];
    $pending_delivery  = $db->query("SELECT COUNT(*) as c FROM orders WHERE status = 'pending'")->fetch()['c'];

    $total_revenue = $db->query("
        SELECT COALESCE(SUM(total_price), 0) as t FROM orders WHERE status = 'completed'
    ")->fetch()['t'];

    $today_revenue = $db->query("
        SELECT COALESCE(SUM(total_price), 0) as t FROM orders
        WHERE DATE(created_at) = DATE('now') AND status = 'completed'
    ")->fetch()['t'];

    // รายชื่อ seller ที่รอการอนุมัติ (แสดงใน dashboard)
    $pending_seller_list = $db->query("
        SELECT id, store_name, fullname, tel, img_store, img_per
        FROM sellers WHERE status = 'pending'
        ORDER BY id ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // คำสั่งเช่าล่าสุด
    $recent_orders = $db->query("
        SELECT o.*, u.fullname as buyer, p.status as pay_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        ORDER BY o.created_at DESC LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // สินค้ายอดนิยม
    $top_products = $db->query("
        SELECT a.amulet_name, s.store_name,
               SUM(oi.quantity) as total_sold
        FROM amulets a
        JOIN order_items oi ON a.id = oi.amulet_id
        LEFT JOIN sellers s ON a.sellerId = s.id
        GROUP BY a.id
        ORDER BY total_sold DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>แดชบอร์ด - Cenmulet Admin</title>
</head>
<body class="admin">
<div class="dashboard-container">

<?php include __DIR__ . '/_sidebar.php'; ?>

    <!-- ── Main Content ── -->
    <main class="main-content">
        <div class="top-bar">
            <h1><i class="fa-solid fa-chart-line"></i> แดชบอร์ดภาพรวม</h1>
            <span style="font-size:13px;color:#6b7280">
                <i class="fa-solid fa-calendar"></i>
                <?php echo dateTH('d/m/Y'); ?>
            </span>
        </div>

        <?php if (isset($_GET["done"])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo $_GET["done"] === "approved" ? "อนุมัติร้านค้าเรียบร้อยแล้ว! ร้านค้าสามารถเข้าสู่ระบบได้แล้ว" : "ดำเนินการเรียบร้อยแล้ว"; ?></span>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</span>
        </div>
        <?php endif; ?>

        <!-- ── Pending Sellers Banner ── -->
        <?php if ($pending_sellers > 0): ?>
        <div class="pending-banner">
            <i class="fa-solid fa-store"></i>
            <div style="flex:1">
                <h3>มีผู้สมัครขาย <?php echo $pending_sellers; ?> ราย รอการอนุมัติ</h3>
                <p>กรุณาตรวจสอบข้อมูลและอนุมัติร้านค้าใหม่</p>
            </div>
            <a href="/views/admin/approve_sellers.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-arrow-right"></i> ไปอนุมัติ
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Stats Grid ── -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                    </div>
                    <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_sellers); ?></div>
                        <div class="stat-label">ร้านค้า (อนุมัติแล้ว)</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-store"></i></div>
                </div>
            </div>

            <div class="stat-card" style="cursor:pointer" onclick="location.href='/views/admin/approve_sellers.php?filter=pending'">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value" style="color:<?php echo $pending_sellers > 0 ? '#f59e0b' : '#1a1a1a'; ?>">
                            <?php echo number_format($pending_sellers); ?>
                        </div>
                        <div class="stat-label">รออนุมัติร้านค้า</div>
                    </div>
                    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                </div>
                <?php if ($pending_sellers > 0): ?>
                <div style="margin-top:10px">
                    <a href="/views/admin/approve_sellers.php?filter=pending"
                       class="btn btn-sm btn-primary" style="font-size:12px;padding:5px 12px">
                        <i class="fa-solid fa-check"></i> อนุมัติเลย
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_products); ?></div>
                        <div class="stat-label">พระเครื่องทั้งหมด</div>
                    </div>
                    <div class="stat-icon blue"><i class="fa-solid fa-box"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                        <div class="stat-label">การเช่าทั้งหมด</div>
                    </div>
                    <div class="stat-icon orange"><i class="fa-solid fa-shopping-cart"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($pending_payments); ?></div>
                        <div class="stat-label">รอตรวจสอบชำระ</div>
                    </div>
                    <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value"><?php echo number_format($pending_delivery); ?></div>
                        <div class="stat-label">รอการจัดส่ง</div>
                    </div>
                    <div class="stat-icon pink"><i class="fa-solid fa-truck"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header-row">
                    <div>
                        <div class="stat-value" style="font-size:20px">฿<?php echo number_format($total_revenue, 0); ?></div>
                        <div class="stat-label">รายได้รวมทั้งหมด</div>
                    </div>
                    <div class="stat-icon green"><i class="fa-solid fa-baht-sign"></i></div>
                </div>
            </div>
        </div>

        <!-- ── Pending Sellers Quick Approve (ถ้ามี) ── -->
        <?php if (count($pending_seller_list) > 0): ?>
        <div class="card" style="margin-bottom:24px">
            <div class="card-header">
                <h2><i class="fa-solid fa-hourglass-half"></i> ร้านค้ารอการอนุมัติ</h2>
                <a href="/views/admin/approve_sellers.php" style="font-size:13px;color:var(--admin-primary);text-decoration:none;font-weight:600">
                    ดูทั้งหมด (<?php echo $pending_sellers; ?>) →
                </a>
            </div>
            <div class="card-body" style="padding:0">
                <table>
                    <thead>
                        <tr>
                            <th>ร้านค้า</th>
                            <th>ผู้สมัคร</th>
                            <th>เบอร์โทร</th>
                            <th>เอกสาร</th>
                            <th>วันที่สมัคร</th>
                            <th>อนุมัติ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending_seller_list as $s): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?php if ($s['img_store']): ?>
                                    <img src="/uploads/sellers/<?php echo htmlspecialchars($s['img_store']); ?>"
                                         style="width:38px;height:38px;border-radius:8px;object-fit:cover;border:2px solid #e5e7eb">
                                    <?php else: ?>
                                    <div style="width:38px;height:38px;border-radius:8px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af">
                                        <i class="fa-solid fa-store"></i>
                                    </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($s['store_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($s['fullname']); ?></td>
                            <td style="font-size:13px"><?php echo htmlspecialchars($s['tel']); ?></td>
                            <td>
                                <?php if ($s['img_per']): ?>
                                <a href="/uploads/sellers/<?php echo htmlspecialchars($s['img_per']); ?>"
                                   target="_blank" class="btn btn-sm btn-secondary" style="font-size:11px;padding:4px 10px">
                                    <i class="fa-solid fa-id-card"></i> ดูบัตร
                                </a>
                                <?php else: ?>
                                <span style="color:#d1d5db;font-size:12px">ไม่มีเอกสาร</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:#6b7280">
                                <?php echo '-'; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px">
                                    <form action="/views/admin/seller_action.php" method="POST">
                                        <input type="hidden" name="seller_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="redirect_to" value="/views/admin/dashboard.php">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                style="font-size:12px;padding:5px 12px"
                                                onclick="return confirm('อนุมัติร้าน <?php echo htmlspecialchars(addslashes($s['store_name'])); ?>?')">
                                            <i class="fa-solid fa-check"></i> อนุมัติ
                                        </button>
                                    </form>
                                    <a href="/views/admin/approve_sellers.php?filter=pending"
                                       class="btn btn-sm btn-secondary" style="font-size:12px;padding:5px 12px">
                                        <i class="fa-solid fa-eye"></i> ดู
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Orders + Top Products ── -->
        <div class="content-row">
            <!-- คำสั่งเช่าล่าสุด -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-shopping-cart"></i> การเช่าล่าสุด</h2>
                    <a href="/views/admin/orders.php" style="font-size:13px;color:var(--admin-primary);text-decoration:none;font-weight:600">
                        ดูทั้งหมด →
                    </a>
                </div>
                <div class="card-body" style="padding:0">
                    <?php if (count($recent_orders) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ผู้เช่า</th>
                                <th>ยอดรวม</th>
                                <th>สถานะ</th>
                                <th>วันที่</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_orders as $o): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo htmlspecialchars($o['buyer']); ?></td>
                                <td><strong style="color:#10b981">฿<?php echo number_format($o['total_price'], 2); ?></strong></td>
                                <td>
                                    <?php if ($o['pay_status'] === 'waiting'): ?>
                                        <span class="badge badge-warning">รอตรวจสอบ</span>
                                    <?php elseif ($o['pay_status'] === 'confirmed'): ?>
                                        <span class="badge badge-confirmed">ยืนยันแล้ว</span>
                                    <?php elseif ($o['status'] === 'completed'): ?>
                                        <span class="badge badge-completed">เสร็จสิ้น</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:12px;color:#6b7280"><?php echo dateTH('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state"><i class="fa-solid fa-shopping-cart"></i><p>ยังไม่มีการเช่า</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- สินค้ายอดนิยม -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-fire"></i> พระเครื่องยอดนิยม</h2>
                </div>
                <div class="card-body">
                    <?php if (count($top_products) > 0): ?>
                    <?php $rank = 1; foreach ($top_products as $p): ?>
                    <div class="top-product-item">
                        <div class="top-product-rank rank-<?php echo $rank; ?>"><?php echo $rank++; ?></div>
                        <div>
                            <div class="top-product-name"><?php echo htmlspecialchars($p['amulet_name']); ?></div>
                            <div class="top-product-store"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($p['store_name'] ?? 'ไม่ระบุ'); ?></div>
                            <div class="top-product-stats"><i class="fa-solid fa-shopping-bag"></i> ขายแล้ว <?php echo number_format($p['total_sold']); ?> ชิ้น</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state"><i class="fa-solid fa-box"></i><p>ยังไม่มีข้อมูล</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>
</body>
</html>