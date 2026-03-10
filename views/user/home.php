<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

// หน้าโฮมเข้าได้โดยไม่ต้องล็อกอิน
$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';

$user = null;
if ($is_logged_in) {
    try {
        $stmt = $db->prepare("SELECT id, fullname, image FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Home user fetch error: " . $e->getMessage());
    }
    if (!$user) { $is_logged_in = false; }
}

try {
    $stmt = $db->query("SELECT * FROM categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

$search          = $_GET['search']   ?? '';
$category_filter = $_GET['category'] ?? '';

try {
    $sql = "
        SELECT a.*, c.category_name, s.store_name
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers   s ON a.sellerId   = s.id
        WHERE a.quantity > 0 AND (a.is_hidden = 0 OR a.is_hidden IS NULL)
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (a.amulet_name LIKE :search OR a.source LIKE :search OR s.store_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($category_filter)) {
        $sql .= " AND a.categoryId = :category";
        $params[':category'] = $category_filter;
    }

    $sql .= " ORDER BY a.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$cart_count = 0;
if ($is_logged_in) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        $cart_count = 0;
    }
}

$active_page = 'home';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/home.css">
    <title>หน้าแรก - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">

        <!-- Search & Filter -->
        <div class="search-filter-panel">
            <form action="" method="GET" class="search-form">
                <div class="search-input-wrap">
                    <input type="text"
                           name="search"
                           class="search-input"
                           placeholder="ค้นหาพระเครื่อง, แหล่งที่มา, ร้านค้า..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                </div>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                <button type="submit" class="btn-search">
                    <i class="fa-solid fa-search"></i>
                    ค้นหา
                </button>
            </form>

            <div class="category-chips">
                <a href="/views/user/home.php"
                   class="category-chip <?php echo empty($category_filter) ? 'active' : ''; ?>">
                    <i class="fa-solid fa-border-all"></i> ทั้งหมด
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?php echo $cat['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                       class="category-chip <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Results Header -->
        <div class="results-header">
            <h1>
                <?php
                if (!empty($search)) {
                    echo 'ผลการค้นหา: "' . htmlspecialchars($search) . '"';
                } elseif (!empty($category_filter)) {
                    $cat_name = '';
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $category_filter) { $cat_name = $cat['category_name']; break; }
                    }
                    echo 'หมวดหมู่: ' . htmlspecialchars($cat_name);
                } else {
                    echo 'พระเครื่องทั้งหมด';
                }
                ?>
            </h1>
            <span class="result-count">พบ <?php echo count($products); ?> รายการ</span>
        </div>

        <!-- Products -->
        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['image']): ?>
                                <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['amulet_name']); ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-image placeholder-icon"></i>
                            <?php endif; ?>
                        </div>

                        <div class="product-body">
                            <div class="product-cat">
                                <?php echo htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?>
                            </div>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['amulet_name']); ?></h3>
                            <p class="product-source">
                                <i class="fa-solid fa-store" style="color:var(--primary);font-size:11px;"></i>
                                <?php echo htmlspecialchars($product['store_name'] ?? 'ร้านค้า'); ?>
                            </p>

                            <div class="product-footer">
                                <div class="product-price">฿<?php echo number_format($product['price'], 2); ?></div>
                                <div class="product-actions">
                                    <a href="/views/user/product_detail.php?id=<?php echo $product['id']; ?>" class="btn-detail">
                                        <i class="fa-solid fa-eye"></i> ดูเพิ่มเติม
                                    </a>
                                    <?php if ($is_logged_in): ?>
                                    <form action="/user/add_to_cart_process.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="amulet_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="quantity"   value="1">
                                        <button type="submit" class="btn-add-cart" title="เพิ่มลงตะกร้า">
                                            <i class="fa-solid fa-cart-plus"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <a href="/views/auth/login.php" class="btn-add-cart" title="ล็อกอินเพื่อเพิ่มลงตะกร้า">
                                        <i class="fa-solid fa-cart-plus"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="no-products">
                <i class="fa-solid fa-box-open"></i>
                <h2>ไม่พบสินค้า</h2>
                <p>
                    <?php
                    if (!empty($search)) {
                        echo 'ไม่พบสินค้าที่ตรงกับคำค้นหา "' . htmlspecialchars($search) . '"';
                    } else {
                        echo 'ไม่มีสินค้าในหมวดหมู่นี้';
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>