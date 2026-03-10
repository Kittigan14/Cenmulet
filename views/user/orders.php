<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php"); exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

try {
    $stmt = $db->prepare("
        SELECT o.id, o.total_price, o.status, o.created_at,
               o.tracking_number, o.shipped_at,
               p.status as pay_status, p.slip_image,
               COUNT(DISTINCT oi.id) as item_count,
               GROUP_CONCAT(DISTINCT s.store_name) as store_names,
               GROUP_CONCAT(DISTINCT a.amulet_name) as amulet_names
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN amulets a ON oi.amulet_id = a.id
        LEFT JOIN sellers s ON a.sellerId = s.id
        WHERE o.user_id = :uid
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <title>รายการเช่าของฉัน - Cenmulet</title>
    <style>
        @media print {
            .no-print, .navbar { display: none !important; }
            html, body { margin:0; padding:0; background:#fff !important; }
            .container { max-width:100% !important; padding:0 !important; }
            .print-page-header { display: flex !important; }
            .print-table-wrap  { display: block !important; }
            .print-footer-wrap { display: block !important; }
            .print-report-table {
                width:100%; border-collapse:collapse;
                font-size:9px; font-family:'Sarabun',sans-serif; table-layout:fixed;
            }
            .print-report-table th {
                background:#b8960c !important; color:#fff !important;
                padding:6px 7px; text-align:center; border:1px solid #6b4f0a;
                font-size:9px; font-weight:700;
                -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
            }
            .print-report-table td {
                padding:6px 7px; border:1px solid #d1d5db;
                vertical-align:middle; text-align:center; font-size:9px; color:#1a1a1a;
            }
            .print-report-table tr:nth-child(even) td {
                background:#fdf8ee !important;
                -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important;
            }
            @page { size:A4 landscape; margin:10mm 12mm; }
        }
        .print-page-header {
            display:none; align-items:center; gap:18px;
            margin-bottom:10px; padding-bottom:10px;
            border-bottom:2.5px solid #8B6914; font-family:'Sarabun',sans-serif;
        }
        .ph-logo    { width:72px; height:72px; object-fit:contain; border-radius:50%; mix-blend-mode:multiply; flex-shrink:0; }
        .ph-info    { flex:1; text-align:center; line-height:1.6; }
        .ph-company { font-size:18pt; font-weight:800; color:#1a1a1a; }
        .ph-addr    { font-size:9pt; color:#333; }
        .ph-tel     { font-size:9pt; color:#333; }
        .ph-title   { font-size:13pt; font-weight:700; color:#1a1a1a; margin-top:5px; }
        .ph-sub     { font-size:9pt; color:#444; }
        .print-table-wrap  { display:none; }
        .print-footer-wrap { display:none; text-align:right; margin-top:36px; font-size:9pt; color:#333; line-height:2; font-family:'Sarabun',sans-serif; }
        .btn-print-history {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 18px; border-radius: 9px; font-size: 14px;
            font-weight: 600; cursor: pointer; border: none;
            font-family: inherit; background: #6366f1; color: #fff;
            transition: all .2s; margin-bottom: 16px;
        }
        .btn-print-history:hover { background: #4f46e5; }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sarabun',sans-serif; background:#f8f8f8; color:#333; }

        /* Navbar */
        .navbar {
            background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.08);
            padding:0 32px; height:64px;
            display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:100;
        }
        .nav-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-logo img { width:38px; height:38px; }
        .nav-logo h2 { font-family:'Kanit',sans-serif; font-size:20px; color:#c8922a; }
        .nav-logo p  { font-size:11px; color:#9ca3af; margin-top:-2px; }
        .nav-links { display:flex; align-items:center; gap:8px; }
        .nav-link {
            padding:8px 16px; border-radius:8px; font-size:14px; font-weight:500;
            text-decoration:none; color:#555; transition:all .2s;
            display:flex; align-items:center; gap:6px;
        }
        .nav-link:hover { background:#fff8ee; color:#c8922a; }
        .nav-link.active { background:#fff8ee; color:#c8922a; }
        .nav-user {
            display:flex; align-items:center; gap:8px;
            padding:7px 14px; border-radius:8px; border:1.5px solid #e5e7eb;
            cursor:pointer; font-size:14px; color:#555;
        }

        /* Page */
        .container { max-width:900px; margin:0 auto; padding:32px 16px; }
        .page-title { font-family:'Kanit',sans-serif; font-size:26px; font-weight:700; color:#1a1a1a; margin-bottom:4px; }
        .page-sub   { font-size:14px; color:#9ca3af; margin-bottom:28px; }

        /* Order Card */
        .order-card {
            background:#fff; border-radius:16px; border:1.5px solid #e5e7eb;
            margin-bottom:16px; overflow:hidden;
            transition:box-shadow .2s;
        }
        .order-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }

        .order-header {
            padding:18px 22px; display:flex;
            align-items:center; justify-content:space-between;
            border-bottom:1.5px solid #f3f4f6;
        }
        .order-id   { font-family:'Kanit',sans-serif; font-size:16px; font-weight:700; color:#1a1a1a; }
        .order-date { font-size:12px; color:#9ca3af; margin-top:2px; }

        .order-body  { padding:16px 22px; }
        .order-row   { display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; }
        .order-icon  {
            width:32px; height:32px; border-radius:8px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; font-size:14px;
        }
        .order-label { font-size:12px; color:#9ca3af; }
        .order-value { font-size:14px; font-weight:500; color:#1a1a1a; }

        /* Tracking Box */
        .tracking-box {
            background:linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border:2px solid #a7f3d0; border-radius:12px;
            padding:16px 20px; margin:14px 0;
            display:flex; align-items:center; gap:14px;
        }
        .tracking-icon {
            width:44px; height:44px; border-radius:10px;
            background:#10b981; color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:18px;
            flex-shrink:0;
        }
        .tracking-label { font-size:11px; color:#059669; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .tracking-number { font-family:monospace; font-size:20px; font-weight:800; color:#059669; letter-spacing:1px; }
        .tracking-copy {
            margin-left:auto; padding:7px 14px; border-radius:8px;
            border:1.5px solid #10b981; background:#fff; color:#059669;
            font-size:13px; font-weight:600; cursor:pointer; font-family:inherit;
            transition:all .2s;
        }
        .tracking-copy:hover { background:#10b981; color:#fff; }
        .tracking-no-box {
            background:#f9fafb; border:1.5px dashed #e5e7eb; border-radius:12px;
            padding:14px 20px; margin:14px 0; text-align:center;
            color:#9ca3af; font-size:13px;
        }

        .order-footer {
            padding:14px 22px; background:#f9fafb;
            border-top:1.5px solid #f3f4f6;
            display:flex; align-items:center; justify-content:space-between;
        }
        .order-total { font-family:'Kanit',sans-serif; font-size:20px; font-weight:700; color:#c8922a; }

        /* Badges */
        .badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 12px; border-radius:99px; font-size:12px; font-weight:600;
        }
        .badge-waiting   { background:#fef3c7; color:#d97706; }
        .badge-confirmed { background:#dbeafe; color:#1d4ed8; }
        .badge-success   { background:#d1fae5; color:#059669; }
        .badge-danger    { background:#fee2e2; color:#dc2626; }

        .store-tag {
            display:inline-flex; align-items:center; gap:4px;
            background:#ede9fe; color:#6d28d9;
            padding:3px 9px; border-radius:99px; font-size:12px; font-weight:600;
            margin:2px;
        }

        /* Buttons */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:9px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; border:none; font-family:inherit; transition:all .2s; }
        .btn-primary   { background:#c8922a; color:#fff; }
        .btn-primary:hover { background:#a87520; }
        .btn-outline   { background:#fff; color:#c8922a; border:1.5px solid #c8922a; }
        .btn-outline:hover { background:#fff8ee; }
        .btn-sm { padding:6px 13px; font-size:13px; }

        /* Confirm delivery */
        .confirm-btn {
            background:linear-gradient(135deg,#c8922a,#e0a83a);
            color:#fff; border:none; border-radius:9px; padding:9px 18px;
            font-size:13px; font-weight:700; cursor:pointer; font-family:inherit;
            display:inline-flex; align-items:center; gap:7px;
            transition:all .2s; box-shadow:0 3px 12px rgba(200,146,42,.3);
        }
        .confirm-btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(200,146,42,.4); }

        /* Empty */
        .empty-state { text-align:center; padding:60px 20px; }
        .empty-state i { font-size:64px; color:#e5e7eb; display:block; margin-bottom:16px; }
        .empty-state h2 { font-size:20px; color:#9ca3af; margin-bottom:8px; }
        .empty-state p { color:#d1d5db; font-size:14px; }

        /* Toast */
        #toast {
            position:fixed; bottom:24px; right:24px; z-index:9999;
            background:#1a1a1a; color:#fff; padding:12px 20px; border-radius:10px;
            font-size:14px; font-weight:500; display:none;
            align-items:center; gap:8px; box-shadow:0 8px 24px rgba(0,0,0,.2);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<!-- <nav class="navbar">
    <a class="nav-logo" href="/views/user/home.php">
        <img src="/public/images/image.png" alt="">
        <div>
            <h2>Cenmulet</h2>
            <p>ตลาดพระเครื่อง</p>
        </div>
    </a>
    <div class="nav-links">
        <a href="/views/user/home.php" class="nav-link"><i class="fa-solid fa-home"></i> หน้าแรก</a>
        
    </div>
    <div class="nav-user">
        <i class="fa-solid fa-user-circle" style="color:#c8922a"></i>
        <?php echo htmlspecialchars($user['fullname']); ?>
    </div>
</nav> -->

<?php
// ── ตัวแปรที่ navbar ต้องการ ──
$active_page = 'orders';

try {
    $stmt2 = $db->prepare("SELECT SUM(quantity) as cnt FROM cart_items WHERE user_id = :uid");
    $stmt2->execute([':uid' => $user_id]);
    $cart_count = (int)($stmt2->fetchColumn() ?? 0);
} catch (PDOException $e) {
    $cart_count = 0;
}

include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    <!-- Print Header -->
    <div class="print-page-header">
        <img class="ph-logo" src="data:image/png;base64,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" alt="logo">
        <div class="ph-info">
            <div class="ph-company">เซ็นมูเล็ท จำกัด</div>
            <div class="ph-addr">เลขที่ 295/55 หมู่ 6 ตำบล บางพูน อำเภอ เมืองปทุมธานี จังหวัด ปทุมธานี 12000</div>
            <div class="ph-tel">โทรศัพท์ : 063-756-0774</div>
            <div class="ph-title">รายงานประวัติการเช่า</div>
            <div class="ph-sub">ผู้เช่า : <?php echo htmlspecialchars($user['fullname'] ?? ''); ?></div>
        </div>
    </div>

    <!-- Print Table -->
    <div class="print-table-wrap">
        <table class="print-report-table">
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:10%">รหัสการเช่า</th>
                    <th style="width:14%">วันที่สั่ง</th>
                    <th style="width:20%">ชื่อสินค้า</th>
                    <th style="width:14%">ร้านค้า</th>
                    <th style="width:10%">ยอดชำระ</th>
                    <th style="width:14%">สถานะการชำระ</th>
                    <th style="width:14%">สถานะการจัดส่ง</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $idx => $o):
                $stores = array_filter(explode(',', $o['store_names'] ?? ''));
                $pay_labels = ['waiting'=>'รอยืนยัน','confirmed'=>'ยืนยันแล้ว','rejected'=>'ปฏิเสธ'];
                if ($o['status']==='completed') $ship = 'จัดส่งสำเร็จ';
                elseif ($o['pay_status']==='confirmed') $ship = 'กำลังจัดส่ง';
                else $ship = 'รอดำเนินการ';
            ?>
            <tr>
                <td><?php echo $idx+1; ?></td>
                <td><strong>#<?php echo str_pad($o['id'],6,'0',STR_PAD_LEFT); ?></strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                <td style="text-align:left"><?php echo htmlspecialchars($o['amulet_names'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars(implode(', ', array_map('trim', $stores))); ?></td>
                <td><strong>฿<?php echo number_format($o['total_price'],2); ?></strong></td>
                <td><?php echo $pay_labels[$o['pay_status']] ?? '-'; ?></td>
                <td><?php echo $ship; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Print Footer -->
    <div class="print-footer-wrap">
        <div>ผู้พิมพ์รายงาน : <?php echo htmlspecialchars($user['fullname'] ?? ''); ?></div>
        <div>วันที่พิมพ์ : <?php
            $mn=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                 'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            echo date('j').' '.$mn[(int)date('n')].' '.(date('Y')+543);
        ?></div>
        <div>เวลาที่พิมพ์ : <span class="print-time-now"></span> น.</div>
    </div>

    <div class="no-print">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:8px">
        <h1 class="page-title" style="margin-bottom:0"><i class="fa-solid fa-box" style="color:#c8922a"></i> รายการเช่าของฉัน</h1>
        <button onclick="window.print()" class="btn-print-history no-print">
            <i class="fa-solid fa-print"></i> พิมพ์ประวัติการเช่า
        </button>
    </div>
    <p class="page-sub">ตรวจสอบสถานะและรายละเอียดรายการเช่าทั้งหมด</p>

    <?php if (isset($_GET['delivery_confirmed'])): ?>
    <div style="background:#d1fae5;border:1.5px solid #a7f3d0;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;color:#059669">
        <i class="fa-solid fa-check-circle fa-lg"></i>
        <span style="font-weight:600">ยืนยันรับสินค้าเรียบร้อยแล้ว! ขอบคุณที่ใช้บริการ Cenmulet</span>
    </div>
    <?php endif; ?>

    <?php if (count($orders) > 0): ?>
    <?php foreach ($orders as $o):
        $stores = array_filter(explode(',', $o['store_names'] ?? ''));
    ?>
    <div class="order-card">
        <!-- Header -->
        <div class="order-header">
            <div>
                <div class="order-id">
                    <i class="fa-solid fa-receipt" style="color:#c8922a;font-size:14px"></i>
                    รายการเช่า #<?php echo str_pad($o['id'],6,'0',STR_PAD_LEFT); ?>
                </div>
                <div class="order-date">
                    <i class="fa-regular fa-clock"></i>
                    <?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?> น.
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <!-- สถานะการชำระ -->
                <?php if ($o['pay_status'] === 'confirmed'): ?>
                    <span class="badge badge-confirmed"><i class="fa-solid fa-check"></i> ยืนยันการชำระแล้ว</span>
                <?php elseif ($o['pay_status'] === 'waiting'): ?>
                    <span class="badge badge-waiting"><i class="fa-solid fa-clock"></i> รอยืนยันการชำระ</span>
                <?php elseif ($o['pay_status'] === 'rejected'): ?>
                    <span class="badge badge-danger"><i class="fa-solid fa-times"></i> ปฏิเสธการชำระ</span>
                <?php endif; ?>
                <!-- สถานะ order -->
                <?php if ($o['status'] === 'completed'): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> จัดส่งสำเร็จ</span>
                <?php elseif ($o['pay_status'] === 'confirmed'): ?>
                    <span class="badge badge-confirmed"><i class="fa-solid fa-truck"></i> กำลังจัดส่ง</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Body -->
        <div class="order-body">
            <!-- ร้านค้า -->
            <?php if (!empty($stores)): ?>
            <div class="order-row">
                <div class="order-icon" style="background:#ede9fe;color:#6d28d9">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <div class="order-label">ร้านค้า</div>
                    <div style="margin-top:3px">
                        <?php foreach ($stores as $st): ?>
                        <span class="store-tag"><i class="fa-solid fa-store" style="font-size:10px"></i> <?php echo htmlspecialchars(trim($st)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- รายการสินค้า -->
            <div class="order-row">
                <div class="order-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div>
                    <div class="order-label">จำนวนสินค้า</div>
                    <div class="order-value"><?php echo $o['item_count']; ?> รายการ</div>
                </div>
            </div>

            <!-- สถานะการชำระเงิน -->
            <div class="order-row">
                <div class="order-icon" style="background:#dbeafe;color:#1d4ed8">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <div>
                    <div class="order-label">สถานะการชำระเงิน</div>
                    <div class="order-value">
                        <?php
                        $pay_labels = [
                            'waiting'   => 'รอยืนยันการชำระ',
                            'confirmed' => 'ยืนยันแล้ว',
                            'rejected'  => 'ปฏิเสธ',
                        ];
                        echo $pay_labels[$o['pay_status']] ?? '-';
                        ?>
                    </div>
                </div>
            </div>

            <!-- สถานะการจัดส่ง -->
            <div class="order-row">
                <div class="order-icon" style="background:#d1fae5;color:#059669">
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div>
                    <div class="order-label">สถานะการจัดส่ง</div>
                    <div class="order-value">
                        <?php if ($o['status'] === 'completed'):      echo 'จัดส่งสำเร็จ';
                        elseif ($o['pay_status'] === 'confirmed'):     echo 'กำลังดำเนินการจัดส่ง';
                        else:                                           echo 'รอดำเนินการ'; endif; ?>
                    </div>
                </div>
            </div>

            <!-- ─── เลขพัสดุ ─── -->
            <?php if (!empty($o['tracking_number'])): ?>
            <div class="tracking-box">
                <div class="tracking-icon"><i class="fa-solid fa-truck-fast"></i></div>
                <div>
                    <div class="tracking-label"><i class="fa-solid fa-barcode"></i> เลขพัสดุ / Tracking Number</div>
                    <div class="tracking-number"><?php echo htmlspecialchars($o['tracking_number']); ?></div>
                    <?php if (!empty($o['shipped_at'])): ?>
                    <div style="font-size:11px;color:#059669;margin-top:3px">
                        <i class="fa-regular fa-clock"></i>
                        ส่งเมื่อ: <?php echo date('d/m/Y H:i', strtotime($o['shipped_at'])); ?> น.
                    </div>
                    <?php endif; ?>
                </div>
                <button class="tracking-copy"
                        onclick="copyTracking('<?php echo htmlspecialchars($o['tracking_number']); ?>')">
                    <i class="fa-solid fa-copy"></i> คัดลอก
                </button>
            </div>
            <?php elseif ($o['pay_status'] === 'confirmed' && $o['status'] !== 'completed'): ?>
            <div class="tracking-no-box">
                <i class="fa-solid fa-clock" style="color:#f59e0b;margin-bottom:5px;display:block"></i>
                ร้านค้ากำลังเตรียมพัสดุ เลขพัสดุจะปรากฏที่นี่เมื่อทำการจัดส่ง
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="order-footer">
            <div>
                <div style="font-size:12px;color:#9ca3af">ยอดชำระ</div>
                <div class="order-total">฿<?php echo number_format($o['total_price'], 2); ?></div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?php if ($o['pay_status'] === 'confirmed' && $o['status'] !== 'completed'): ?>
                <form action="/user/confirm_delivery.php" method="POST">
                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                    <button type="submit" class="confirm-btn"
                            onclick="return confirm('ยืนยันว่าได้รับสินค้าแล้ว?')">
                        <i class="fa-solid fa-box-open"></i> ยืนยันรับสินค้า
                    </button>
                </form>
                <?php endif; ?>
                <a href="/views/user/order_detail.php?id=<?php echo $o['id']; ?>" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-eye"></i> ดูรายละเอียด
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-box-open"></i>
        <h2>ยังไม่มีรายการเช่า</h2>
        <p>เริ่มช้อปปิ้งพระเครื่องกับ Cenmulet เลย!</p>
        <a href="/views/user/home.php" class="btn btn-primary" style="margin-top:20px">
            <i class="fa-solid fa-store"></i> ไปช้อปปิ้ง
        </a>
    </div>
    <?php endif; ?>
</div>

</div><!-- /no-print -->
<!-- Toast notification -->
<div id="toast"><i class="fa-solid fa-check-circle" style="color:#10b981"></i> คัดลอกเลขพัสดุแล้ว!</div>

<script>
function setNowTime(){
    var n=new Date(),h=String(n.getHours()).padStart(2,'0'),m=String(n.getMinutes()).padStart(2,'0');
    document.querySelectorAll('.print-time-now').forEach(function(e){e.textContent=h+':'+m;});
}
setNowTime();
window.addEventListener('beforeprint',setNowTime);
</script>
<script>
function copyTracking(text) {
    navigator.clipboard.writeText(text).then(() => {
        const toast = document.getElementById('toast');
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 2500);
    }).catch(() => {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        const toast = document.getElementById('toast');
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 2500);
    });
}
</script>
</body>
</html>