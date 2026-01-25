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

// ดึงคำสั่งซื้อทั้งหมดของสินค้าของผู้ขาย
try {
    $stmt = $db->prepare("
        SELECT DISTINCT o.*, u.fullname, u.tel, u.address,
               p.slip_image, p.status as payment_status,
               COUNT(DISTINCT oi.id) as item_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN amulets a ON oi.amulet_id = a.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE a.sellerId = :seller_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([':seller_id' => $seller_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>จัดการคำสั่งซื้อ - Cenmulet</title>
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            background: #10b981;
            color: #fff;
            border-color: #10b981;
        }

        .tab-btn:hover {
            border-color: #10b981;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 15px;
        }

        .order-number {
            font-size: 18px;
            color: #1a1a1a;
            font-weight: 600;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item i {
            color: #10b981;
            width: 20px;
        }

        .info-item div {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 14px;
            color: #1a1a1a;
            font-weight: 500;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f3f4f6;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-view {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-view:hover {
            background: #bfdbfe;
        }

        .btn-slip {
            background: #fef3c7;
            color: #d97706;
        }

        .btn-slip:hover {
            background: #fde68a;
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

        .empty-state h2 {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 10px;
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
            position: relative;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #1a1a1a;
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
                <p>แดชบอร์ดผู้ขาย</p>
            </div>

            <div class="user-info">
                <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
                <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
            </div>

            <ul class="sidebar-menu">
                <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
                <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มสินค้า</a></li>
                <li><a href="/views/seller/orders.php" class="active"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
                <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>คำสั่งซื้อทั้งหมด</h1>
            </div>

            <div class="filter-tabs">
                <button class="tab-btn active" onclick="filterOrders('all')">
                    <i class="fa-solid fa-list"></i> ทั้งหมด (<?php echo count($orders); ?>)
                </button>
                <button class="tab-btn" onclick="filterOrders('pending')">
                    <i class="fa-solid fa-clock"></i> รอตรวจสอบ
                </button>
                <button class="tab-btn" onclick="filterOrders('confirmed')">
                    <i class="fa-solid fa-check-circle"></i> ยืนยันแล้ว
                </button>
                <button class="tab-btn" onclick="filterOrders('completed')">
                    <i class="fa-solid fa-check-double"></i> สำเร็จ
                </button>
            </div>

            <?php if (count($orders) > 0): ?>
                <div class="orders-list">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $status_class = 'status-pending';
                        $status_text = 'รอตรวจสอบ';
                        $status_icon = 'fa-clock';
                        $data_status = 'pending';
                        
                        if ($order['payment_status'] === 'confirmed') {
                            $status_class = 'status-confirmed';
                            $status_text = 'ยืนยันการชำระเงิน';
                            $status_icon = 'fa-check-circle';
                            $data_status = 'confirmed';
                        }
                        if ($order['status'] === 'completed') {
                            $status_class = 'status-completed';
                            $status_text = 'จัดส่งสำเร็จ';
                            $status_icon = 'fa-check-double';
                            $data_status = 'completed';
                        }
                        ?>
                        <div class="order-card" data-status="<?php echo $data_status; ?>">
                            <div class="order-header">
                                <div>
                                    <div class="order-number">
                                        <i class="fa-solid fa-receipt"></i>
                                        คำสั่งซื้อ #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?php echo date('d/m/Y H:i น.', strtotime($order['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="fa-solid <?php echo $status_icon; ?>"></i>
                                    <?php echo $status_text; ?>
                                </div>
                            </div>

                            <div class="order-info">
                                <div class="info-item">
                                    <i class="fa-solid fa-user"></i>
                                    <div>
                                        <div class="info-label">ผู้สั่งซื้อ</div>
                                        <div class="info-value"><?php echo htmlspecialchars($order['fullname']); ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <i class="fa-solid fa-phone"></i>
                                    <div>
                                        <div class="info-label">เบอร์โทร</div>
                                        <div class="info-value"><?php echo htmlspecialchars($order['tel']); ?></div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <i class="fa-solid fa-box"></i>
                                    <div>
                                        <div class="info-label">จำนวนสินค้า</div>
                                        <div class="info-value"><?php echo $order['item_count']; ?> รายการ</div>
                                    </div>
                                </div>

                                <div class="info-item">
                                    <i class="fa-solid fa-money-bill"></i>
                                    <div>
                                        <div class="info-label">ยอดรวม</div>
                                        <div class="info-value" style="color: #10b981; font-size: 16px; font-weight: 600;">
                                            ฿<?php echo number_format($order['total_price'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="order-actions">
                                <a href="/views/seller/order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-view">
                                    <i class="fa-solid fa-eye"></i> ดูรายละเอียด
                                </a>
                                <?php if ($order['slip_image']): ?>
                                <button onclick="showSlip('<?php echo htmlspecialchars($order['slip_image']); ?>')" class="btn btn-slip">
                                    <i class="fa-solid fa-receipt"></i> ดูสลิป
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <h2>ยังไม่มีคำสั่งซื้อ</h2>
                    <p style="color: #6b7280; font-size: 16px;">รอลูกค้าสั่งซื้อสินค้าของคุณ</p>
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
            const cards = document.querySelectorAll('.order-card');
            const tabs = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.closest('.tab-btn').classList.add('active');
            
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
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

        // ปิด modal เมื่อคลิกนอก content
        window.onclick = function(event) {
            const modal = document.getElementById('slipModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>