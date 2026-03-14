<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT id, fullname FROM admins WHERE id = :id");
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

// แปลงวันที่เป็นปี พ.ศ.
function dateTH(string $format, $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    $year_ad = (int) date('Y', $timestamp);
    $year_be = $year_ad + 543;
    $formatted = date($format, $timestamp);
    return str_replace($year_ad, $year_be, $formatted);
}

// ── ดึงข้อมูลตามประเภท ────────────────────────────────
$data     = [];
$summary  = [];
$columns  = [];
$title_th = '';

switch ($report_type) {

    // ── Orders ─────────────────────────────────────────
    case 'orders':
        $title_th = 'รายงานการเช่า';
        $columns  = ['#','รหัส Order','ผู้เช่า','เบอร์โทร','ร้านค้า','ยอดรวม','สถานะชำระ','สถานะจัดส่ง','เลขพัสดุ','วันที่เช่า'];
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
            'การเช่าทั้งหมด' => number_format($total_orders) . ' รายการ',
            'รายได้รวม'          => '฿' . number_format($total_revenue, 2),
            'เสร็จสิ้นแล้ว'      => number_format($completed_count) . ' รายการ',
        ];
        break;

    // ── Customers ──────────────────────────────────────
    case 'customers':
        $title_th = 'รายงานข้อมูลลูกค้า';
        $columns  = ['#','ชื่อ-นามสกุล','ชื่อผู้ใช้','เบอร์โทร','เลขบัตรประชาชน','จำนวน Orders','ยอดเช่ารวม','วันที่สมัคร'];
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
            'มีประวัติการเช่า' => number_format(count(array_filter($data, function($r){ return $r['order_count'] > 0; }))) . ' คน',
            'ยอดเช่ารวมทุกคน' => '฿' . number_format(array_sum(array_column($data, 'total_spent')), 2),
        ];
        break;

    // ── Stores ─────────────────────────────────────────
    case 'stores':
        $title_th = 'รายงานข้อมูลร้านค้า';
        $columns  = ['#','ชื่อร้าน','เจ้าของ','เบอร์โทร','ช่องทางชำระ','จำนวนพระเครื่อง','จำนวน Orders','รายได้ร้าน','สถานะ'];
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
        $title_th = 'รายงานข้อมูลพระเครื่อง';
        $columns  = ['#','ชื่อพระเครื่อง','หมวดหมู่','ร้านค้า','ราคา','คงเหลือ','ขายแล้ว','รายได้จากพระเครื่อง'];
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
            'พระเครื่องทั้งหมด'  => number_format(count($data)) . ' รายการ',
            'มีพระเครื่องในสต็อก' => number_format($in_stock) . ' รายการ',
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
    <title><?php echo $title_th; ?> - Cenmulet Admin</title>
    <style>
        /* ── Print Styles ── */
        @media print {
            .no-print   { display: none !important; }
            .sidebar    { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .dashboard-container { display: block !important; }
            body { background: #fff !important; font-family: 'Sarabun', sans-serif !important; }
            .card { box-shadow: none !important; border: none !important; }
            .print-header { display: flex !important; }
            .summary-strip { display: none !important; }
            .table-wrapper { overflow: visible !important; }

            /* ── ตาราง: บีบให้พอดีหน้า ── */
            table {
                font-size: 9px !important;
                font-family: 'Sarabun', sans-serif !important;
                border-collapse: collapse !important;
                width: 100% !important;
                table-layout: fixed !important;
                word-break: break-word !important;
            }
            th {
                background: #b8960c !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-size: 9px !important;
            }
            th, td {
                padding: 4px 5px !important;
                border: 1px solid #d1d5db !important;
                vertical-align: middle !important;
                text-align: center !important;
            }
            tr:nth-child(even) td {
                background: #fdf8ee !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-footer { display: block !important; }
            .store-tag, .status-ok, .status-wait, .status-rej {
                border: none !important; background: none !important;
                color: #1a1a1a !important; font-size: 9px !important; padding: 0 !important;
            }
            .card-header { display: none !important; }

            /* A4 landscape ให้มีพื้นที่กว้างขึ้น */
            @page { margin: 10mm 12mm; size: A4 landscape; }
        }
        .print-header {
            display: none;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #c9a227;
        }
        .print-header-logo img { width: 72px; height: 72px; object-fit: contain; }
        .print-header-info { flex: 1; text-align: center; }
        .print-header-info .company-name { font-size: 22px; font-weight: 800; color: #1a1a1a; margin-bottom: 2px; }
        .print-header-info .company-addr { font-size: 12px; color: #444; margin-bottom: 2px; }
        .print-header-info .company-tel  { font-size: 12px; color: #444; margin-bottom: 8px; }
        .print-header-info .report-title-th { font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 2px; }
        .print-date-range { font-size: 13px; color: #444; margin-bottom: 16px; }
        .print-footer {
            display: none;
            text-align: right;
            margin-top: 40px;
            font-size: 12px;
            color: #444;
            line-height: 1.8;
        }

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
            <div class="print-header-logo">
                <img src="data:image/png;base64,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" alt="โลโก้" style="width:80px;height:80px;object-fit:contain;border-radius:50%;mix-blend-mode:multiply;">
            </div>
            <div class="print-header-info">
                <div class="company-name">เซ็นมูเล็ท จำกัด</div>
                <div class="company-addr">เลขที่ 295/55 หมู่ 6 ตำบล บางพูน อำเภอ เมืองปทุมธานี จังหวัด ปทุมธานี 12000</div>
                <div class="company-tel">โทรศัพท์ : 063-756-0774</div>
                <div class="report-title-th"><?php echo $title_th; ?></div>
            </div>
        </div>
        <?php if ($report_type === 'orders'): ?>
        <div class="print-date-range">
            สรุปยอดขายภายในวันที่ <?php echo dateTH('d/m/Y', strtotime($date_from)); ?> – <?php echo dateTH('d/m/Y', strtotime($date_to)); ?>
        </div>
        <?php endif; ?>

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
                'orders'    => ['fa-cart-shopping',  'รายงาน การเช่า'],
                'customers' => ['fa-users',           'ข้อมูลลูกค้า'],
                'stores'    => ['fa-store',           'ข้อมูลร้านค้า'],
                'products'  => ['fa-box',             'ข้อมูลพระเครื่อง'],
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
        <!-- [แก้ไข] เปลี่ยนจาก input type="date" เป็น Flatpickr เพื่อแสดงปี พ.ศ. ได้ -->
        <!-- โครงสร้าง: input[text] แสดงผล พ.ศ. + input[hidden] ส่งค่า ค.ศ. จริงไปยัง DB -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
        <style>
            .flatpickr-input { padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;background:#fff;cursor:pointer; }
            .flatpickr-input:focus { outline:none;border-color:#6366f1; }
        </style>
        <?php if ($report_type === 'orders'): ?>
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px" class="no-print">
            <input type="hidden" name="type" value="orders">
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ตั้งแต่วันที่</label>
                <!-- input text แสดง พ.ศ. ให้ user -->
                <input type="text" id="display_date_from" class="flatpickr-input"
                       value="<?php echo dateTH('d/m/Y', strtotime($date_from)); ?>" readonly>
                <!-- hidden ส่งค่า ค.ศ. จริงไป query DB -->
                <input type="hidden" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ถึงวันที่</label>
                <input type="text" id="display_date_to" class="flatpickr-input"
                       value="<?php echo dateTH('d/m/Y', strtotime($date_to)); ?>" readonly>
                <input type="hidden" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
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
                        <?php echo dateTH('d/m/Y H:i', strtotime($r['created_at'])); ?>
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
                        <?php echo isset($r['created_at']) ? dateTH('d/m/Y',strtotime($r['created_at'])) : '-'; ?>
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

        <!-- Print Footer (แสดงเฉพาะตอนพิมพ์) -->
        <div class="print-footer">
            <div>ผู้พิมพ์รายงาน : <?php echo htmlspecialchars($admin['fullname']); ?></div>
            <div>วันที่พิมพ์ : <?php echo dateTH('d') . ' ' . ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'][date('n')] . ' ' . (date('Y') + 543); ?></div>
            <div>เวลาที่พิมพ์ : <?php echo dateTH('H:i'); ?> น.</div>
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
<!-- [แก้ไข] Flatpickr script — แปลงปีเป็น พ.ศ. ทั้งใน input และ header ปฏิทิน -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
// แปลงวันที่ Date object → string dd/mm/พ.ศ.
function toBEString(d) {
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = d.getFullYear() + 543;
    return dd + '/' + mm + '/' + yy;
}

// แปลงปีใน calendar header (month/year dropdowns) เป็น พ.ศ.
function fixCalendarHeader(fp) {
    const yearEl = fp.calendarContainer.querySelector('.numInput.cur-year');
    if (yearEl) {
        yearEl.value = parseInt(yearEl.value) + 543;
        // ดักการพิมพ์ปีในช่อง year input ของ flatpickr
        yearEl.addEventListener('input', function() {
            if (this.value.length === 4) {
                const adYear = parseInt(this.value) - 543;
                fp.set('maxDate', adYear + '-12-31');
                fp.changeYear(adYear);
            }
        });
    }
}

function initDatePicker(displayId, hiddenId, defaultDate) {
    const fp = flatpickr('#' + displayId, {
        dateFormat: 'Y-m-d',
        defaultDate: defaultDate,
        locale: { firstDayOfWeek: 1 },

        // onReady: ทำงานหลัง Flatpickr render เสร็จ
        // แก้ค่าใน input และ header ให้เป็น พ.ศ. ทันที
        onReady: function(selectedDates, dateStr, fp) {
            if (selectedDates.length > 0) {
                document.getElementById(displayId).value = toBEString(selectedDates[0]);
            }
            fixCalendarHeader(fp);
        },

        // onMonthChange / onYearChange: แก้ header ทุกครั้งที่เปลี่ยนเดือน/ปี
        onMonthChange: function(selectedDates, dateStr, fp) {
            fixCalendarHeader(fp);
        },
        onYearChange: function(selectedDates, dateStr, fp) {
            fixCalendarHeader(fp);
        },

        onChange: function(selectedDates, dateStr, fp) {
            // hidden input รับค่า ค.ศ. ส่งไป DB
            document.getElementById(hiddenId).value = dateStr;
            // text input แสดง พ.ศ.
            if (selectedDates.length > 0) {
                document.getElementById(displayId).value = toBEString(selectedDates[0]);
            }
        }
    });
}

initDatePicker('display_date_from', 'date_from', '<?php echo $date_from; ?>');
initDatePicker('display_date_to',   'date_to',   '<?php echo $date_to; ?>');
</script>
</body>
</html>