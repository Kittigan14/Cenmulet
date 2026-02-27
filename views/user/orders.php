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
               GROUP_CONCAT(DISTINCT s.store_name) as store_names
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
    <title>คำสั่งซื้อของฉัน - Cenmulet</title>
    <style>
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
<nav class="navbar">
    <a class="nav-logo" href="/views/user/home.php">
        <img src="/public/images/image.png" alt="">
        <div>
            <h2>Cenmulet</h2>
            <p>ตลาดพระเครื่อง</p>
        </div>
    </a>
    <div class="nav-links">
        <a href="/views/user/home.php" class="nav-link"><i class="fa-solid fa-home"></i> หน้าแรก</a>
        <a href="/views/user/my_orders.php" class="nav-link active"><i class="fa-solid fa-box"></i> คำสั่งซื้อของฉัน</a>
    </div>
    <div class="nav-user">
        <i class="fa-solid fa-user-circle" style="color:#c8922a"></i>
        <?php echo htmlspecialchars($user['fullname']); ?>
    </div>
</nav>

<div class="container">
    <h1 class="page-title"><i class="fa-solid fa-box" style="color:#c8922a"></i> คำสั่งซื้อของฉัน</h1>
    <p class="page-sub">ตรวจสอบสถานะและรายละเอียดคำสั่งซื้อทั้งหมด</p>

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
                    คำสั่งซื้อ #<?php echo str_pad($o['id'],6,'0',STR_PAD_LEFT); ?>
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
        <h2>ยังไม่มีคำสั่งซื้อ</h2>
        <p>เริ่มช้อปปิ้งพระเครื่องกับ Cenmulet เลย!</p>
        <a href="/views/user/home.php" class="btn btn-primary" style="margin-top:20px">
            <i class="fa-solid fa-store"></i> ไปช้อปปิ้ง
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Toast notification -->
<div id="toast"><i class="fa-solid fa-check-circle" style="color:#10b981"></i> คัดลอกเลขพัสดุแล้ว!</div>

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