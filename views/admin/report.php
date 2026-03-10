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
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();

// ── ประเภทรายงานที่เลือก ───────────────────────────────
$report_type = $_GET['type'] ?? 'orders';
$allowed_types = ['orders','customers','stores','products'];
if (!in_array($report_type, $allowed_types)) $report_type = 'orders';

// ── ช่วงวันที่ ─────────────────────────────────────────
$date_from = $_GET['date_from'] ?? date('Y-m-01');         // ต้นเดือนปัจจุบัน
$date_to   = $_GET['date_to']   ?? date('Y-m-d');          // วันนี้

// ── ดึงข้อมูลตามประเภท ────────────────────────────────
$data     = [];
$summary  = [];
$columns  = [];
$title_th = '';

switch ($report_type) {

    // ── Orders ─────────────────────────────────────────
    case 'orders':
        $title_th = 'รายงานคำสั่งซื้อ';
        $columns  = ['#','รหัส Order','ผู้ซื้อ','เบอร์โทร','ร้านค้า','ยอดรวม','สถานะชำระ','สถานะจัดส่ง','เลขพัสดุ','วันที่สั่ง'];
        $rows = $db->prepare("
            SELECT o.id,
                   u.fullname as buyer, u.tel,
                   GROUP_CONCAT(DISTINCT s.store_name) as stores,
                   o.total_price,
                   p.status as pay_status,
                   o.status as order_status,
                   o.tracking_number,
                   o.created_at
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN amulets a ON oi.amulet_id = a.id
            LEFT JOIN sellers s ON a.sellerId = s.id
            WHERE DATE(o.created_at) BETWEEN :f AND :t
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $rows->execute([':f' => $date_from, ':t' => $date_to]);
        $data = $rows->fetchAll(PDO::FETCH_ASSOC);

        // summary
        $total_orders    = count($data);
        $total_revenue   = array_sum(array_column($data, 'total_price'));
        $completed_count = 0;
        foreach ($data as $_r) { if ($_r['order_status'] === 'completed') $completed_count++; }
        $summary = [
            'คำสั่งซื้อทั้งหมด' => number_format($total_orders) . ' รายการ',
            'รายได้รวม'          => '฿' . number_format($total_revenue, 2),
            'เสร็จสิ้นแล้ว'      => number_format($completed_count) . ' รายการ',
        ];
        break;

    // ── Customers ──────────────────────────────────────
    case 'customers':
        $title_th = 'รายงานข้อมูลลูกค้า';
        $columns  = ['#','ชื่อ-นามสกุล','ชื่อผู้ใช้','เบอร์โทร','เลขบัตรประชาชน','จำนวน Orders','ยอดซื้อรวม','วันที่สมัคร'];
        $rows = $db->query("
            SELECT u.*,
                   COUNT(DISTINCT o.id) as order_count,
                   COALESCE(SUM(o.total_price),0) as total_spent
            FROM users u
            LEFT JOIN orders o ON u.id = o.user_id
            GROUP BY u.id
            ORDER BY total_spent DESC
        ");
        $data = $rows->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'ลูกค้าทั้งหมด' => number_format(count($data)) . ' คน',
            'มีประวัติสั่งซื้อ' => number_format(count(array_filter($data, function($r){ return $r['order_count'] > 0; }))) . ' คน',
            'ยอดซื้อรวมทุกคน' => '฿' . number_format(array_sum(array_column($data, 'total_spent')), 2),
        ];
        break;

    // ── Stores ─────────────────────────────────────────
    case 'stores':
        $title_th = 'รายงานข้อมูลร้านค้า';
        $columns  = ['#','ชื่อร้าน','เจ้าของ','เบอร์โทร','ช่องทางชำระ','จำนวนสินค้า','จำนวน Orders','รายได้ร้าน','สถานะ'];
        $rows = $db->query("
            SELECT s.*,
                   COUNT(DISTINCT a.id)  as product_count,
                   COUNT(DISTINCT o.id)  as order_count,
                   COALESCE(SUM(oi.quantity * oi.price),0) as revenue
            FROM sellers s
            LEFT JOIN amulets a ON s.id = a.sellerId
            LEFT JOIN order_items oi ON a.id = oi.amulet_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
            GROUP BY s.id
            ORDER BY revenue DESC
        ");
        $data = $rows->fetchAll(PDO::FETCH_ASSOC);

        $approved = 0; foreach ($data as $_r) { if ($_r['status'] === 'approved') $approved++; }
        $summary = [
            'ร้านค้าทั้งหมด'      => number_format(count($data)) . ' ร้าน',
            'อนุมัติแล้ว'         => number_format($approved) . ' ร้าน',
            'รายได้รวมทุกร้าน'    => '฿' . number_format(array_sum(array_column($data, 'revenue')), 2),
        ];
        break;

    // ── Products ───────────────────────────────────────
    case 'products':
        $title_th = 'รายงานข้อมูลสินค้า';
        $columns  = ['#','ชื่อสินค้า','หมวดหมู่','ร้านค้า','ราคา','คงเหลือ','ขายแล้ว','รายได้จากสินค้า'];
        $rows = $db->query("
            SELECT a.*,
                   c.category_name,
                   s.store_name,
                   COALESCE(SUM(oi.quantity),0) as total_sold,
                   COALESCE(SUM(oi.quantity * oi.price),0) as revenue
            FROM amulets a
            LEFT JOIN categories c ON a.categoryId = c.id
            LEFT JOIN sellers s ON a.sellerId = s.id
            LEFT JOIN order_items oi ON a.id = oi.amulet_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
            GROUP BY a.id
            ORDER BY total_sold DESC
        ");
        $data = $rows->fetchAll(PDO::FETCH_ASSOC);

        $in_stock = 0; foreach ($data as $_r) { if ($_r['quantity'] > 0) $in_stock++; }
        $summary = [
            'สินค้าทั้งหมด'  => number_format(count($data)) . ' รายการ',
            'มีสินค้าในสต็อก' => number_format($in_stock) . ' รายการ',
            'รายได้รวม'      => '฿' . number_format(array_sum(array_column($data, 'revenue')), 2),
        ];
        break;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
    <title><?php echo $title_th; ?> - Cenmulet Admin</title>
    <style>
        /* ── Print Styles ── */
        @media print {
            .no-print   { display: none !important; }
            .sidebar    { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .dashboard-container { display: block !important; }
            body { background: #fff !important; font-family: 'Sarabun', sans-serif !important; }
            .card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
            .print-header { display: block !important; }
            table { font-size: 11px !important; font-family: 'Sarabun', sans-serif !important; }
            th, td { padding: 6px 8px !important; }
            .badge { border: 1px solid currentColor !important; }
            @page { margin: 15mm; size: A4 landscape; }
        }
        body { font-family: 'Sarabun', sans-serif; }
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e5e7eb;
        }
        .print-header h1 { font-size: 20px; color: #1a1a1a; margin-bottom: 4px; }
        .print-header p  { font-size: 12px; color: #6b7280; }

        .report-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .report-tab {
            padding: 9px 18px; border-radius: 8px; font-size: 14px;
            font-family: inherit; font-weight: 600; cursor: pointer;
            border: 2px solid #e5e7eb; background: #fff; color: #6b7280;
            text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
            transition: all .18s;
        }
        .report-tab:hover  { border-color: #6366f1; color: #6366f1; }
        .report-tab.active { border-color: #6366f1; background: #6366f1; color: #fff; }

        .summary-strip {
            display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .summary-card {
            flex: 1; min-width: 160px; background: #fff;
            border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 14px 18px; text-align: center;
        }
        .summary-card .s-val { font-size: 20px; font-weight: 800; color: #6366f1; margin-bottom: 3px; }
        .summary-card .s-lbl { font-size: 12px; color: #9ca3af; }

        .store-tag {
            display: inline-flex; align-items: center; gap: 3px;
            background: #ede9fe; color: #6d28d9;
            padding: 2px 7px; border-radius: 99px; font-size: 11px; font-weight: 600;
        }

        .pay-badge   { font-size: 11px; padding: 2px 8px; }
        .status-ok   { background: #d1fae5; color: #059669; border-radius: 99px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
        .status-wait { background: #fef3c7; color: #d97706; border-radius: 99px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
        .status-rej  { background: #fee2e2; color: #dc2626; border-radius: 99px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body class="admin">
<div class="dashboard-container">
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <main class="main-content">

        <!-- Print Header (แสดงเฉพาะตอนพิมพ์) -->
        <div class="print-header">
            <h1>Cenmulet — <?php echo $title_th; ?></h1>
            <p>
                พิมพ์โดย: <?php echo htmlspecialchars($admin['fullname']); ?> &nbsp;|&nbsp;
                <?php if ($report_type === 'orders'): ?>
                ช่วงวันที่: <?php echo date('d/m/Y', strtotime($date_from)); ?> – <?php echo date('d/m/Y', strtotime($date_to)); ?> &nbsp;|&nbsp;
                <?php endif; ?>
                พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?>
            </p>
        </div>

        <!-- Top bar -->
        <div class="top-bar no-print">
            <h1><i class="fa-solid fa-print"></i> พิมพ์รายงาน</h1>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึก PDF
            </button>
        </div>

        <!-- Report type tabs -->
        <div class="report-tabs no-print">
            <?php
            $tabs = [
                'orders'    => ['fa-cart-shopping',  'รายงาน Orders'],
                'customers' => ['fa-users',           'ข้อมูลลูกค้า'],
                'stores'    => ['fa-store',           'ข้อมูลร้านค้า'],
                'products'  => ['fa-box',             'ข้อมูลสินค้า'],
            ];
            foreach ($tabs as $key => [$icon, $label]):
                $q = $key === 'orders'
                    ? "?type=$key&date_from=$date_from&date_to=$date_to"
                    : "?type=$key";
            ?>
            <a href="<?php echo $q; ?>"
               class="report-tab <?php echo $report_type === $key ? 'active' : ''; ?>">
                <i class="fa-solid <?php echo $icon; ?>"></i>
                <?php echo $label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Date filter (Orders เท่านั้น) -->
        <?php if ($report_type === 'orders'): ?>
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px" class="no-print">
            <input type="hidden" name="type" value="orders">
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ตั้งแต่วันที่</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                       style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ถึงวันที่</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                       style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-filter"></i> กรอง
            </button>
        </form>
        <?php endif; ?>

        <!-- Summary Cards -->
        <?php if (!empty($summary)): ?>
        <div class="summary-strip">
            <?php foreach ($summary as $lbl => $val): ?>
            <div class="summary-card">
                <div class="s-val"><?php echo $val; ?></div>
                <div class="s-lbl"><?php echo $lbl; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Report Table -->
        <div class="card">
            <div class="card-header no-print">
                <h2><i class="fa-solid fa-table"></i> <?php echo $title_th; ?> (<?php echo count($data); ?> รายการ)</h2>
            </div>
            <div class="table-wrapper">
            <?php if (count($data) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                        <th><?php echo $col; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>

                <?php if ($report_type === 'orders'):
                    foreach ($data as $i => $r):
                        $stores = array_filter(explode(',', $r['stores'] ?? ''));
                ?>
                <tr>
                    <td style="color:#9ca3af;font-size:12px"><?php echo $i+1; ?></td>
                    <td><strong>#<?php echo str_pad($r['id'],6,'0',STR_PAD_LEFT); ?></strong></td>
                    <td>
                        <div style="font-weight:500"><?php echo htmlspecialchars($r['buyer']); ?></div>
                    </td>
                    <td style="font-size:12px"><?php echo htmlspecialchars($r['tel']); ?></td>
                    <td>
                        <?php foreach ($stores as $st): ?>
                        <span class="store-tag"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars(trim($st)); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($r['total_price'],2); ?></strong></td>
                    <td>
                        <?php
                        $p = $r['pay_status'];
                        if ($p==='confirmed')     echo '<span class="status-ok"><i class="fa-solid fa-check"></i> ยืนยันแล้ว</span>';
                        elseif ($p==='waiting')   echo '<span class="status-wait"><i class="fa-solid fa-clock"></i> รอยืนยัน</span>';
                        elseif ($p==='rejected')  echo '<span class="status-rej"><i class="fa-solid fa-times"></i> ปฏิเสธ</span>';
                        else echo '<span style="color:#d1d5db">-</span>';
                        ?>
                    </td>
                    <td>
                        <?php if ($r['order_status']==='completed'):   echo '<span class="status-ok"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>';
                        elseif ($p==='confirmed'):                      echo '<span class="badge badge-info" style="font-size:11px"><i class="fa-solid fa-truck"></i> กำลังส่ง</span>';
                        else:                                           echo '<span class="status-wait"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>'; endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:12px">
                        <?php echo $r['tracking_number'] ? htmlspecialchars($r['tracking_number']) : '<span style="color:#d1d5db">-</span>'; ?>
                    </td>
                    <td style="font-size:12px;white-space:nowrap">
                        <?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>

                <?php if ($report_type === 'customers'):
                    foreach ($data as $i => $r): ?>
                <tr>
                    <td style="color:#9ca3af;font-size:12px"><?php echo $i+1; ?></td>
                    <td><strong><?php echo htmlspecialchars($r['fullname']); ?></strong></td>
                    <td style="color:#6b7280;font-size:12px"><?php echo htmlspecialchars($r['username']); ?></td>
                    <td><?php echo htmlspecialchars($r['tel']); ?></td>
                    <td style="font-size:12px;letter-spacing:.5px"><?php echo htmlspecialchars($r['id_per'] ?? '-'); ?></td>
                    <td style="text-align:center;font-weight:600"><?php echo number_format($r['order_count']); ?></td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($r['total_spent'],2); ?></strong></td>
                    <td style="font-size:12px;color:#6b7280">
                        <?php echo isset($r['created_at']) ? date('d/m/Y',strtotime($r['created_at'])) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>

                <?php if ($report_type === 'stores'):
                    $status_map = [
                        'approved' => '<span class="status-ok">อนุมัติแล้ว</span>',
                        'pending'  => '<span class="status-wait">รออนุมัติ</span>',
                        'rejected' => '<span class="status-rej">ปฏิเสธ</span>',
                    ];
                    foreach ($data as $i => $r): ?>
                <tr>
                    <td style="color:#9ca3af;font-size:12px"><?php echo $i+1; ?></td>
                    <td><strong><?php echo htmlspecialchars($r['store_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['fullname']); ?></td>
                    <td style="font-size:12px"><?php echo htmlspecialchars($r['tel']); ?></td>
                    <td style="font-size:12px"><?php echo htmlspecialchars($r['pay_contax'] ?? '-'); ?></td>
                    <td style="text-align:center;font-weight:600"><?php echo number_format($r['product_count']); ?></td>
                    <td style="text-align:center;font-weight:600"><?php echo number_format($r['order_count']); ?></td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($r['revenue'],2); ?></strong></td>
                    <td><?php echo $status_map[$r['status']] ?? '-'; ?></td>
                </tr>
                <?php endforeach; endif; ?>

                <?php if ($report_type === 'products'):
                    foreach ($data as $i => $r): ?>
                <tr>
                    <td style="color:#9ca3af;font-size:12px"><?php echo $i+1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($r['amulet_name']); ?></strong>
                    </td>
                    <td><span class="store-tag" style="background:#e0e7ff;color:#6366f1"><?php echo htmlspecialchars($r['category_name'] ?? '-'); ?></span></td>
                    <td><span class="store-tag"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($r['store_name'] ?? '-'); ?></span></td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($r['price'],2); ?></strong></td>
                    <td style="font-weight:600;color:<?php echo $r['quantity']>0?'#1a1a1a':'#ef4444'; ?>">
                        <?php echo number_format($r['quantity']); ?>
                    </td>
                    <td style="text-align:center;font-weight:600"><?php echo number_format($r['total_sold']); ?></td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($r['revenue'],2); ?></strong></td>
                </tr>
                <?php endforeach; endif; ?>

                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-file-circle-xmark"></i>
                <h2>ไม่มีข้อมูลในช่วงที่เลือก</h2>
            </div>
            <?php endif; ?>
            </div>
        </div>

        <div style="text-align:center;margin-top:20px" class="no-print">
            <button onclick="window.print()" class="btn btn-primary" style="padding:12px 32px;font-size:15px">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึกเป็น PDF
            </button>
            <a href="/views/admin/dashboard.php" class="btn btn-secondary" style="padding:12px 24px;font-size:15px;margin-left:10px">
                <i class="fa-solid fa-arrow-left"></i> กลับ
            </a>
        </div>

    </main>
</div>
</body>
</html>