<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

// ตรวจสอบว่า login และเป็น admin หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

// ดึงข้อมูล admin
try {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
    $stmt->execute([':id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// ดึงสถิติต่างๆ
try {
    // จำนวนผู้ใช้
    $total_users = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    
    // จำนวนผู้ขาย
    $total_sellers = $db->query("SELECT COUNT(*) as count FROM sellers")->fetch()['count'];
    
    // จำนวนสินค้า
    $total_products = $db->query("SELECT COUNT(*) as count FROM amulets")->fetch()['count'];
    
    // จำนวนคำสั่งซื้อ
    $total_orders = $db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
    
    // ยอดขายรวม
    $total_revenue = $db->query("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE status = 'completed'")->fetch()['total'];
    
    // คำสั่งซื้อล่าสุด
    $recent_orders = $db->query("
        SELECT o.*, u.fullname 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
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
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>แดชบอร์ดผู้ดูแลระบบ - Cenmulet</title>
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
        }

        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        /* Stats Grid */
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

        /* Content Section */
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

        .badge-completed {
            background: #d1fae5;
            color: #059669;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #ef4444;
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
                <li><a href="/views/index.php"><i class="fa-solid fa-home"></i> กลับหน้าแรก</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>แดชบอร์ดภาพรวม</h1>
            </div>

            <!-- Stats Cards -->
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
                            <div class="stat-value">฿<?php echo number_format($total_revenue, 2); ?></div>
                            <div class="stat-label">รายได้รวม</div>
                        </div>
                        <div class="stat-icon red">
                            <i class="fa-solid fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="content-section">
                <div class="section-header">
                    <h2>คำสั่งซื้อล่าสุด</h2>
                    <a href="/views/admin/orders.php" style="color: #667eea; text-decoration: none; font-size: 14px;">ดูทั้งหมด →</a>
                </div>

                <?php if (count($recent_orders) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสคำสั่งซื้อ</th>
                                <th>ผู้ซื้อ</th>
                                <th>ยอดรวม</th>
                                <th>สถานะ</th>
                                <th>วันที่</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($order['fullname']); ?></td>
                                    <td>฿<?php echo number_format($order['total_price'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = 'badge-pending';
                                        $status_text = 'รอดำเนินการ';
                                        
                                        if ($order['status'] === 'completed') {
                                            $status_class = 'badge-completed';
                                            $status_text = 'สำเร็จ';
                                        } elseif ($order['status'] === 'cancelled') {
                                            $status_class = 'badge-cancelled';
                                            $status_text = 'ยกเลิก';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-shopping-cart"></i>
                        <h3>ยังไม่มีคำสั่งซื้อ</h3>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>