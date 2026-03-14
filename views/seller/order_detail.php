<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
$order_id  = (int)($_GET['id'] ?? 0);

if (!$order_id) {
    header("Location: /views/seller/orders.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("
        SELECT DISTINCT
            o.id, o.total_price, o.status, o.created_at,
            o.tracking_number, o.shipped_at,
            u.fullname as buyer_name, u.tel as buyer_tel, u.address as buyer_address,
            p.slip_image, p.status as payment_status, p.confirmed_at
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = :order_id AND a.sellerId = :seller_id
    ");
    $stmt->execute([':order_id' => $order_id, ':seller_id' => $seller_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: /views/seller/orders.php?error=unauthorized");
        exit;
    }

    $stmt = $db->prepare("
        SELECT oi.*, a.amulet_name, a.image as amulet_image, a.price as unit_price,
               c.category_name
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        LEFT JOIN categories c ON a.categoryId = c.id
        WHERE oi.order_id = :order_id AND a.sellerId = :seller_id
    ");
    $stmt->execute([':order_id' => $order_id, ':seller_id' => $seller_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count_waiting = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN amulets a ON oi.amulet_id=a.id LEFT JOIN payments p ON o.id=p.order_id WHERE a.sellerId=:id AND p.status='waiting'");
    $count_waiting->execute([':id' => $seller_id]);
    $n_waiting = $count_waiting->fetchColumn();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$seller_subtotal = array_sum(array_map(function($i) {
    return $i['price'] * $i['quantity'];
}, $items));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>รายละเอียด Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?> - Cenmulet Seller</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');
        body { font-family: "Kanit", sans-serif; background: #f3f4f6; }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .info-card h3 {
            font-size: 14px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-card h3 i { color: #10b981; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .info-label { color: #9ca3af; }
        .info-value { color: #1a1a1a; font-weight: 500; text-align: right; max-width: 60%; }

        .slip-img {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            cursor: zoom-in;
            transition: transform .2s;
        }
        .slip-img:hover { transform: scale(1.02); }

        .items-table th { font-size: 13px; }
        .product-row { display: flex; align-items: center; gap: 12px; }
        .product-thumb {
            width: 52px; height: 52px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e5e7eb;
            background: #f3f4f6;
        }

        .tracking-box {
            background: #f0fdf4;
            border: 2px solid #a7f3d0;
            border-radius: 10px;
            padding: 14px 18px;
            font-family: monospace;
            font-size: 15px;
            font-weight: 700;
            color: #059669;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 18px;
            font-weight: 500;
            transition: color .2s;
        }
        .back-btn:hover { color: #10b981; }

        /* Lightbox */
        #slipModal {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.78);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        #slipModal img {
            max-width: 90vw;
            max-height: 88vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        @media (max-width: 768px) {
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="">
            <h2>Cenmulet</h2>
            <p>แดชบอร์ดร้านค้า</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
            <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
            <li><a href="/views/seller/orders.php" class="active"><i class="fa-solid fa-shopping-cart"></i> การเช่า
                <?php if ($n_waiting > 0): ?>
                <span style="background:#ef4444;color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;margin-left:auto"><?php echo $n_waiting; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
            <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <div class="top-bar">
            <div>
                <h1><i class="fa-solid fa-file-invoice"></i> รายละเอียดการเช่า</h1>
            </div>
            <a href="/views/seller/orders.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> กลับ
            </a>
        </div>

        <a href="/views/seller/orders.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าการเช่า
        </a>

        <!-- Order Header -->
        <div class="info-card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:22px;font-weight:800;color:#1a1a1a">
                    Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
                <div style="font-size:13px;color:#9ca3af;margin-top:4px">
                    <i class="fa-solid fa-calendar"></i>
                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?> น.
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <?php if ($order['payment_status'] === 'waiting'): ?>
                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> รอยืนยันชำระ</span>
                <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                    <span class="badge badge-info"><i class="fa-solid fa-check"></i> ยืนยันชำระแล้ว</span>
                <?php elseif ($order['payment_status'] === 'rejected'): ?>
                    <span class="badge badge-danger"><i class="fa-solid fa-times"></i> ปฏิเสธ</span>
                <?php endif; ?>

                <?php if ($order['status'] === 'completed'): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> เสร็จสิ้น</span>
                <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                    <span class="badge badge-info"><i class="fa-solid fa-truck"></i> กำลังจัดส่ง</span>
                <?php else: ?>
                    <span class="badge badge-warning"><i class="fa-solid fa-hourglass"></i> รอดำเนินการ</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Grid -->
        <div class="detail-grid">

            <!-- ข้อมูลผู้เช่า -->
            <div class="info-card">
                <h3><i class="fa-solid fa-user"></i> ข้อมูลผู้เช่า</h3>
                <div class="info-row">
                    <span class="info-label">ชื่อ-นามสกุล</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['buyer_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">เบอร์โทรศัพท์</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['buyer_tel']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ที่อยู่จัดส่ง</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($order['buyer_address'] ?? '-')); ?></span>
                </div>
            </div>

            <!-- ข้อมูลการชำระเงิน -->
            <div class="info-card">
                <h3><i class="fa-solid fa-money-bill-wave"></i> การชำระเงิน</h3>
                <div class="info-row">
                    <span class="info-label">ยอดที่ต้องชำระ</span>
                    <span class="info-value" style="color:#10b981;font-size:18px;font-weight:800">
                        ฿<?php echo number_format($order['total_price'], 2); ?>
                    </span>
                </div>
                <?php if ($order['confirmed_at']): ?>
                <div class="info-row">
                    <span class="info-label">ยืนยันเมื่อ</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['confirmed_at'])); ?> น.</span>
                </div>
                <?php endif; ?>
                <div style="margin-top:14px">
                    <?php if ($order['slip_image']): ?>
                    <img src="/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>"
                         class="slip-img"
                         onclick="openSlip('/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>')"
                         alt="สลิปการโอนเงิน">
                    <?php else: ?>
                    <div style="text-align:center;padding:28px;background:#f9fafb;border-radius:10px;border:2px dashed #e5e7eb;color:#9ca3af">
                        <i class="fa-solid fa-file-image" style="font-size:28px;margin-bottom:8px;display:block"></i>
                        ยังไม่มีสลิปการโอนเงิน
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- เลขพัสดุ -->
        <?php if ($order['payment_status'] === 'confirmed'): ?>
        <div class="info-card" style="margin-bottom:20px">
            <h3><i class="fa-solid fa-truck"></i> ข้อมูลการจัดส่ง</h3>
            <?php if (!empty($order['tracking_number'])): ?>
            <div class="tracking-box">
                <i class="fa-solid fa-barcode"></i>
                <?php echo htmlspecialchars($order['tracking_number']); ?>
            </div>
            <?php else: ?>
            <!-- ฟอร์มกรอกเลขพัสดุ -->
            <form action="/seller/update_tracking.php" method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <label style="font-size:13px;color:#6b7280;display:block;margin-bottom:6px">เลขพัสดุ <span style="color:#ef4444">*</span></label>
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <input type="text" name="tracking_number"
                           placeholder="กรอกเลขพัสดุ เช่น EF123456789TH"
                           style="width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit"
                           required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> บันทึกเลขพัสดุ
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- รายการพระเครื่อง -->
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
                <h2><i class="fa-solid fa-box"></i> รายการพระเครื่อง (<?php echo count($items); ?> รายการ)</h2>
                <strong style="color:#10b981;font-size:16px">฿<?php echo number_format($seller_subtotal, 2); ?></strong>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>พระเครื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>ราคาต่อชิ้น</th>
                            <th>จำนวน</th>
                            <th>รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="product-row">
                                    <?php if ($item['amulet_image']): ?>
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($item['amulet_image']); ?>"
                                         class="product-thumb" alt="<?php echo htmlspecialchars($item['amulet_name']); ?>">
                                    <?php else: ?>
                                    <div class="product-thumb" style="display:flex;align-items:center;justify-content:center;color:#9ca3af">
                                        <i class="fa-solid fa-image"></i>
                                    </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($item['amulet_name']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <?php if ($item['category_name']): ?>
                                <span style="background:#e0e7ff;color:#6366f1;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600">
                                    <?php echo htmlspecialchars($item['category_name']); ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#9ca3af">-</span>
                                <?php endif; ?>
                            </td>
                            <td>฿<?php echo number_format($item['price'], 2); ?></td>
                            <td style="text-align:center;font-weight:600"><?php echo $item['quantity']; ?></td>
                            <td><strong style="color:#10b981">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f9fafb">
                            <td colspan="4" style="text-align:right;font-weight:700;font-size:15px;padding:14px 12px">ยอดรวมพระเครื่องของร้าน</td>
                            <td style="font-weight:800;font-size:16px;color:#10b981">฿<?php echo number_format($seller_subtotal, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap">
            <?php if ($order['payment_status'] === 'waiting'): ?>
            <form action="/views/seller/confirm_payment.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-primary"
                        onclick="return confirm('ยืนยันการชำระเงินสำหรับ Order #<?php echo str_pad($order['id'],6,'0',STR_PAD_LEFT); ?> ?')">
                    <i class="fa-solid fa-check"></i> ยืนยันการชำระเงิน
                </button>
            </form>
            <form action="/views/seller/confirm_payment.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('ปฏิเสธการชำระเงินนี้?')">
                    <i class="fa-solid fa-times"></i> ปฏิเสธ
                </button>
            </form>
            <?php endif; ?>
            <a href="/views/seller/orders.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> กลับ
            </a>
        </div>

    </main>
</div>

<!-- Slip Lightbox -->
<div id="slipModal" onclick="closeSlip()">
    <img id="slipImg" src="" alt="สลิปการโอนเงิน">
</div>

<script>
function openSlip(src) {
    document.getElementById('slipImg').src = src;
    const m = document.getElementById('slipModal');
    m.style.display = 'flex';
}
function closeSlip() {
    document.getElementById('slipModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSlip();
});
</script>
</body>
</html>