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
    // พระเครื่องทั้งหมด
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM amulets WHERE sellerId = :seller_id");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // คำสั่งเช่าทั้งหมด
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        WHERE a.sellerId = :seller_id
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // คำสั่งเช่ารอตรวจสอบ
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT o.id) as total 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN payments p ON o.id = p.order_id
        WHERE a.sellerId = :seller_id AND p.status = 'waiting'
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ยอดขายรวม (เฉพาะที่สำเร็จ)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total 
        FROM order_items oi
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN orders o ON oi.order_id = o.id
        WHERE a.sellerId = :seller_id AND o.status = 'completed'
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // พระเครื่องใกล้หมด (น้อยกว่า 5 ชิ้น)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM amulets 
        WHERE sellerId = :seller_id AND quantity < 5 AND quantity > 0
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // พระเครื่องล่าสุด
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
    
    // คำสั่งเช่าล่าสุด
    $stmt = $db->prepare("
        SELECT DISTINCT o.*, u.fullname, p.status as payment_status
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE a.sellerId = :seller_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>แดชบอร์ดผู้ขาย - Cenmulet</title>
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

        .sidebar-user {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .sidebar-user h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .sidebar-user p {
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

        .btn-primary {
            padding: 10px 20px;
            background: #10b981;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
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

        .stat-icon.yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        .content-section {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
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

        .badge-danger {
            background: #fee2e2;
            color: #ef4444;
        }

        .badge-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #2563eb;
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
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/public/images/image.png" alt="" width="64px">
                <h2>Cenmulet</h2>
                <p>แดชบอร์ดผู้ขาย</p>
            </div>

            <div class="sidebar-user">
                <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
                <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="/views/seller/dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
                <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่า</a></li>
                <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>แดชบอร์ด</h1>
                <a href="/views/seller/add_product.php" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> เพิ่มพระเครื่องใหม่
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_products); ?></div>
                            <div class="stat-label">พระเครื่องทั้งหมด</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                            <div class="stat-label">คำสั่งเช่าทั้งหมด</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fa-solid fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($pending_orders); ?></div>
                            <div class="stat-label">รอตรวจสอบ</div>
                        </div>
                        <div class="stat-icon yellow">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">฿<?php echo number_format($total_sales, 2); ?></div>
                            <div class="stat-label">ยอดขายรวม</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fa-solid fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>

                <?php if ($low_stock > 0): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($low_stock); ?></div>
                            <div class="stat-label">พระเครื่องใกล้หมด</div>
                        </div>
                        <div class="stat-icon red">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่าล่าสุด</h2>
                    <a href="/views/seller/orders.php" class="btn-primary">ดูทั้งหมด</a>
                </div>

                <?php if (count($recent_orders) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ผู้สั่งเช่า</th>
                                <th>ยอดรวม</th>
                                <th>สถานะชำระเงิน</th>
                                <th>วันที่</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['fullname']); ?></td>
                                    <td><strong style="color: #10b981;">฿<?php echo number_format($order['total_price'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($order['payment_status'] === 'waiting'): ?>
                                            <span class="badge badge-pending">รอตรวจสอบ</span>
                                        <?php elseif ($order['payment_status'] === 'confirmed'): ?>
                                            <span class="badge badge-confirmed">ยืนยันแล้ว</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">ไม่ทราบสถานะ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-shopping-cart"></i>
                        <p>ยังไม่มีคำสั่งเช่า</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- พระเครื่องล่าสุด -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fa-solid fa-box"></i> พระเครื่องล่าสุด</h2>
                    <a href="/views/seller/products.php" class="btn-primary">ดูทั้งหมด</a>
                </div>

                <?php if (count($recent_products) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>รูปภาพ</th>
                                <th>ชื่อพระเครื่อง</th>
                                <th>หมวดหมู่</th>
                                <th>ราคา</th>
                                <th>คงเหลือ</th>
                                <th>สถานะ</th>
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
                                    <td>
                                        <?php if ($product['quantity'] < 5 && $product['quantity'] > 0): ?>
                                            <span style="color: #f59e0b; font-weight: 600;"><?php echo number_format($product['quantity']); ?></span>
                                        <?php else: ?>
                                            <?php echo number_format($product['quantity']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['quantity'] > 5): ?>
                                            <span class="badge badge-success">มีพระเครื่อง</span>
                                        <?php elseif ($product['quantity'] > 0): ?>
                                            <span class="badge badge-warning">ใกล้หมด</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">พระเครื่องหมด</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>ยังไม่มีพระเครื่อง</h3>
                        <p>เริ่มต้นเพิ่มพระเครื่องของคุณเลย</p>
                        <a href="/views/seller/add_product.php" class="btn-primary" style="margin-top: 15px;">
                            <i class="fa-solid fa-plus"></i> เพิ่มพระเครื่องแรก
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>