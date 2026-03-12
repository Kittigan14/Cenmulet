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
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการพระเครื่อง - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');
        body { font-family: "Kanit", sans-serif; background: #f3f4f6; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="">
            <h2>Cenmulet</h2>
            <p>แดชบอร์ดผู้ขาย</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php" class="active"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
            <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
            <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งเช่า</a></li>
            <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
            <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</h1>
            <a href="/views/seller/add_product.php" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> เพิ่มพระเครื่องใหม่
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'deleted'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span>ลบพระเครื่องสำเร็จ!</span></div>
            <?php elseif ($_GET['success'] === 'hidden'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-eye-slash"></i> <span>ซ่อนพระเครื่องเรียบร้อยแล้ว</span></div>
            <?php elseif ($_GET['success'] === 'shown'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-eye"></i> <span>แสดงพระเครื่องเรียบร้อยแล้ว</span></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <span>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</span></div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="stats-mini" style="margin-bottom:22px">
            <?php
            $total  = count($products);
            $inStock  = 0; foreach ($products as $p) { if ($p['quantity'] > 0) $inStock++; }
            $outStock = $total - $inStock;
            ?>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $total; ?></div><div class="stat-mini-label">พระเครื่องทั้งหมด</div></div>
                <div class="stat-mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fa-solid fa-box"></i></div>
            </div>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $inStock; ?></div><div class="stat-mini-label">มีพระเครื่อง</div></div>
                <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-circle"></i></div>
            </div>
            <div class="stat-mini">
                <div><div class="stat-mini-value"><?php echo $outStock; ?></div><div class="stat-mini-label">พระเครื่องหมด</div></div>
                <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-exclamation-circle"></i></div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <?php if (count($products) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>รูปภาพ</th>
                            <th>ชื่อพระเครื่อง</th>
                            <th>หมวดหมู่</th>
                            <th>ราคา</th>
                            <th>คงเหลือ</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['image']): ?>
                                <img src="/uploads/amulets/<?php echo htmlspecialchars($p['image']); ?>"
                                     class="product-img" alt="">
                                <?php else: ?>
                                <div class="no-image"><i class="fa-solid fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['amulet_name']); ?></strong>
                                <?php if ($p['source']): ?>
                                <div style="font-size:12px;color:#9ca3af"><?php echo htmlspecialchars($p['source']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-purple">
                                    <?php echo htmlspecialchars($p['category_name'] ?? '-'); ?>
                                </span>
                            </td>
                            <td><strong style="color:#10b981">฿<?php echo number_format($p['price'], 2); ?></strong></td>
                            <td>
                                <span style="font-weight:600;color:<?php echo $p['quantity'] > 0 ? '#1a1a1a' : '#ef4444'; ?>">
                                    <?php echo number_format($p['quantity']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($p['is_hidden'])): ?>
                                <span class="badge" style="background:#f3f4f6;color:#6b7280"><i class="fa-solid fa-eye-slash"></i> ซ่อนอยู่</span>
                                <?php elseif ($p['quantity'] > 0): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> มีพระเครื่อง</span>
                                <?php else: ?>
                                <span class="badge badge-warning"><i class="fa-solid fa-circle-exclamation"></i> พระเครื่องหมด</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/views/seller/edit_product.php?id=<?php echo $p['id']; ?>"
                                       class="btn-icon edit" title="แก้ไข">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <?php if (!empty($p['is_hidden'])): ?>
                                    <a href="/seller/toggle_product_visibility.php?id=<?php echo $p['id']; ?>"
                                       class="btn-icon" title="แสดงพระเครื่อง"
                                       style="background:#d1fae5;color:#059669;border-color:#a7f3d0">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="/seller/toggle_product_visibility.php?id=<?php echo $p['id']; ?>"
                                       class="btn-icon" title="ซ่อนพระเครื่อง"
                                       style="background:#fef3c7;color:#d97706;border-color:#fde68a">
                                        <i class="fa-solid fa-eye-slash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h2>ยังไม่มีพระเครื่อง</h2>
                    <p>เริ่มต้นเพิ่มพระเครื่องของคุณเลย</p>
                    <a href="/views/seller/add_product.php" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> เพิ่มพระเครื่องแรก
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>