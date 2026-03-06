<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id  = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: /views/user/orders.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

try {
    $stmt = $db->prepare("
        SELECT o.*, p.slip_image, p.status as payment_status
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = :order_id AND o.user_id = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { header("Location: /views/user/orders.php"); exit; }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

try {
    $stmt = $db->prepare("
        SELECT oi.*, a.amulet_name, a.image, c.category_name, s.store_name
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers    s ON a.sellerId   = s.id
        WHERE oi.order_id = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

$amulet_images_map = [];
if (!empty($order_items)) {
    $amulet_ids = array_unique(array_column($order_items, 'amulet_id'));
    try {
        // ดึงรูปทั้งหมดจาก amulet_images ทีละ amulet_id
        $img_stmt = $db->prepare("
            SELECT amulet_id, image
            FROM amulet_images
            WHERE amulet_id = :amulet_id
            ORDER BY sort_order ASC
        ");
        foreach ($amulet_ids as $aid) {
            $img_stmt->execute([':amulet_id' => $aid]);
            $rows = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $amulet_images_map[$aid][] = $row['image'];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("amulet_images query error: " . $e->getMessage());
    }
    // Fallback: ถ้า amulet_images ว่าง ใช้รูปจาก amulets.image แทน
    foreach ($order_items as $item) {
        $aid = $item['amulet_id'];
        if (empty($amulet_images_map[$aid]) && !empty($item['image'])) {
            $amulet_images_map[$aid] = [$item['image']];
        }
    }
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) { $cart_count = 0; }

// Determine status
$status_class = 'status-pending';
$status_text  = 'รอการตรวจสอบ';
$status_icon  = 'fa-clock';

if ($order['payment_status'] === 'confirmed') {
    $status_class = 'status-confirmed';
    $status_text  = 'ยืนยันการชำระเงิน';
    $status_icon  = 'fa-check-circle';
}
if ($order['status'] === 'completed') {
    $status_class = 'status-completed';
    $status_text  = 'จัดส่งสำเร็จ';
    $status_icon  = 'fa-check-double';
} elseif ($order['status'] === 'cancelled') {
    $status_class = 'status-cancelled';
    $status_text  = 'ยกเลิก';
    $status_icon  = 'fa-times-circle';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/order_detail.css">
    <title>คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?> - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span class="separator"><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
            <a href="/views/user/orders.php">คำสั่งซื้อของฉัน</a>
            <span class="separator"><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
            <span>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
        </nav>

        <?php if (isset($_GET['delivery_confirmed'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>ยืนยันการรับสินค้าสำเร็จ! ขอบคุณที่ใช้บริการ</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php
                    if ($_GET['error'] === 'payment_not_confirmed') {
                        echo 'กรุณารอการยืนยันการชำระเงินก่อน';
                    } elseif ($_GET['error'] === 'already_confirmed') {
                        echo 'คำสั่งซื้อนี้ได้รับการยืนยันแล้ว';
                    } else {
                        echo 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="order-detail-header">
            <div>
                <h1>คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h1>
                <div class="order-datetime">
                    <i class="fa-regular fa-calendar"></i>
                    <?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?>
                </div>
            </div>
            <span class="status-badge <?php echo $status_class; ?>">
                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                <?php echo $status_text; ?>
            </span>
        </div>

        <!-- Main Layout -->
        <div class="order-detail-layout">

            <!-- Left Column -->
            <div>
                <!-- Items -->
                <div class="detail-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-box"></i> รายการสินค้า
                    </h2>
                    <?php foreach ($order_items as $idx => $item):
                        $imgs = $amulet_images_map[$item['amulet_id']] ?? [];
                        if (empty($imgs) && $item['image']) $imgs = [$item['image']];
                        $sliderId = 'slider-' . $idx;
                    ?>
                        <div class="order-item-row">
                            <!-- Image Slider -->
                            <div class="order-item-slider" id="<?php echo $sliderId; ?>">
                                <div class="slider-track">
                                    <?php if (!empty($imgs)): ?>
                                        <?php foreach ($imgs as $img): ?>
                                            <div class="slider-slide">
                                                <img src="/uploads/amulets/<?php echo htmlspecialchars($img); ?>" alt="">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="slider-slide slider-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (count($imgs) > 1): ?>
                                    <button class="slider-btn slider-prev" onclick="slideMove('<?php echo $sliderId; ?>', -1)" aria-label="ก่อนหน้า">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                    <button class="slider-btn slider-next" onclick="slideMove('<?php echo $sliderId; ?>', 1)" aria-label="ถัดไป">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                    <div class="slider-dots">
                                        <?php for ($d = 0; $d < count($imgs); $d++): ?>
                                            <span class="slider-dot<?php echo $d === 0 ? ' active' : ''; ?>"
                                                  onclick="slideTo('<?php echo $sliderId; ?>', <?php echo $d; ?>)"></span>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="order-item-body">
                                <div class="order-item-cat"><?php echo htmlspecialchars($item['category_name'] ?? 'ไม่ระบุ'); ?></div>
                                <div class="order-item-name"><?php echo htmlspecialchars($item['amulet_name']); ?></div>
                                <div class="order-item-store">
                                    <i class="fa-solid fa-store" style="font-size:11px;"></i>
                                    <?php echo htmlspecialchars($item['store_name'] ?? 'ร้านค้า'); ?>
                                </div>
                                <div class="order-item-foot">
                                    <span class="order-item-qty">จำนวน: <?php echo $item['quantity']; ?> ชิ้น</span>
                                    <span class="order-item-price">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Timeline -->
                <div class="detail-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-list-check"></i> สถานะการสั่งซื้อ
                    </h2>
                    <div class="status-timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot active"></div>
                            <div class="timeline-content">
                                <h4>สั่งซื้อสำเร็จ</h4>
                                <p><?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot <?php echo ($order['payment_status'] === 'confirmed' || $order['status'] === 'completed') ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h4>ยืนยันการชำระเงิน</h4>
                                <p><?php echo $order['payment_status'] === 'confirmed' ? 'ยืนยันแล้ว' : 'รอการตรวจสอบ'; ?></p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot <?php echo $order['status'] === 'completed' ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h4>จัดส่งสินค้า</h4>
                                <p><?php echo $order['status'] === 'completed' ? 'จัดส่งสำเร็จ' : 'รอดำเนินการ'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Order Summary -->
                <div class="detail-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-file-invoice"></i> สรุปคำสั่งซื้อ
                    </h2>
                    <div class="summary-line">
                        <span>จำนวนสินค้า</span>
                        <span><?php echo count($order_items); ?> รายการ</span>
                    </div>
                    <div class="summary-line">
                        <span>ค่าจัดส่ง</span>
                        <span class="free">ฟรี</span>
                    </div>
                    <div class="summary-grand-total">
                        <span class="label">ยอดรวมทั้งหมด</span>
                        <span class="amount">฿<?php echo number_format($order['total_price'], 2); ?></span>
                    </div>
                </div>

                <!-- Confirm Delivery -->
                <?php if ($order['payment_status'] === 'confirmed' && $order['status'] !== 'completed'): ?>
                <div class="detail-section confirm-delivery-box">
                    <h2 class="section-title">
                        <i class="fa-solid fa-truck"></i> ยืนยันการรับสินค้า
                    </h2>
                    <div class="warn-box">
                        <i class="fa-solid fa-info-circle"></i>
                        กรุณายืนยันเมื่อได้รับสินค้าเรียบร้อยแล้ว เมื่อยืนยันแล้วจะไม่สามารถยกเลิกได้
                    </div>
                    <form action="/user/confirm_delivery.php" method="POST"
                          onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าได้รับสินค้าเรียบร้อยแล้ว?')">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="btn-confirm-delivery">
                            <i class="fa-solid fa-check-circle"></i>
                            ยืนยันการรับสินค้า
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Slip -->
                <?php if ($order['slip_image']): ?>
                <div class="detail-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-receipt"></i> หลักฐานการโอนเงิน
                    </h2>
                    <img src="/uploads/slips/<?php echo htmlspecialchars($order['slip_image']); ?>"
                         alt="Slip"
                         class="slip-preview"
                         onclick="window.open(this.src,'_blank')">
                </div>
                <?php endif; ?>

                <!-- Extra Info -->
                <div class="detail-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-info-circle"></i> ข้อมูลเพิ่มเติม
                    </h2>
                    <div class="info-table">
                        <div class="info-table-row">
                            <span class="key">สถานะการชำระเงิน</span>
                            <span class="val">
                                <?php
                                if ($order['payment_status'] === 'waiting') {
                                    echo 'รอการตรวจสอบ';
                                } elseif ($order['payment_status'] === 'confirmed') {
                                    echo 'ยืนยันแล้ว';
                                } else {
                                    echo 'ไม่ทราบสถานะ';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-table-row">
                            <span class="key">สถานะการจัดส่ง</span>
                            <span class="val">
                                <?php
                                if ($order['status'] === 'pending') {
                                    echo 'รอดำเนินการ';
                                } elseif ($order['status'] === 'completed') {
                                    echo 'จัดส่งสำเร็จ';
                                } elseif ($order['status'] === 'cancelled') {
                                    echo 'ยกเลิก';
                                } else {
                                    echo ucfirst($order['status']);
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <a href="/views/user/orders.php" class="btn-back-page">
                    <i class="fa-solid fa-arrow-left"></i>
                    กลับไปหน้าคำสั่งซื้อ
                </a>
            </div>

        </div>
    </div>

    <script>
    function slideMove(id, dir) {
        const el = document.getElementById(id);
        const slides = el.querySelectorAll('.slider-slide');
        const dots   = el.querySelectorAll('.slider-dot');
        let current  = parseInt(el.dataset.current || 0);
        current = (current + dir + slides.length) % slides.length;
        slideTo(id, current);
    }
    function slideTo(id, index) {
        const el = document.getElementById(id);
        const slides = el.querySelectorAll('.slider-slide');
        const dots   = el.querySelectorAll('.slider-dot');
        const track  = el.querySelector('.slider-track');
        el.dataset.current = index;
        track.style.transform = `translateX(-${index * 100}%)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === index));
    }
    </script>
</body>
</html>