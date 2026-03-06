<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: /views/user/home.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("
        SELECT a.*, c.category_name, s.store_name, s.fullname as seller_name
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers   s ON a.sellerId   = s.id
        WHERE a.id = :id AND a.quantity > 0
    ");
    $stmt->execute([':id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: /views/user/home.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $cart_count = 0;
}

/* ── ดึงรูปทั้งหมดจาก amulet_images ── */
try {
    $stmt = $db->prepare("SELECT image FROM amulet_images WHERE amulet_id = :id ORDER BY sort_order ASC");
    $stmt->execute([':id' => $product_id]);
    $product_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($product_images) && $product['image']) {
        $product_images = [$product['image']];
    }
} catch (PDOException $e) {
    $product_images = $product['image'] ? [$product['image']] : [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/product_detail.css">
    <title><?php echo htmlspecialchars($product['amulet_name']); ?> - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span class="separator"><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
            <span><?php echo htmlspecialchars($product['amulet_name']); ?></span>
        </nav>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>เพิ่มสินค้าลงตะกร้าสำเร็จ!</span>
            </div>
        <?php endif; ?>

        <div class="product-detail-card">
            <div class="product-layout">

                <!-- Image Slider -->
                <div class="product-image-panel">
                    <div class="main-image" id="productSlider">
                        <?php if (!empty($product_images)): ?>
                            <!-- Slides -->
                            <div class="pd-slider-track">
                                <?php foreach ($product_images as $img): ?>
                                <div class="pd-slide">
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($img); ?>"
                                         alt="<?php echo htmlspecialchars($product['amulet_name']); ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($product_images) > 1): ?>
                            <!-- Prev / Next -->
                            <button class="pd-slider-btn pd-prev" onclick="pdSlideMove(-1)" aria-label="ก่อนหน้า">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <button class="pd-slider-btn pd-next" onclick="pdSlideMove(1)" aria-label="ถัดไป">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>

                            <!-- Counter badge -->
                            <div class="pd-counter">
                                <span id="pdCurrent">1</span> / <?php echo count($product_images); ?>
                            </div>

                            <!-- Thumbnail strip -->
                            <div class="pd-thumbs">
                                <?php foreach ($product_images as $ti => $img): ?>
                                <div class="pd-thumb <?php echo $ti === 0 ? 'active' : ''; ?>"
                                     onclick="pdSlideTo(<?php echo $ti; ?>)">
                                    <img src="/uploads/amulets/<?php echo htmlspecialchars($img); ?>"
                                         alt="thumb <?php echo $ti+1; ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <i class="fa-solid fa-image img-placeholder"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info -->
                <div class="product-info-panel">
                    <span class="product-cat-badge">
                        <i class="fa-solid fa-tag"></i>
                        <?php echo htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?>
                    </span>

                    <h1 class="product-title"><?php echo htmlspecialchars($product['amulet_name']); ?></h1>

                    <div class="seller-card">
                        <div class="seller-icon"><i class="fa-solid fa-store"></i></div>
                        <div>
                            <div class="seller-name"><?php echo htmlspecialchars($product['store_name'] ?? 'ร้านค้า'); ?></div>
                            <div class="seller-label">ผู้ขาย: <?php echo htmlspecialchars($product['seller_name'] ?? 'ไม่ระบุ'); ?></div>
                        </div>
                    </div>

                    <div class="price-box">
                        <div class="price-label">ราคา</div>
                        <div class="price-value">฿<?php echo number_format($product['price'], 2); ?></div>
                        <span class="stock-pill">
                            <i class="fa-solid fa-check-circle"></i>
                            มีสินค้า <?php echo number_format($product['quantity']); ?> ชิ้น
                        </span>
                    </div>

                    <form action="/user/add_to_cart_process.php" method="POST">
                        <input type="hidden" name="amulet_id" value="<?php echo $product['id']; ?>">

                        <div class="qty-section">
                            <label for="quantity">จำนวน</label>
                            <div class="qty-selector">
                                <button type="button" class="qty-btn" onclick="decreaseQty()">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <input type="number"
                                       id="quantity"
                                       name="quantity"
                                       class="qty-num"
                                       value="1"
                                       min="1"
                                       max="<?php echo $product['quantity']; ?>"
                                       readonly>
                                <button type="button" class="qty-btn" onclick="increaseQty()">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="product-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-cart-plus"></i>
                                เพิ่มลงตะกร้า
                            </button>
                            <a href="/views/user/home.php" class="btn btn-secondary">
                                <i class="fa-solid fa-arrow-left"></i>
                                กลับ
                            </a>
                        </div>
                    </form>

                    <div class="product-desc">
                        <h3 class="desc-title">
                            <i class="fa-solid fa-info-circle"></i>
                            รายละเอียดสินค้า
                        </h3>
                        <div class="desc-body"><?php echo htmlspecialchars($product['source']); ?></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        /* ── Product Detail Slider ── */
        .main-image {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            background: #f3f4f6;
            aspect-ratio: 1 / 1;
        }

        .pd-slider-track {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(.4,0,.2,1);
        }

        .pd-slide {
            min-width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pd-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Prev / Next buttons */
        .pd-slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.42);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            z-index: 3;
            transition: background 0.2s, transform 0.2s;
        }
        .pd-slider-btn:hover {
            background: rgba(0,0,0,0.68);
            transform: translateY(-50%) scale(1.08);
        }
        .pd-prev { left: 12px; }
        .pd-next { right: 12px; }

        /* Counter badge */
        .pd-counter {
            position: absolute;
            top: 12px;
            right: 14px;
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 99px;
            z-index: 3;
            letter-spacing: 0.3px;
        }

        /* Thumbnail strip */
        .pd-thumbs {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 7px;
            z-index: 3;
            max-width: calc(100% - 24px);
            overflow-x: auto;
            padding: 3px 2px;
            scrollbar-width: none;
        }
        .pd-thumbs::-webkit-scrollbar { display: none; }

        .pd-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2.5px solid rgba(255,255,255,0.5);
            flex-shrink: 0;
            transition: border-color 0.2s, transform 0.2s;
            background: #fff;
        }
        .pd-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .pd-thumb.active {
            border-color: var(--primary, #FF8C00);
            transform: scale(1.08);
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .pd-thumb:hover { border-color: rgba(255,255,255,0.9); }
    </style>

    <script>
        /* ── Quantity ── */
        const maxQty   = <?php echo $product['quantity']; ?>;
        const qtyInput = document.getElementById('quantity');

        function increaseQty() {
            const v = parseInt(qtyInput.value);
            if (v < maxQty) qtyInput.value = v + 1;
        }
        function decreaseQty() {
            const v = parseInt(qtyInput.value);
            if (v > 1) qtyInput.value = v - 1;
        }
        qtyInput.addEventListener('keydown', function(e) { e.preventDefault(); });

        /* ── Product Image Slider ── */
        let pdIdx   = 0;
        const pdTotal = <?php echo count($product_images); ?>;

        function pdSlideTo(idx) {
            pdIdx = ((idx % pdTotal) + pdTotal) % pdTotal;

            const track = document.querySelector('.pd-slider-track');
            if (track) track.style.transform = 'translateX(-' + (pdIdx * 100) + '%)';

            document.querySelectorAll('.pd-thumb').forEach(function(t, i) {
                t.classList.toggle('active', i === pdIdx);
            });

            const counter = document.getElementById('pdCurrent');
            if (counter) counter.textContent = pdIdx + 1;
        }

        function pdSlideMove(dir) { pdSlideTo(pdIdx + dir); }

        /* Swipe support */
        (function() {
            const el = document.getElementById('productSlider');
            if (!el) return;
            var sx = 0;
            el.addEventListener('touchstart', function(e) { sx = e.touches[0].clientX; }, { passive: true });
            el.addEventListener('touchend',   function(e) {
                var diff = sx - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) pdSlideMove(diff > 0 ? 1 : -1);
            });
        })();
    </script>
</body>
</html>
