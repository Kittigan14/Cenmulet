<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    // สถิติพื้นฐาน
    $total_users = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    $total_sellers = $db->query("SELECT COUNT(*) as count FROM sellers")->fetch()['count'];
    $total_products = $db->query("SELECT COUNT(*) as count FROM amulets")->fetch()['count'];
    $total_orders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
    
    // สถิติการชำระเงิน
    $pending_payments = $db->query("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE status = 'waiting'
    ")->fetch()['count'];
    
    $confirmed_payments = $db->query("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE status = 'confirmed'
    ")->fetch()['count'];
    
    // สถิติการจัดส่ง
    $pending_delivery = $db->query("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = 'pending'
    ")->fetch()['count'];
    
    $completed_delivery = $db->query("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status = 'completed'
    ")->fetch()['count'];
    
    // รายได้รวม
    $total_revenue = $db->query("
        SELECT COALESCE(SUM(total_price), 0) as total 
        FROM orders 
        WHERE status = 'completed'
    ")->fetch()['total'];
    
    // รายได้วันนี้
    $today_revenue = $db->query("
        SELECT COALESCE(SUM(total_price), 0) as total 
        FROM orders 
        WHERE DATE(created_at) = DATE('now') AND status = 'completed'
    ")->fetch()['total'];
    
    // คำสั่งซื้อล่าสุด
    $recent_orders = $db->query("
        SELECT o.*, u.fullname, p.status as payment_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        ORDER BY o.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // สินค้ายอดนิยม (Top 5)
    $top_products = $db->query("
        SELECT a.amulet_name, s.store_name, 
               COUNT(oi.id) as order_count,
               SUM(oi.quantity) as total_sold
        FROM amulets a
        JOIN order_items oi ON a.id = oi.amulet_id
        LEFT JOIN sellers s ON a.sellerId = s.id
        GROUP BY a.id
        ORDER BY total_sold DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>แดชบอร์ดผู้ดูแลระบบ - Cenmulet</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
        }

        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .stat-icon.purple {
            background: #ede9fe;
            color: #7c3aed;
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

        .stat-icon.red {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-icon.yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.indigo {
            background: #e0e7ff;
            color: #6366f1;
        }

        .stat-icon.pink {
            background: #fce7f3;
            color: #ec4899;
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

        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

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
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
        }

        table td {
            padding: 12px;
            border-top: 1px solid #f3f4f6;
            font-size: 14px;
            color: #1a1a1a;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #f59e0b;
        }

        .badge-confirmed {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-completed {
            background: #d1fae5;
            color: #059669;
        }

        .top-product-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .top-product-item:last-child {
            margin-bottom: 0;
        }

        .top-product-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #667eea;
            color: #fff;
            font-weight: bold;
            margin-right: 12px;
        }

        .top-product-name {
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .top-product-store {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .top-product-stats {
            font-size: 13px;
            color: #10b981;
            font-weight: 600;
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

        @media (max-width: 1200px) {
            .content-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="/public/images/image.png" alt="" width="64px">
                <h2>Cenmulet</h2>
                <p>แดชบอร์ดผู้ดูแลระบบ</p>
            </div>

            <div class="user-info">
                <h3><?php echo htmlspecialchars($admin['fullname']); ?></h3>
                <p>ผู้ดูแลระบบ</p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="/views/admin/dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/admin/users.php"><i class="fa-solid fa-users"></i> จัดการผู้ใช้</a></li>
                <li><a href="/views/admin/sellers.php"><i class="fa-solid fa-store"></i> จัดการผู้ขาย</a></li>
                <li><a href="/views/admin/products.php"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/admin/categories.php"><i class="fa-solid fa-tags"></i> จัดการหมวดหมู่</a></li>
                <li><a href="/views/admin/orders.php"><i class="fa-solid fa-shopping-cart"></i> จัดการคำสั่งซื้อ</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>แดชบอร์ดภาพรวม</h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_sellers); ?></div>
                            <div class="stat-label">ผู้ขายทั้งหมด</div>
                        </div>
                        <div class="stat-icon green">
                            <i class="fa-solid fa-store"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_products); ?></div>
                            <div class="stat-label">สินค้าทั้งหมด</div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                            <div class="stat-label">คำสั่งซื้อทั้งหมด</div>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fa-solid fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($pending_payments); ?></div>
                            <div class="stat-label">รอตรวจสอบการชำระเงิน</div>
                        </div>
                        <div class="stat-icon yellow">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($confirmed_payments); ?></div>
                            <div class="stat-label">ยืนยันการชำระเงินแล้ว</div>
                        </div>
                        <div class="stat-icon indigo">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($pending_delivery); ?></div>
                            <div class="stat-label">รอการจัดส่ง</div>
                        </div>
                        <div class="stat-icon pink">
                            <i class="fa-solid fa-truck"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">฿<?php echo number_format($total_revenue, 2); ?></div>
                            <div class="stat-label">รายได้รวมทั้งหมด</div>
                        </div>
                        <div class="stat-icon red">
                            <i class="fa-solid fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-row">
                <!-- คำสั่งซื้อล่าสุด -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อล่าสุด</h2>
                        <a href="/views/admin/orders.php" style="color: #667eea; text-decoration: none; font-size: 14px;">
                            ดูทั้งหมด →
                        </a>
                    </div>

                    <?php if (count($recent_orders) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>รหัส</th>
                                    <th>ผู้ซื้อ</th>
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
                                                <span class="badge badge-pending">-</span>
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
                            <p>ยังไม่มีคำสั่งซื้อ</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- สินค้ายอดนิยม -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-fire"></i> สินค้ายอดนิยม</h2>
                    </div>

                    <?php if (count($top_products) > 0): ?>
                        <?php $rank = 1; foreach ($top_products as $product): ?>
                            <div class="top-product-item">
                                <span class="top-product-rank"><?php echo $rank++; ?></span>
                                <div style="display: inline-block; vertical-align: top; width: calc(100% - 50px);">
                                    <div class="top-product-name"><?php echo htmlspecialchars($product['amulet_name']); ?></div>
                                    <div class="top-product-store">
                                        <i class="fa-solid fa-store"></i>
                                        <?php echo htmlspecialchars($product['store_name'] ?? 'ไม่ระบุ'); ?>
                                    </div>
                                    <div class="top-product-stats">
                                        <i class="fa-solid fa-shopping-bag"></i>
                                        ขายไปแล้ว <?php echo number_format($product['total_sold']); ?> ชิ้น
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-box"></i>
                            <p>ยังไม่มีข้อมูล</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>