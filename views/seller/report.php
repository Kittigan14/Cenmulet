<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php"); exit;
}

$seller_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
$stmt->execute([':id' => $seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

// ── ประเภทรายงาน: sales / products ────────────────────
$view = $_GET['view'] ?? 'sales';
if (!in_array($view, ['sales', 'products'])) $view = 'sales';

// ════════════════════════════════════════════════════════
//  VIEW: PRODUCTS — สินค้าทั้งหมดของ seller คนนี้
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
    //  VIEW: SALES — รายงานการขาย (โค้ดเดิม)
    // ════════════════════════════════════════════════════

    $period = $_GET['period'] ?? 'monthly';
    $year   = (int)($_GET['year']  ?? date('Y'));
    $month  = (int)($_GET['month'] ?? date('n'));
    $day    = $_GET['day']         ?? date('Y-m-d');

    $allowed_periods = ['daily','monthly','yearly'];
    if (!in_array($period, $allowed_periods)) $period = 'monthly';

    switch ($period) {
        case 'daily':
            $date_label = date('d เดือน m ปี Y', strtotime($day));
            $where_time = "AND DATE(o.created_at) = '$day'";
            $group_by   = "strftime('%H', o.created_at)";
            $x_label    = "ชั่วโมง";
            break;
        case 'monthly':
            $date_label = "เดือน " . date('F Y', mktime(0,0,0,$month,1,$year));
            $where_time = "AND strftime('%Y-%m', o.created_at) = '" . sprintf('%04d-%02d',$year,$month) . "'";
            $group_by   = "strftime('%d', o.created_at)";
            $x_label    = "วัน";
            break;
        case 'yearly':
            $date_label = "ปี $year";
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
    <title>รายงานการขาย - <?php echo htmlspecialchars($seller['store_name']); ?></title>
    <style>
        /* ── Print ── */
        @media print {
            .no-print  { display: none !important; }
            .sidebar   { display: none !important; }
            .main-content { margin: 0 !important; padding: 14px !important; }
            .dashboard-container { display: block !important; }
            body { background: #fff !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .print-header { display: block !important; }
            canvas { max-height: 220px !important; }

            /* products print table */
            .products-grid  { display: none !important; }
            .products-print { display: table !important; width: 100%; border-collapse: collapse; }
            .products-print th {
                background: #f3f4f6; color: #374151; font-weight: 700;
                padding: 7px 10px; text-align: left;
                border: 1px solid #e5e7eb; font-size: 11px; white-space: nowrap;
            }
            .products-print td {
                padding: 7px 10px; border: 1px solid #e5e7eb;
                vertical-align: middle; font-size: 11px;
            }
            .products-print tr:nth-child(even) td { background: #fafafa; }
            .p-thumb {
                width: 60px; height: 60px; object-fit: cover;
                border-radius: 6px; border: 1px solid #e5e7eb;
            }
            .p-thumb-ph {
                width: 60px; height: 60px; background: #f3f4f6;
                border-radius: 6px; border: 1px solid #e5e7eb;
                display: inline-flex; align-items: center; justify-content: center;
                color: #d1d5db; font-size: 22px;
            }
            .print-footer { display: block !important; }
            @page { margin: 12mm; size: A4 landscape; }
        }
        .print-header {
            display: none; text-align: center;
            margin-bottom: 18px; padding-bottom: 14px;
            border-bottom: 2px solid #e5e7eb;
        }
        .print-header h1 { font-size: 18px; margin-bottom: 3px; }
        .print-header p  { font-size: 12px; color: #6b7280; }
        .print-footer    { display: none; text-align: center; margin-top: 18px;
                           font-size: 10px; color: #9ca3af;
                           border-top: 1px solid #e5e7eb; padding-top: 8px; }

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
            <p>แดชบอร์ดผู้ขาย</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><i class="fa-solid fa-store"></i> ผู้ขาย</p>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-sep">เมนูหลัก</li>
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> สินค้าของฉัน</a></li>
            <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
            <li><a href="/views/seller/report.php" class="active"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
            <li class="menu-sep">ระบบ</li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <main class="main-content">

        <!-- ════ PRINT HEADER ════ -->
        <div class="print-header">
            <?php if ($view === 'products'): ?>
            <h1><i class="fa-solid fa-box"></i> รายการสินค้าทั้งหมด — <?php echo htmlspecialchars($seller['store_name']); ?></h1>
            <p>
                เจ้าของ: <?php echo htmlspecialchars($seller['fullname']); ?>
                &nbsp;|&nbsp; โทร: <?php echo htmlspecialchars($seller['tel']); ?>
                &nbsp;|&nbsp; พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?>
            </p>
            <?php else: ?>
            <h1>รายงานสรุปการขาย — <?php echo htmlspecialchars($seller['store_name']); ?></h1>
            <p>ช่วงเวลา: <?php echo $date_label; ?> &nbsp;|&nbsp; พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?></p>
            <?php endif; ?>
        </div>

        <!-- ════ TOP BAR ════ -->
        <div class="top-bar no-print">
            <h1><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</h1>
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
                <i class="fa-solid fa-chart-bar"></i> รายงานการขาย
            </a>
            <a href="?view=products"
               class="view-tab <?php echo $view === 'products' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> ข้อมูลสินค้า
            </a>
        </div>


        <?php if ($view === 'products'): ?>
        <!-- ════════════════════════════════════════════
             TAB: ข้อมูลสินค้า
        ════════════════════════════════════════════ -->

        <!-- Summary -->
        <div class="summary-row">
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($total_products); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-box"></i> สินค้าทั้งหมด</div>
            </div>
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($in_stock); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-cubes"></i> มีสินค้าในสต็อก</div>
            </div>
            <div class="summary-box">
                <div class="s-val"><?php echo number_format($total_sold_all); ?></div>
                <div class="s-lbl"><i class="fa-solid fa-cart-check"></i> ขายแล้วทั้งหมด</div>
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
                        <span><i class="fa-solid fa-cart-check"></i> ขาย <?php echo number_format($p['total_sold']); ?> ชิ้น</span>
                        <span style="color:#10b981;font-weight:600">฿<?php echo number_format($p['revenue'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PRINT: Table with image thumbnail -->
        <table class="products-print">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th style="width:70px">รูปภาพ</th>
                    <th>ชื่อสินค้า / รายละเอียด</th>
                    <th style="width:100px">หมวดหมู่</th>
                    <th style="width:90px;text-align:right">ราคา</th>
                    <th style="width:75px;text-align:center">คงเหลือ</th>
                    <th style="width:75px;text-align:center">ขายแล้ว</th>
                    <th style="width:110px;text-align:right">รายได้</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p): ?>
            <tr>
                <td style="color:#9ca3af;font-size:10px"><?php echo $i + 1; ?></td>
                <td>
                    <?php if (!empty($p['main_image'])): ?>
                        <img class="p-thumb"
                             src="/uploads/amulets/<?php echo htmlspecialchars($p['main_image']); ?>"
                             alt="<?php echo htmlspecialchars($p['amulet_name']); ?>">
                    <?php else: ?>
                        <span class="p-thumb-ph"><i class="fa-solid fa-image"></i></span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="font-size:12px"><?php echo htmlspecialchars($p['amulet_name']); ?></strong>
                    <?php if (!empty($p['source'])): ?>
                    <span style="display:block;font-size:10px;color:#6b7280;margin-top:2px">
                        <?php echo htmlspecialchars(mb_strimwidth($p['source'], 0, 100, '...')); ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($p['category_name'])): ?>
                    <span class="badge-cat"><?php echo htmlspecialchars($p['category_name']); ?></span>
                    <?php else: ?><span style="color:#d1d5db">-</span><?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:700;color:#10b981">
                    ฿<?php echo number_format($p['price'], 2); ?>
                </td>
                <td style="text-align:center">
                    <?php if ($p['quantity'] > 0): ?>
                        <span class="badge-ok"><?php echo number_format($p['quantity']); ?></span>
                    <?php else: ?>
                        <span class="badge-none">หมด</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:600"><?php echo number_format($p['total_sold']); ?></td>
                <td style="text-align:right;font-weight:700;color:#10b981">
                    ฿<?php echo number_format($p['revenue'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f9fafb">
                    <td colspan="4" style="font-weight:700;text-align:right">รวมทั้งหมด</td>
                    <td></td>
                    <td style="text-align:center;font-weight:700"><?php echo number_format($in_stock); ?> รายการ</td>
                    <td style="text-align:center;font-weight:700"><?php echo number_format($total_sold_all); ?></td>
                    <td style="text-align:right;font-weight:700;color:#10b981">฿<?php echo number_format($total_revenue, 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-box-open"></i>
            <h2>ยังไม่มีสินค้าในร้าน</h2>
        </div>
        <?php endif; ?>

        <!-- Print footer -->
        <div class="print-footer">
            ร้าน <?php echo htmlspecialchars($seller['store_name']); ?>
            &nbsp;|&nbsp; สินค้าทั้งหมด <?php echo number_format($total_products); ?> รายการ
            &nbsp;|&nbsp; พิมพ์เมื่อ <?php echo date('d/m/Y H:i'); ?>
        </div>

        <!-- Bottom print button -->
        <div style="text-align:center;margin-top:20px" class="no-print">
            <button onclick="window.print()" class="btn btn-primary" style="padding:12px 32px;font-size:15px">
                <i class="fa-solid fa-print"></i> พิมพ์ / บันทึกเป็น PDF
            </button>
        </div>


        <?php else: ?>
        <!-- ════════════════════════════════════════════
             TAB: รายงานการขาย (โค้ดเดิมทั้งหมด)
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
                <input type="date" name="day" value="<?php echo $day; ?>"
                       style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            </div>

            <?php elseif ($period === 'monthly'): ?>
            <div>
                <label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">ปี</label>
                <select name="year" style="padding:9px 12px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
                    <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y; ?></option>
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
                    <option value="<?php echo $y; ?>" <?php echo $y==$year?'selected':''; ?>><?php echo $y; ?></option>
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
                <div class="s-lbl"><i class="fa-solid fa-box"></i> จำนวนชิ้นที่ขาย</div>
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
                <h3><i class="fa-solid fa-fire" style="color:#f59e0b"></i> สินค้าขายดี</h3>
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
                            ขาย <?php echo number_format($p['qty_sold']); ?> ชิ้น
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
                <h2><i class="fa-solid fa-list"></i> รายการคำสั่งซื้อ (<?php echo count($orders); ?> รายการ)</h2>
            </div>
            <div class="table-wrapper">
            <?php if (count($orders) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ผู้ซื้อ</th>
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
                        <?php echo date('d/m/Y', strtotime($o['created_at'])); ?><br>
                        <span style="color:#9ca3af"><?php echo date('H:i', strtotime($o['created_at'])); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-chart-bar"></i>
                <h2>ไม่มีคำสั่งซื้อในช่วงนี้</h2>
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
</body>
</html>