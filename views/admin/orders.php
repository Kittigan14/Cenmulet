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

// ดึงคำสั่งซื้อทั้งหมด
try {
    $stmt = $db->query("
        SELECT o.*, u.fullname, u.tel, u.address,
               p.slip_image, p.status as payment_status,
               COUNT(DISTINCT oi.id) as item_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// นับสถานะต่างๆ
$count_pending = 0;
$count_confirmed = 0;
$count_completed = 0;
foreach ($orders as $order) {
    if ($order['payment_status'] === 'waiting') $count_pending++;
    if ($order['payment_status'] === 'confirmed') $count_confirmed++;
    if ($order['status'] === 'completed') $count_completed++;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการคำสั่งซื้อ - Admin - Cenmulet</title>
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

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-mini {
            background: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-mini-info h4 {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .stat-mini-info p {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a1a;
        }

        .stat-mini-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-mini-icon.yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-mini-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .stat-mini-icon.green {
            background: #d1fae5;
            color: #059669;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            border: 2px solid #e5e7eb;
            background: #fff;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #667eea;
            color: #fff;
            border-color: #667eea;
        }

        .orders-table {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
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
            padding: 15px;
            text-align: left;
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
        }

        table td {
            padding: 15px;
            border-top: 1px solid #f3f4f6;
            font-size: 14px;
            color: #1a1a1a;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-confirmed {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-completed {
            background: #d1fae5;
            color: #059669;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-icon.view {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-icon.approve {
            background: #d1fae5;
            color: #059669;
        }

        .btn-icon.slip {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-icon:hover {
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            background: none;
            border: none;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-body img {
            width: 100%;
            border-radius: 10px;
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
                <li><a href="/views/admin/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/admin/users.php"><i class="fa-solid fa-users"></i> จัดการผู้ใช้</a></li>
                <li><a href="/views/admin/sellers.php"><i class="fa-solid fa-store"></i> จัดการผู้ขาย</a></li>
                <li><a href="/views/admin/products.php"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/admin/categories.php"><i class="fa-solid fa-tags"></i> จัดการหมวดหมู่</a></li>
                <li><a href="/views/admin/orders.php" class="active"><i class="fa-solid fa-shopping-cart"></i> จัดการคำสั่งซื้อ</a></li>
                <li><a href="/views/index.php"><i class="fa-solid fa-home"></i> กลับหน้าแรก</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>จัดการคำสั่งซื้อทั้งหมด</h1>
            </div>

            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="stat-mini-info">
                        <h4>รอตรวจสอบ</h4>
                        <p><?php echo $count_pending; ?></p>
                    </div>
                    <div class="stat-mini-icon yellow">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>

                <div class="stat-mini">
                    <div class="stat-mini-info">
                        <h4>ยืนยันแล้ว</h4>
                        <p><?php echo $count_confirmed; ?></p>
                    </div>
                    <div class="stat-mini-icon blue">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                </div>

                <div class="stat-mini">
                    <div class="stat-mini-info">
                        <h4>สำเร็จ</h4>
                        <p><?php echo $count_completed; ?></p>
                    </div>
                    <div class="stat-mini-icon green">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                </div>
            </div>

            <div class="filter-tabs">
                <button class="tab-btn active" onclick="filterOrders('all')">
                    ทั้งหมด (<?php echo count($orders); ?>)
                </button>
                <button class="tab-btn" onclick="filterOrders('pending')">
                    รอตรวจสอบ (<?php echo $count_pending; ?>)
                </button>
                <button class="tab-btn" onclick="filterOrders('confirmed')">
                    ยืนยันแล้ว (<?php echo $count_confirmed; ?>)
                </button>
                <button class="tab-btn" onclick="filterOrders('completed')">
                    สำเร็จ (<?php echo $count_completed; ?>)
                </button>
            </div>

            <?php if (count($orders) > 0): ?>
                <div class="orders-table">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ผู้สั่งซื้อ</th>
                                <th>เบอร์โทร</th>
                                <th>จำนวน</th>
                                <th>ยอดรวม</th>
                                <th>สถานะชำระเงิน</th>
                                <th>สถานะจัดส่ง</th>
                                <th>วันที่</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $payment_class = 'status-pending';
                                $payment_text = 'รอตรวจสอบ';
                                $payment_icon = 'fa-clock';
                                $data_status = 'pending';
                                
                                if ($order['payment_status'] === 'confirmed') {
                                    $payment_class = 'status-confirmed';
                                    $payment_text = 'ยืนยันแล้ว';
                                    $payment_icon = 'fa-check-circle';
                                    $data_status = 'confirmed';
                                }

                                $delivery_class = 'status-pending';
                                $delivery_text = 'รอดำเนินการ';
                                if ($order['status'] === 'completed') {
                                    $delivery_class = 'status-completed';
                                    $delivery_text = 'สำเร็จ';
                                    $data_status = 'completed';
                                }
                                ?>
                                <tr data-status="<?php echo $data_status; ?>">
                                    <td><strong>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($order['tel']); ?></td>
                                    <td><?php echo $order['item_count']; ?> รายการ</td>
                                    <td><strong style="color: #10b981;">฿<?php echo number_format($order['total_price'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge <?php echo $payment_class; ?>">
                                            <i class="fa-solid <?php echo $payment_icon; ?>"></i>
                                            <?php echo $payment_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $delivery_class; ?>">
                                            <?php echo $delivery_text; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="/views/admin/order_detail.php?id=<?php echo $order['id']; ?>" 
                                               class="btn-icon view" 
                                               title="ดูรายละเอียด">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if ($order['payment_status'] === 'waiting'): ?>
                                            <form action="/admin/approve_payment.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" 
                                                        class="btn-icon approve" 
                                                        title="อนุมัติการชำระเงิน"
                                                        onclick="return confirm('ยืนยันการอนุมัติการชำระเงิน?')">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($order['slip_image']): ?>
                                            <button onclick="showSlip('<?php echo htmlspecialchars($order['slip_image']); ?>')" 
                                                    class="btn-icon slip"
                                                    title="ดูสลิป">
                                                <i class="fa-solid fa-receipt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <h2>ยังไม่มีคำสั่งซื้อ</h2>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal สำหรับแสดงสลิป -->
    <div id="slipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-receipt"></i> หลักฐานการโอนเงิน</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="slipImage" src="" alt="Slip">
            </div>
        </div>
    </div>

    <script>
        function filterOrders(status) {
            const rows = document.querySelectorAll('tbody tr');
            const tabs = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function showSlip(image) {
            document.getElementById('slipImage').src = '/uploads/slips/' + image;
            document.getElementById('slipModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('slipModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('slipModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>