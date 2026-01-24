<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

// ตรวจสอบว่า login และเป็น seller หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ขาย
try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// ดึงสถิติต่างๆ
try {
    // จำนวนสินค้าทั้งหมด
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM amulets WHERE sellerId = :seller_id");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // จำนวนคำสั่งซื้อ
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE a.sellerId = :seller_id
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ยอดขายรวม
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total 
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN orders o ON oi.order_id = o.id
        WHERE a.sellerId = :seller_id AND o.status = 'completed'
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // สินค้าของผู้ขาย (5 รายการล่าสุด)
    $stmt = $db->prepare("
        SELECT a.*, c.category_name 
        FROM amulets a
        LEFT JOIN categories c ON a.categoryId = c.id
        WHERE a.sellerId = :seller_id
        ORDER BY a.id DESC
        LIMIT 5
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>แดชบอร์ดผู้ขาย - Cenmulet</title>
    <style>
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

        /* Sidebar */
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

        /* Main Content */
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

        .top-bar-actions {
            display: flex;
            gap: 15px;
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

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-info h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .stat-info p {
            font-size: 32px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.green {
            background: #d1fae5;
            color: #059669;
        }

        .stat-icon.blue {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-icon.orange {
            background: #fed7aa;
            color: #f59e0b;
        }

        /* Recent Products Table */
        .content-section {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: #1a1a1a;
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
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
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
            padding: 40px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Cenmulet</h2>
                <p>แดชบอร์ดผู้ขาย</p>
            </div>

            <div class="user-info">
                <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
                <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="/views/seller/dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มสินค้า</a></li>
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
                <li><a href="/views/seller/profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/views/index.php"><i class="fa-solid fa-home"></i> กลับหน้าแรก</a></li>
                <li><a href="/auth/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>แดชบอร์ด</h1>
                <div class="top-bar-actions">
                    <a href="/views/seller/add_product.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> เพิ่มสินค้าใหม่
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>สินค้าทั้งหมด</h3>
                        <p><?php echo number_format($total_products); ?></p>
                    </div>
                    <div class="stat-icon green">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>คำสั่งซื้อ</h3>
                        <p><?php echo number_format($total_orders); ?></p>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-shopping-cart"></i>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>ยอดขายรวม</h3>
                        <p>฿<?php echo number_format($total_sales, 2); ?></p>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Products -->
            <div class="content-section">
                <div class="section-header">
                    <h2>สินค้าล่าสุด</h2>
                    <a href="/views/seller/products.php" class="btn btn-primary">ดูทั้งหมด</a>
                </div>

                <?php if (count($recent_products) > 0): ?>
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
                            <?php foreach ($recent_products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image']): ?>
                                            <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="" class="product-img">
                                        <?php else: ?>
                                            <div class="product-img" style="background: #e5e7eb; display: flex; align-items: center; justify-content: center;">
                                                <i class="fa-solid fa-image" style="color: #9ca3af;"></i>
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
                                            <a href="/views/seller/edit_product.php?id=<?php echo $product['id']; ?>" class="btn-icon edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="/views/seller/delete_product.php?id=<?php echo $product['id']; ?>" 
                                               class="btn-icon delete" 
                                               onclick="return confirm('คุณต้องการลบสินค้านี้หรือไม่?')">
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
                        <h3>ยังไม่มีสินค้า</h3>
                        <p>เริ่มต้นเพิ่มสินค้าของคุณเลย</p>
                        <a href="/views/seller/add_product.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fa-solid fa-plus"></i> เพิ่มสินค้าแรก
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>