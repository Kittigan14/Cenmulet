<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php"); exit;
}

$seller_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT id, fullname, store_name, pay_contax, address, tel FROM sellers WHERE id = :id");
$stmt->execute([':id' => $seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

// [แก้ไข] เพิ่ม function แปลงวันที่ ค.ศ. → พ.ศ.
function dateTH(string $format, $timestamp = null): string {
    if ($timestamp === null) $timestamp = time();
    $year_ad = (int) date('Y', $timestamp);
    $year_be = $year_ad + 543;
    $formatted = date($format, $timestamp);
    return str_replace($year_ad, $year_be, $formatted);
}

// ── ประเภทรายงาน: sales / products ────────────────────
$view = $_GET['view'] ?? 'sales';
if (!in_array($view, ['sales', 'products'])) $view = 'sales';

// ════════════════════════════════════════════════════════
//  VIEW: PRODUCTS — พระเครื่องทั้งหมดของ seller คนนี้
// ════════════════════════════════════════════════════════
if ($view === 'products') {

    $prod_stmt = $db->prepare("
        SELECT
            a.id,
            a.amulet_name,
            a.source,
            a.price,
            a.quantity,
            c.category_name,
            COALESCE(
                (SELECT ai.image FROM amulet_images ai
                 WHERE ai.amulet_id = a.id
                 ORDER BY ai.sort_order ASC LIMIT 1),
                a.image
            ) AS main_image,
            COALESCE(SUM(oi.quantity), 0)              AS total_sold,
            COALESCE(SUM(oi.quantity * oi.price), 0)   AS revenue
        FROM amulets a
        LEFT JOIN categories c   ON a.categoryId  = c.id
        LEFT JOIN order_items oi ON a.id = oi.amulet_id
        LEFT JOIN orders o       ON oi.order_id = o.id AND o.status = 'completed'
        WHERE a.sellerId = :sid
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    $prod_stmt->execute([':sid' => $seller_id]);
    $products = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_products = count($products);
    $in_stock       = count(array_filter($products, function($p) { return $p['quantity'] > 0; }));
    $total_sold_all = array_sum(array_column($products, 'total_sold'));
    $total_revenue  = array_sum(array_column($products, 'revenue'));

} else {
    // ════════════════════════════════════════════════════
    //  VIEW: SALES — รายงานการปล่อยเช่า (โค้ดเดิม)
    // ════════════════════════════════════════════════════

    $period = $_GET['period'] ?? 'monthly';
    $year   = (int)($_GET['year']  ?? date('Y'));
    $month  = (int)($_GET['month'] ?? date('n'));
    $day    = $_GET['day']         ?? date('Y-m-d');

    $allowed_periods = ['daily','monthly','yearly'];
    if (!in_array($period, $allowed_periods)) $period = 'monthly';

    switch ($period) {
        case 'daily':
            // [แก้ไข] date() → dateTH() แสดงปี พ.ศ. ใน label
            $date_label = dateTH('d เดือน m ปี Y', strtotime($day));
            $where_time = "AND DATE(o.created_at) = '$day'";
            $group_by   = "strftime('%H', o.created_at)";
            $x_label    = "ชั่วโมง";
            break;
        case 'monthly':
            // [แก้ไข] date() → dateTH() แสดงปี พ.ศ. ใน label + dropdown ปี
            $date_label = "เดือน " . dateTH('F Y', mktime(0,0,0,$month,1,$year));
            $where_time = "AND strftime('%Y-%m', o.created_at) = '" . sprintf('%04d-%02d',$year,$month) . "'";
            $group_by   = "strftime('%d', o.created_at)";
            $x_label    = "วัน";
            break;
        case 'yearly':
            // [แก้ไข] แสดงปี พ.ศ.
            $date_label = "ปี " . ($year + 543);
            $where_time = "AND strftime('%Y', o.created_at) = '$year'";
            $group_by   = "strftime('%m', o.created_at)";
            $x_label    = "เดือน";
            break;
    }

    $summary_stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT o.id)          as order_count,
            COALESCE(SUM(oi.quantity),0)  as total_qty,
            COALESCE(SUM(oi.quantity * oi.price),0) as total_revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE a.sellerId = :sid
          AND o.status = 'completed'
          $where_time
    ");
    $summary_stmt->execute([':sid' => $seller_id]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    $chart_stmt = $db->prepare("
        SELECT $group_by as period_key,
               COUNT(DISTINCT o.id) as orders,
               COALESCE(SUM(oi.quantity * oi.price),0) as revenue
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE a.sellerId = :sid
          AND o.status = 'completed'
          $where_time
        GROUP BY period_key
        ORDER BY period_key ASC
    ");
    $chart_stmt->execute([':sid' => $seller_id]);
    $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders_stmt = $db->prepare("
        SELECT DISTINCT
            o.id, o.total_price, o.status, o.created_at,
            o.tracking_number,
            u.fullname as buyer_name, u.tel as buyer_tel,
            p.status as pay_status,
            COUNT(DISTINCT oi.id) as item_count,
            COALESCE(SUM(oi.quantity * oi.price),0) as seller_total
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE a.sellerId = :sid
          $where_time
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $orders_stmt->execute([':sid' => $seller_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    $top_stmt = $db->prepare("
        SELECT a.amulet_name, a.image,
               SUM(oi.quantity) as qty_sold,
               SUM(oi.quantity * oi.price) as revenue
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN orders o ON oi.order_id = o.id
        WHERE a.sellerId = :sid
          AND o.status = 'completed'
          $where_time
        GROUP BY a.id
        ORDER BY qty_sold DESC
        LIMIT 5
    ");
    $top_stmt->execute([':sid' => $seller_id]);
    $top_products = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

    // [แก้ไข] $years เก็บค่า ค.ศ. จริง (ใช้ query) แต่แสดง พ.ศ. ใน dropdown
    $years     = range(date('Y'), date('Y') - 4);
    $months_th = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                  'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- [แก้ไข] Flatpickr CSS สำหรับ date picker แสดง พ.ศ. -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <title>รายงานการปล่อยเช่า - <?php echo htmlspecialchars($seller['store_name']); ?></title>
    <style>
        /* ── Print ── */
        @media print {
            .no-print    { display: none !important; }
            .sidebar     { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .dashboard-container { display: block !important; }
            body { background: #fff !important; font-family: 'Sarabun', sans-serif !important; }
            .card { box-shadow: none !important; border: none !important; }
            .print-header { display: flex !important; }
            .table-wrapper { overflow: visible !important; }
            .summary-row { display: none !important; }
            .chart-wrap  { display: none !important; }
            canvas       { display: none !important; }
            .products-grid  { display: none !important; }
            .products-print {
                display: table !important; width: 100% !important;
                border-collapse: collapse !important; font-size: 9px !important;
                table-layout: fixed !important; word-break: break-word !important;
            }
            .products-print th {
                background: #b8960c !important; color: #fff !important;
                font-weight: 700 !important; padding: 5px 6px !important;
                text-align: center !important; border: 1px solid #6b4f0a !important;
                font-size: 9px !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            .products-print td {
                padding: 5px 6px !important; border: 1px solid #d1d5db !important;
                vertical-align: middle !important; text-align: center !important; font-size: 9px !important;
            }
            .products-print tr:nth-child(even) td {
                background: #fdf8ee !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            .products-print tfoot td {
                background: #f3f4f6 !important; font-weight: 700 !important;
                border: 1px solid #d1d5db !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            table:not(.products-print) {
                font-size: 9px !important; border-collapse: collapse !important;
                width: 100% !important; table-layout: fixed !important;
            }
            table:not(.products-print) th {
                background: #b8960c !important; color: #fff !important;
                padding: 5px 6px !important; text-align: center !important;
                border: 1px solid #6b4f0a !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            table:not(.products-print) td {
                padding: 5px 6px !important; border: 1px solid #d1d5db !important;
                vertical-align: middle !important; text-align: center !important;
            }
            table:not(.products-print) tr:nth-child(even) td {
                background: #fdf8ee !important;
                -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
            }
            .print-footer { display: block !important; }
            @page { margin: 10mm 12mm; size: A4 landscape; }
        }
        .print-header {
            display: none; align-items: center; gap: 18px;
            margin-bottom: 10px; padding-bottom: 10px;
            border-bottom: 2.5px solid #8B6914; font-family: 'Sarabun', sans-serif;
        }
        .ph-logo    { width: 72px; height: 72px; object-fit: contain; border-radius: 50%; mix-blend-mode: multiply; flex-shrink: 0; }
        .ph-info    { flex: 1; text-align: center; line-height: 1.55; }
        .ph-company { font-size: 18pt; font-weight: 800; color: #1a1a1a; }
        .ph-addr    { font-size: 9pt; color: #333; }
        .ph-tel     { font-size: 9pt; color: #333; }
        .ph-title   { font-size: 13pt; font-weight: 700; color: #1a1a1a; margin-top: 5px; }
        .ph-sub     { font-size: 9pt; color: #444; margin-top: 2px; }
        .print-footer {
            display: none; text-align: right; margin-top: 36px;
            font-size: 9pt; color: #333; line-height: 2; font-family: 'Sarabun', sans-serif;
        }

        /* ── View Tabs (sales / products) ── */
        .view-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .view-tab {
            padding: 9px 20px; border-radius: 8px; font-size: 14px;
            font-family: inherit; font-weight: 600; cursor: pointer;
            border: 2px solid #e5e7eb; background: #fff; color: #6b7280;
            text-decoration: none; transition: all .18s;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .view-tab:hover  { border-color: #10b981; color: #10b981; }
        .view-tab.active { border-color: #10b981; background: #10b981; color: #fff; }

        /* ── Period Tabs ── */
        .period-tabs { display: flex; gap: 6px; margin-bottom: 18px; }
        .period-tab {
            padding: 8px 18px; border-radius: 8px; font-size: 14px;
            font-family: inherit; font-weight: 600; cursor: pointer;
            border: 2px solid #e5e7eb; background: #fff; color: #6b7280;
            text-decoration: none; transition: all .18s;
        }
        .period-tab:hover  { border-color: #10b981; color: #10b981; }
        .period-tab.active { border-color: #10b981; background: #10b981; color: #fff; }

        /* ── Summary ── */
        .summary-row { display: flex; gap: 14px; margin-bottom: 22px; }
        .summary-box {
            flex: 1; background: #fff; border: 1px solid #e5e7eb;
            border-radius: 14px; padding: 18px 20px; text-align: center;
        }
        .summary-box .s-val { font-size: 24px; font-weight: 800; color: #10b981; }
        .summary-box .s-lbl { font-size: 12px; color: #9ca3af; margin-top: 3px; }

        /* ── Chart container ── */
        .chart-wrap {
            background: #fff; border-radius: 14px; border: 1px solid #e5e7eb;
            padding: 20px; margin-bottom: 22px;
        }
        .chart-wrap h3 { font-size: 14px; font-weight: 700; margin-bottom: 14px; color: #374151; }

        /* ── Top products ── */
        .top-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid #f3f4f6;
        }
        .top-item:last-child { border-bottom: none; }
        .top-rank {
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800; flex-shrink: 0;
        }
        .rank-1 { background: #fef9c3; color: #ca8a04; }
        .rank-2 { background: #f1f5f9; color: #475569; }
        .rank-3 { background: #fff7ed; color: #c2410c; }
        .rank-other { background: #f3f4f6; color: #9ca3af; }

        /* ── Order status badges ── */
        .st-ok   { background:#d1fae5;color:#059669;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }
        .st-ship { background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }
        .st-wait { background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }

        /* ── Products Grid (screen) ── */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .prod-card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 14px; overflow: hidden;
            transition: box-shadow .2s;
        }
        .prod-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.08); }
        .prod-img {
            width: 100%; aspect-ratio: 1/1; object-fit: cover; background: #f3f4f6;
        }
        .prod-img-ph {
            width: 100%; aspect-ratio: 1/1;
            display: flex; align-items: center; justify-content: center;
            background: #f3f4f6; color: #d1d5db; font-size: 40px;
        }
        .prod-body { padding: 12px 14px 14px; }
        .prod-name {
            font-weight: 700; font-size: 13px; color: #111827;
            margin-bottom: 3px; line-height: 1.4;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }
        .prod-source {
            font-size: 11px; color: #9ca3af; margin-bottom: 10px;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }
        .prod-meta {
            display: flex; justify-content: space-between;
            align-items: center; gap: 6px; flex-wrap: wrap;
        }
        .prod-price { font-size: 15px; font-weight: 800; color: #10b981; }
        .qty-ok   { background:#d1fae5;color:#059669;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }
        .qty-none { background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600; }
        .cat-tag  {
            display: inline-flex; align-items: center; gap: 3px;
            background: #ede9fe; color: #6d28d9;
            padding: 2px 8px; border-radius: 99px; font-size: 11px;
            font-weight: 600; margin-bottom: 8px;
        }
        .sold-info {
            display: flex; justify-content: space-between;
            font-size: 11px; color: #6b7280; margin-top: 8px;
        }

        /* ── Print table (hidden on screen) ── */
        .products-print { display: none; }
        .badge-cat  { background:#ede9fe;color:#6d28d9;border-radius:99px;padding:1px 8px;font-size:10px;font-weight:600; }
        .badge-ok   { background:#d1fae5;color:#059669;border-radius:99px;padding:1px 8px;font-size:10px;font-weight:600; }
        .badge-none { background:#fee2e2;color:#dc2626;border-radius:99px;padding:1px 8px;font-size:10px;font-weight:600; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="">
            <h2>Cenmulet</h2>
            <p>แดชบอร์ดผู้ปล่อยเช่า</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
            <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
            <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่า</a></li>
            <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
            <li><a href="/views/seller/report.php" class="active"><i class="fa-solid fa-chart-bar"></i> รายงานการปล่อยเช่า</a></li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <!-- ════ PRINT HEADER ════ -->
        <div class="print-header">
            <img class="ph-logo" src="data:image/png;base64,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" alt="logo">
            <div class="ph-info">
                <div class="ph-company"><?php echo htmlspecialchars($seller['store_name']); ?></div>
                <div class="ph-addr"><?php echo htmlspecialchars($seller['address'] ?? ''); ?></div>
                <div class="ph-tel">โทรศัพท์ : <?php echo htmlspecialchars($seller['tel'] ?? ''); ?></div>
                <?php if ($view === 'products'): ?>
                <div class="ph-title">รายการพระเครื่องทั้งหมด</div>
                <?php else: ?>
                <div class="ph-title">รายงานสรุปการปล่อยเช่า</div>
                <div class="ph-sub">ช่วงเวลา: <?php echo $date_label; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ════ TOP BAR ════ -->
        <div class="top-bar no-print">
            <h1><i class="fa-solid fa-chart-bar"></i> รายงานการปล่อยเช่า</h1>
            <div style="display:flex;gap:8px">
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-print"></i> พิมพ์ / PDF
                </button>
                <a href="/views/seller/orders.php" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> กลับ
                </a>
            </div>
        </div>

        <!-- ════ VIEW TABS (sales / products) ════ -->
        <div class="view-tabs no-print">
            <a href="?view=sales"
               class="view-tab <?php echo $view === 'sales' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-bar"></i> รายงานการปล่อยเช่า
            </a>
            <a href="?view=products"
               class="view-tab <?php echo $view === 'products' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> ข้อมูลพระเครื่อง
            </a>
        </div>


        <?php if ($view === 'products'): ?>
        <!-- ════════════════════════════════════════════
             TAB: ข้อมูลพระเครื่อง
        ════════════════════════════════════════════ -->

        <!-- Summary -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($total_products); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-box"></i> พระเครื่องทั้งหมด</div>
            </div>
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($in_stock); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-cubes"></i> มีพระเครื่องในสต็อก</div>
            </div>
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($total_sold_all); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-cart-check"></i> ปล่อยเช่าแล้วทั้งหมด</div>
            </div>
            <div class="summary-box">
                <div class="s-val" style="font-size:20px">฿<?php echo number_format($total_revenue, 2); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-baht-sign"></i> รายได้รวม</div>
            </div>
        </div>

        <?php if (count($products) > 0): ?>

        <!-- SCREEN: Grid -->
        <div class="products-grid no-print">
            <?php foreach ($products as $p): ?>
            <div class="prod-card">
                <?php if (!empty($p['main_image'])): ?>
                    <img class="prod-img"
                         src="/uploads/amulets/<?php echo htmlspecialchars($p['main_image']); ?>"
                         alt="<?php echo htmlspecialchars($p['amulet_name']); ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="prod-img-ph" style="display:none"><i class="fa-solid fa-image"></i></div>
                <?php else: ?>
                    <div class="prod-img-ph"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>

                <div class="prod-body">
                    <?php if (!empty($p['category_name'])): ?>
                    <span class="cat-tag"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($p['category_name']); ?></span>
                    <?php endif; ?>
                    <div class="prod-name"><?php echo htmlspecialchars($p['amulet_name']); ?></div>
                    <div class="prod-source"><?php echo htmlspecialchars($p['source'] ?? ''); ?></div>
                    <div class="prod-meta">
                        <span class="prod-price">฿<?php echo number_format($p['price'], 2); ?></span>
                        <span class="<?php echo $p['quantity'] > 0 ? 'qty-ok' : 'qty-none'; ?>">
                            <?php echo $p['quantity'] > 0 ? 'คงเหลือ ' . number_format($p['quantity']) : 'หมด'; ?>
                        </span>
                    </div>
                    <div class="sold-info">
                        <span><i class="fa-solid fa-cart-check"></i> ปล่อยเช่า <?php echo number_format($p['total_sold']); ?> ชิ้น</span>
                        <span style="color:#10b981;font-weight:600">฿<?php echo number_format($p['revenue'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PRINT: Clean table (admin-style) -->
        <table class="products-print">
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:8%">รหัส</th>
                    <th style="width:30%">ชื่อพระเครื่อง</th>
                    <th style="width:14%">หมวดหมู่</th>
                    <th style="width:12%">ราคา</th>
                    <th style="width:10%">คงเหลือ</th>
                    <th style="width:10%">ปล่อยเช่าแล้ว</th>
                    <th style="width:12%">รายได้</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td style="text-align:left"><?php echo htmlspecialchars($p['amulet_name']); ?></td>
                <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                <td>฿<?php echo number_format($p['price'], 2); ?></td>
                <td><?php echo $p['quantity'] > 0 ? number_format($p['quantity']) : 'หมด'; ?></td>
                <td><?php echo number_format($p['total_sold']); ?></td>
                <td>฿<?php echo number_format($p['revenue'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="font-weight:700;text-align:right">รวมทั้งหมด</td>
                    <td></td>
                    <td style="font-weight:700"><?php echo number_format($in_stock); ?> รายการ</td>
                    <td style="font-weight:700"><?php echo number_format($total_sold_all); ?></td>
                    <td style="font-weight:700">฿<?php echo number_format($total_revenue, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-box-open"></i>
            <h2>ยังไม่มีพระเครื่องในร้าน</h2>
        </div>
        <?php endif; ?>

        <!-- Print footer -->
        <div class="print-footer">
            <div>ผู้พิมพ์รายงาน : <?php echo htmlspecialchars($seller['fullname']); ?></div>
            <div>วันที่พิมพ์ : <?php
                $mn=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                     'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                echo date('j').' '.$mn[(int)date('n')].' '.(date('Y')+543);
            ?></div>
            <div>เวลาที่พิมพ์ : <span class="print-time-now"></span> น.</div>
        </div>

        <!-- Bottom print button -->
        <div style="text-align:center;margin-top:20px" class="no-print">
            <button onclick="window.print()" class="btn btn-primary" style="padding:12px 32px;font-size:15px">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึกเป็น PDF
            </button>
        </div>


        <?php else: ?>
        <!-- ════════════════════════════════════════════
             TAB: รายงานการปล่อยเช่า (โค้ดเดิมทั้งหมด)
        ════════════════════════════════════════════ -->

        <!-- Period tabs -->
        <div class="period-tabs no-print">
            <a href="?view=sales&period=daily&day=<?php echo date('Y-m-d'); ?>"
               class="period-tab <?php echo $period==='daily' ? 'active':''; ?>">
                <i class="fa-solid fa-calendar-day"></i> รายวัน
            </a>
            <a href="?view=sales&period=monthly&year=<?php echo $year; ?>&month=<?php echo $month; ?>"
               class="period-tab <?php echo $period==='monthly' ? 'active':''; ?>">
                <i class="fa-solid fa-calendar-week"></i> รายเดือน
            </a>
            <a href="?view=sales&period=yearly&year=<?php echo $year; ?>"
               class="period-tab <?php echo $period==='yearly' ? 'active':''; ?>">
                <i class="fa-solid fa-calendar"></i> รายปี
            </a>
        </div>

        <!-- Period selector -->
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px" class="no-print">
            <input type="hidden" name="view" value="sales">
            <input type="hidden" name="period" value="<?php echo $period; ?>">

            <?php if ($period === 'daily'): ?>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">เลือกวัน</label>
                <!-- [แก้ไข] เปลี่ยนจาก input type="date" เป็น Flatpickr แสดง พ.ศ. -->
                <input type="text" id="display_day" class="flatpickr-input"
                       value="<?php echo dateTH('d/m/Y', strtotime($day)); ?>" readonly
                       style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px;background:#fff;cursor:pointer">
                <input type="hidden" name="day" id="hidden_day" value="<?php echo $day; ?>">
            </div>

            <?php elseif ($period === 'monthly'): ?>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ปี</label>
                <select name="year" style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
                    <?php foreach ($years as $y): ?>
                    <!-- [แก้ไข] value เป็น ค.ศ. (ส่ง query), แสดงเป็น พ.ศ. -->
                    <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y + 543; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">เดือน</label>
                <select name="month" style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
                    <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m==$month?'selected':''; ?>><?php echo $months_th[$m]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php elseif ($period === 'yearly'): ?>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ปี</label>
                <select name="year" style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
                    <?php foreach ($years as $y): ?>
                    <!-- [แก้ไข] value เป็น ค.ศ. (ส่ง query), แสดงเป็น พ.ศ. -->
                    <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y + 543; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-filter"></i> ดูรายงาน
            </button>
        </form>

        <!-- Period label -->
        <div style="font-size:13px;color:#6b7280;margin-bottom:16px">
            <i class="fa-solid fa-clock"></i>
            ช่วงเวลา: <strong style="color:#1a1a1a"><?php echo $date_label; ?></strong>
        </div>

        <!-- Summary Boxes -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($summary['order_count']); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-cart-shopping"></i> Orders เสร็จสิ้น</div>
            </div>
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($summary['total_qty']); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-box"></i> จำนวนชิ้นที่ปล่อยเช่า</div>
            </div>
            <div class="summary-box">
                <div class="s-val" style="font-size:20px">
                    ฿<?php echo number_format($summary['total_revenue'], 2); ?>
                </div>
                <div class="s-lbl"><i class="fa-solid fa-baht-sign"></i> รายได้รวม</div>
            </div>
        </div>

        <!-- Chart + Top Products -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:22px">
            <div class="chart-wrap">
                <h3><i class="fa-solid fa-chart-bar" style="color:#10b981"></i>
                    กราฟรายได้แยก<?php echo $x_label; ?>
                </h3>
                <?php if (count($chart_data) > 0): ?>
                <canvas id="revenueChart" style="max-height:220px"></canvas>
                <?php else: ?>
                <div class="empty-state" style="padding:30px">
                    <i class="fa-solid fa-chart-bar"></i>
                    <p>ไม่มีข้อมูลในช่วงนี้</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="chart-wrap">
                <h3><i class="fa-solid fa-fire" style="color:#f59e0b"></i> พระเครื่องปล่อยเช่าดี</h3>
                <?php if (count($top_products) > 0): ?>
                <?php foreach ($top_products as $i => $p): ?>
                <div class="top-item">
                    <div class="top-rank <?php echo $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')); ?>">
                        <?php echo $i+1; ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?php echo htmlspecialchars($p['amulet_name']); ?>
                        </div>
                        <div style="font-size:11px;color:#9ca3af">
                            ปล่อยเช่า <?php echo number_format($p['qty_sold']); ?> ชิ้น
                        </div>
                    </div>
                    <div style="font-size:12px;font-weight:700;color:#10b981;white-space:nowrap">
                        ฿<?php echo number_format($p['revenue'],0); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="text-align:center;color:#9ca3af;padding:20px;font-size:13px">ไม่มีข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fa-solid fa-list"></i> รายการคำสั่งเช่า (<?php echo count($orders); ?> รายการ)</h2>
            </div>
            <div class="table-wrapper">
            <?php if (count($orders) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ผู้เช่า</th>
                        <th>รายได้ร้านนี้</th>
                        <th>สถานะชำระ</th>
                        <th>สถานะส่ง</th>
                        <th>เลขพัสดุ</th>
                        <th>วันที่</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong style="font-size:13px">#<?php echo str_pad($o['id'],6,'0',STR_PAD_LEFT); ?></strong></td>
                    <td>
                        <div style="font-weight:500;font-size:13px"><?php echo htmlspecialchars($o['buyer_name']); ?></div>
                        <div style="font-size:11px;color:#9ca3af"><?php echo htmlspecialchars($o['buyer_tel']); ?></div>
                    </td>
                    <td><strong style="color:#10b981">฿<?php echo number_format($o['seller_total'],2); ?></strong></td>
                    <td>
                        <?php if ($o['pay_status']==='confirmed'):  echo '<span class="st-ok"><i class="fa-solid fa-check"></i> ยืนยันแล้ว</span>';
                        elseif ($o['pay_status']==='waiting'):       echo '<span class="st-wait"><i class="fa-solid fa-clock"></i> รอยืนยัน</span>';
                        else:                                         echo '<span style="color:#d1d5db;font-size:11px">-</span>'; endif; ?>
                    </td>
                    <td>
                        <?php if ($o['status']==='completed'):       echo '<span class="st-ok"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>';
                        elseif ($o['pay_status']==='confirmed'):     echo '<span class="st-ship"><i class="fa-solid fa-truck"></i> กำลังส่ง</span>';
                        else:                                         echo '<span class="st-wait"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>'; endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:12px">
                        <?php echo !empty($o['tracking_number'])
                            ? '<span style="background:#f0fdf4;color:#059669;padding:2px 7px;border-radius:5px;border:1px solid #a7f3d0">'.htmlspecialchars($o['tracking_number']).'</span>'
                            : '<span style="color:#d1d5db">-</span>'; ?>
                    </td>
                    <td style="font-size:12px;color:#6b7280;white-space:nowrap">
                        <!-- [แก้ไข] date() → dateTH() แสดงปี พ.ศ. ในตาราง -->
                        <?php echo dateTH('d/m/Y', strtotime($o['created_at'])); ?><br>
                        <span style="color:#9ca3af"><?php echo dateTH('H:i', strtotime($o['created_at'])); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-chart-bar"></i>
                <h2>ไม่มีคำสั่งเช่าในช่วงนี้</h2>
            </div>
            <?php endif; ?>
            </div>
        </div>

        <div style="text-align:center;margin-top:20px" class="no-print">
            <button onclick="window.print()" class="btn btn-primary" style="padding:12px 32px;font-size:15px">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึกเป็น PDF
            </button>
        </div>

        <?php endif; ?>

    </main>
</div>

<!-- Chart.js (sales view only) -->
<?php if ($view === 'sales' && isset($chart_data) && count($chart_data) > 0): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const chartRaw  = <?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE); ?>;
const period    = "<?php echo $period; ?>";
const monthsTH  = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];

const labels = chartRaw.map(r => {
    const k = r.period_key;
    if (period === 'yearly')  return monthsTH[parseInt(k)] || k;
    if (period === 'monthly') return 'วันที่ ' + parseInt(k);
    return k + ':00';
});
const revenues = chartRaw.map(r => parseFloat(r.revenue));
const orders   = chartRaw.map(r => parseInt(r.orders));

const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'รายได้ (฿)',
                data: revenues,
                backgroundColor: 'rgba(16,185,129,.7)',
                borderColor: '#10b981',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y',
            },
            {
                label: 'จำนวน Orders',
                data: orders,
                type: 'line',
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,.12)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.35,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { font: { family: 'Sarabun', size: 12 } } },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        if (ctx.datasetIndex === 0)
                            return ' รายได้: ฿' + ctx.raw.toLocaleString('th-TH', {minimumFractionDigits:2});
                        return ' Orders: ' + ctx.raw + ' รายการ';
                    }
                }
            }
        },
        scales: {
            y: {
                type: 'linear', position: 'left',
                ticks: {
                    callback: v => '฿' + v.toLocaleString('th-TH'),
                    font: { family: 'Sarabun', size: 11 }
                },
                grid: { color: '#f3f4f6' }
            },
            y1: {
                type: 'linear', position: 'right',
                ticks: { font: { family: 'Sarabun', size: 11 } },
                grid: { drawOnChartArea: false }
            },
            x: { ticks: { font: { family: 'Sarabun', size: 11 } } }
        }
    }
});
</script>
<?php endif; ?>
<script>
function setNowTime(){
    var n=new Date(),h=String(n.getHours()).padStart(2,'0'),m=String(n.getMinutes()).padStart(2,'0');
    document.querySelectorAll('.print-time-now').forEach(function(e){e.textContent=h+':'+m;});
}
setNowTime();
window.addEventListener('beforeprint',setNowTime);
</script>

<!-- [แก้ไข] Flatpickr สำหรับ input วันที่ period=daily แสดง พ.ศ. -->
<?php if (($view ?? '') === 'sales' && ($period ?? '') === 'daily'): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
function toBEString(d) {
    return String(d.getDate()).padStart(2,'0') + '/' +
           String(d.getMonth()+1).padStart(2,'0') + '/' +
           (d.getFullYear() + 543);
}
function fixCalendarHeader(fp) {
    const yearEl = fp.calendarContainer.querySelector('.numInput.cur-year');
    if (yearEl && !yearEl._be_patched) {
        yearEl._be_patched = true;
        // แสดงปี พ.ศ. ใน header
        yearEl.value = parseInt(yearEl.value) + 543;
        yearEl.addEventListener('input', function() {
            if (this.value.length === 4) fp.changeYear(parseInt(this.value) - 543);
        });
    }
}
flatpickr('#display_day', {
    dateFormat: 'Y-m-d',
    defaultDate: '<?php echo $day; ?>',
    locale: { firstDayOfWeek: 1 },
    onReady: function(sel, str, fp) {
        if (sel.length) document.getElementById('display_day').value = toBEString(sel[0]);
        fixCalendarHeader(fp);
    },
    onMonthChange: function(sel, str, fp) { fixCalendarHeader(fp); },
    onYearChange:  function(sel, str, fp) { fixCalendarHeader(fp); },
    onChange: function(sel, dateStr) {
        document.getElementById('hidden_day').value = dateStr;
        if (sel.length) document.getElementById('display_day').value = toBEString(sel[0]);
    }
});
</script>
<?php endif; ?>
</body>
</html>