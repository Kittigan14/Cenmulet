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

// Search
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE fullname LIKE :q OR username LIKE :q OR tel LIKE :q" : "";

$stmt = $db->prepare("SELECT * FROM users $where ORDER BY id DESC");
if ($search) $stmt->bindValue(':q', "%$search%");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการผู้ใช้ - Cenmulet Admin</title>
</head>
<body class="admin">
<div class="dashboard-container">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <div class="top-bar">
        <h1><i class="fa-solid fa-users"></i> จัดการผู้ใช้</h1>
        <span class="badge badge-info" style="font-size:14px;padding:8px 16px">
            ทั้งหมด <?php echo number_format($total); ?> คน
        </span>
    </div>

    <!-- Search -->
    <form method="GET" style="margin-bottom:20px;display:flex;gap:10px">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
               placeholder="ค้นหาชื่อ, ชื่อผู้ใช้, เบอร์โทร..."
               style="flex:1;padding:10px 16px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-search"></i> ค้นหา
        </button>
        <?php if ($search): ?>
        <a href="/views/admin/users.php" class="btn btn-secondary">
            <i class="fa-solid fa-times"></i> ล้าง
        </a>
        <?php endif; ?>
    </form>

    <div class="card">
        <div class="table-wrapper">
        <?php if (count($users) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>รูปโปรไฟล์</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ชื่อผู้ใช้</th>
                    <th>เบอร์โทร</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>ที่อยู่</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="color:#9ca3af;font-size:13px"><?php echo $u['id']; ?></td>
                <td>
                    <?php if (!empty($u['image'])): ?>
                    <img src="/uploads/users/<?php echo htmlspecialchars($u['image']); ?>"
                         class="product-img" alt="">
                    <?php else: ?>
                    <div class="no-image"><i class="fa-solid fa-user"></i></div>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo htmlspecialchars($u['fullname']); ?></strong></td>
                <td style="color:#6b7280"><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['tel']); ?></td>
                <td style="font-size:13px;letter-spacing:1px">
                    <?php
                    $id = $u['id_per'] ?? '';
                    echo $id ? substr($id,0,3).'-'.substr($id,3,4).'-'.substr($id,7,5).'-'.substr($id,12) : '-';
                    ?>
                </td>
                <td style="font-size:13px;max-width:200px;word-break:break-word">
                    <?php echo htmlspecialchars($u['address'] ?? '-'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <h2><?php echo $search ? "ไม่พบผลการค้นหา" : "ยังไม่มีผู้ใช้"; ?></h2>
            <p><?php echo $search ? "ลองค้นหาด้วยคำอื่น" : ""; ?></p>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>
</body>
</html>