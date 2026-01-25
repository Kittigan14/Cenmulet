<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("
        SELECT a.*, c.category_name 
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        WHERE a.sellerId = :seller_id
        ORDER BY a.id DESC
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการสินค้า - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Kanit", sans-serif;
            background: #f3f4f6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .user-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-info p {
            font-size: 13px;
            opacity: 0.9;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-menu i {
            font-size: 18px;
            width: 20px;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
        }

        .top-bar {
            background: #fff;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #10b981;
            color: #fff;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
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

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .content-section {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f9fafb;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-size: 14px;
            color: #6b7280;
            font-weight: 600;
        }

        table td {
            padding: 12px;
            border-top: 1px solid #f3f4f6;
            font-size: 14px;
            color: #1a1a1a;
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        .no-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d1fae5;
            color: #059669;
        }

        .badge-warning {
            background: #fef3c7;
            color: #f59e0b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-icon.edit {
            background: #dbeafe;
            color: #3b82f6;
        }

        .btn-icon.delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-icon:hover {
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/public/images/image.png" alt="" width="64px">
                <h2>Cenmulet</h2>
                <p>แดชบอร์ดผู้ขาย</p>
            </div>

            <div class="user-info">
                <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
                <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/seller/products.php" class="active"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มสินค้า</a></li>
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
                <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>จัดการสินค้า</h1>
                <a href="/views/seller/add_product.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> เพิ่มสินค้าใหม่
                </a>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>ลบสินค้าสำเร็จ!</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</span>
                </div>
            <?php endif; ?>

            <div class="content-section">
                <?php if (count($products) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>รูปภาพ</th>
                                <th>ชื่อสินค้า</th>
                                <th>หมวดหมู่</th>
                                <th>ราคา</th>
                                <th>คงเหลือ</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="" class="product-img">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['amulet_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                    <td>฿<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo number_format($product['quantity']); ?></td>
                                    <td>
                                        <?php if ($product['quantity'] > 0): ?>
                                            <span class="badge badge-success">มีสินค้า</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">สินค้าหมด</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="/views/seller/edit_product.php?id=<?php echo $product['id']; ?>" class="btn-icon edit" title="แก้ไข">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="/seller/delete_product_process.php?id=<?php echo $product['id']; ?>" 
                                               class="btn-icon delete" 
                                               title="ลบ"
                                               onclick="return confirm('คุณต้องการลบสินค้า \'<?php echo htmlspecialchars($product['amulet_name']); ?>\' หรือไม่?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h2>ยังไม่มีสินค้า</h2>
                        <p>เริ่มต้นเพิ่มสินค้าของคุณเลย</p>
                        <a href="/views/seller/add_product.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> เพิ่มสินค้าแรก
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>