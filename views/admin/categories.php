<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงหมวดหมู่พร้อมนับจำนวนสินค้า
$categories = $db->query("
    SELECT c.*,
           COUNT(a.id) as product_count
    FROM categories c
    LEFT JOIN amulets a ON c.id = a.categoryId
    GROUP BY c.id
    ORDER BY c.category_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($categories);
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการหมวดหมู่ - Cenmulet Admin</title>
</head>
<body class="admin">
<div class="dashboard-container">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <div class="top-bar">
        <h1><i class="fa-solid fa-tags"></i> จัดการหมวดหมู่</h1>
        <span class="badge badge-info" style="font-size:14px;padding:8px 16px">
            ทั้งหมด <?php echo $total; ?> หมวดหมู่
        </span>
    </div>

    <div class="card">
        <div class="table-wrapper">
        <?php if (count($categories) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ชื่อหมวดหมู่</th>
                    <th>จำนวนสินค้า</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $i => $c): ?>
            <tr>
                <td style="color:#9ca3af;width:60px"><?php echo $i + 1; ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff">
                            <i class="fa-solid fa-tag"></i>
                        </div>
                        <strong style="font-size:15px"><?php echo htmlspecialchars($c['category_name']); ?></strong>
                    </div>
                </td>
                <td>
                    <span class="badge badge-info">
                        <i class="fa-solid fa-box"></i>
                        <?php echo number_format($c['product_count']); ?> สินค้า
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-tags"></i>
            <h2>ยังไม่มีหมวดหมู่</h2>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>