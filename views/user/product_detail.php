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

                <!-- Image -->
                <div class="product-image-panel">
                    <div class="main-image">
                        <?php if ($product['image']): ?>
                            <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>"
                                 alt="<?php echo htmlspecialchars($product['amulet_name']); ?>">
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

    <script>
        const maxQty = <?php echo $product['quantity']; ?>;
        const qtyInput = document.getElementById('quantity');

        function increaseQty() {
            const v = parseInt(qtyInput.value);
            if (v < maxQty) qtyInput.value = v + 1;
        }

        function decreaseQty() {
            const v = parseInt(qtyInput.value);
            if (v > 1) qtyInput.value = v - 1;
        }

        qtyInput.addEventListener('keydown', e => e.preventDefault());
    </script>
</body>
</html>