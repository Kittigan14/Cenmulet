<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: /views/user/home.php");
    exit;
}

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// ดึงข้อมูลสินค้า
try {
    $stmt = $db->prepare("
        SELECT a.*, c.category_name, s.store_name, s.fullname as seller_name 
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        LEFT JOIN sellers s ON a.sellerId = s.id
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

// นับจำนวนสินค้าในตะกร้า
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
    <title><?php echo htmlspecialchars($product['amulet_name']); ?> - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&family=Sriracha&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Kanit", sans-serif;
            background: #f9fafb;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .product-detail {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
        }

        .product-image-section {
            position: sticky;
            top: 120px;
            height: fit-content;
        }

        .main-image {
            width: 100%;
            height: 500px;
            background: #f3f4f6;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .main-image i {
            font-size: 80px;
            color: #d1d5db;
        }

        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .category-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #d1fae5;
            color: #059669;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            width: fit-content;
        }

        .product-title {
            font-size: 36px;
            color: #1a1a1a;
            font-weight: 700;
            line-height: 1.3;
        }

        .seller-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .seller-info i {
            font-size: 20px;
            color: #10b981;
        }

        .seller-info div {
            display: flex;
            flex-direction: column;
        }

        .seller-info strong {
            font-size: 14px;
            color: #1a1a1a;
        }

        .seller-info span {
            font-size: 13px;
            color: #6b7280;
        }

        .price-section {
            padding: 25px;
            background: #f0fdf4;
            border-radius: 15px;
            border: 2px solid #10b981;
        }

        .price-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .price-value {
            font-size: 42px;
            color: #10b981;
            font-weight: bold;
        }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .stock-badge {
            padding: 6px 12px;
            background: #d1fae5;
            color: #059669;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .quantity-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .quantity-label {
            font-size: 14px;
            color: #374151;
            font-weight: 600;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #e5e7eb;
            background: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
            color: #1a1a1a;
        }

        .quantity-btn:hover {
            border-color: #10b981;
            color: #10b981;
        }

        .quantity-input {
            width: 80px;
            height: 40px;
            text-align: center;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10b981;
            color: #fff;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .product-description {
            padding-top: 25px;
            border-top: 2px solid #f3f4f6;
        }

        .description-title {
            font-size: 18px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .description-title i {
            color: #10b981;
        }

        .description-content {
            font-size: 15px;
            color: #374151;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #10b981;
        }

        @media (max-width: 768px) {
            .product-layout {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .product-image-section {
                position: relative;
                top: 0;
            }

            .main-image {
                height: 350px;
            }

            .product-title {
                font-size: 28px;
            }

            .price-value {
                font-size: 36px;
            }
        }
    </style>
</head>

<body>
   <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($product['amulet_name']); ?></span>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>เพิ่มสินค้าลงตะกร้าสำเร็จ!</span>
            </div>
        <?php endif; ?>

        <div class="product-detail">
            <div class="product-layout">
                <!-- Product Image -->
                <div class="product-image-section">
                    <div class="main-image">
                        <?php if ($product['image']): ?>
                            <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['amulet_name']); ?>">
                        <?php else: ?>
                            <i class="fa-solid fa-image"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info-section">
                    <div class="category-badge">
                        <?php echo htmlspecialchars($product['category_name'] ?? 'ไม่ระบุหมวดหมู่'); ?>
                    </div>

                    <h1 class="product-title"><?php echo htmlspecialchars($product['amulet_name']); ?></h1>

                    <div class="seller-info">
                        <i class="fa-solid fa-store"></i>
                        <div>
                            <strong><?php echo htmlspecialchars($product['store_name'] ?? 'ร้านค้า'); ?></strong>
                            <span>ผู้ขาย: <?php echo htmlspecialchars($product['seller_name'] ?? 'ไม่ระบุ'); ?></span>
                        </div>
                    </div>

                    <div class="price-section">
                        <div class="price-label">ราคา</div>
                        <div class="price-value">฿<?php echo number_format($product['price'], 2); ?></div>
                        <div class="stock-info">
                            <span class="stock-badge">
                                <i class="fa-solid fa-check-circle"></i> มีสินค้า <?php echo number_format($product['quantity']); ?> ชิ้น
                            </span>
                        </div>
                    </div>

                    <form action="/user/add_to_cart_process.php" method="POST">
                        <input type="hidden" name="amulet_id" value="<?php echo $product['id']; ?>">
                        
                        <div class="quantity-section">
                            <label class="quantity-label">จำนวน</label>
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn" onclick="decreaseQuantity()">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <input type="number" 
                                       id="quantity" 
                                       name="quantity" 
                                       class="quantity-input" 
                                       value="1" 
                                       min="1" 
                                       max="<?php echo $product['quantity']; ?>" 
                                       readonly>
                                <button type="button" class="quantity-btn" onclick="increaseQuantity()">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-cart-plus"></i>
                                เพิ่มลงตะกร้า
                            </button>
                            <a href="/views/user/home.php" class="btn btn-secondary">
                                <i class="fa-solid fa-arrow-left"></i>
                                กลับไปเลือกสินค้า
                            </a>
                        </div>
                    </form>

                    <div class="product-description">
                        <h3 class="description-title">
                            <i class="fa-solid fa-info-circle"></i>
                            รายละเอียดสินค้า
                        </h3>
                        <div class="description-content">
                            <?php echo htmlspecialchars($product['source']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const maxQuantity = <?php echo $product['quantity']; ?>;
        
        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue < maxQuantity) {
                input.value = currentValue + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        }

        // Prevent manual input
        document.getElementById('quantity').addEventListener('keydown', function(e) {
            e.preventDefault();
        });
    </script>
</body>

</html>