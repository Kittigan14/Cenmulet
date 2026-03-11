<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

// ── Handle POST: update user ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update_user') {
    $id       = (int)($_POST['id']       ?? 0);
    $fullname = trim($_POST['fullname']  ?? '');
    $username = trim($_POST['username']  ?? '');
    $tel      = trim($_POST['tel']       ?? '');
    $id_per   = preg_replace('/\D/', '', $_POST['id_per'] ?? '');
    $address  = trim($_POST['address']   ?? '');

    if ($id && $fullname && $username) {
        $check = $db->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
        $check->execute([':u' => $username, ':id' => $id]);
        if ($check->fetch()) {
            header("Location: /views/admin/users.php?error=duplicate"); exit;
        }
        $stmt = $db->prepare("
            UPDATE users SET
                fullname = :fullname, username = :username,
                tel = :tel, id_per = :id_per, address = :address
            WHERE id = :id
        ");
        $stmt->execute([
            ':fullname' => $fullname, ':username' => $username,
            ':tel' => $tel, ':id_per' => $id_per,
            ':address' => $address, ':id' => $id,
        ]);
    }
    header("Location: /views/admin/users.php?success=updated"); exit;
}
// ────────────────────────────────────────────────────────

$admin_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM admins WHERE id = :id");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE fullname LIKE :q OR username LIKE :q OR tel LIKE :q" : "";

$stmt = $db->prepare("SELECT * FROM users $where ORDER BY id DESC");
if ($search) $stmt->bindValue(':q', "%$search%");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total           = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
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
    <style>
        #editUserModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:998;align-items:center;justify-content:center; }
        .edit-box { background:#fff;border-radius:16px;padding:28px;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2); }
        .edit-box label { display:block;font-size:13px;color:#6b7280;margin-bottom:4px;font-weight:600; }
        .edit-box input, .edit-box textarea {
            width:100%;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;
            font-family:inherit;font-size:14px;box-sizing:border-box;transition:border-color .2s;
        }
        .edit-box input:focus, .edit-box textarea:focus { outline:none;border-color:#6366f1; }
        .edit-box textarea { resize:vertical;min-height:80px; }
        .form-group { margin-bottom:16px; }
        tr.clickable-row:hover td { background:#f5f3ff !important; }
    </style>
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

    <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span>แก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i>
        <span><?php echo $_GET['error'] === 'duplicate' ? 'ชื่อผู้ใช้นี้มีอยู่แล้ว' : 'เกิดข้อผิดพลาด กรุณาลองใหม่'; ?></span>
    </div>
    <?php endif; ?>

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
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <?php $uB64 = base64_encode(json_encode([
                'id'       => (int)$u['id'],
                'fullname' => $u['fullname'],
                'username' => $u['username'],
                'tel'      => $u['tel'] ?? '',
                'id_per'   => $u['id_per'] ?? '',
                'address'  => $u['address'] ?? '',
            ], JSON_UNESCAPED_UNICODE)); ?>
            <tr class="clickable-row" onclick="openEdit('<?php echo $uB64; ?>')" style="cursor:pointer">
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
                <td><?php echo htmlspecialchars($u['tel'] ?? '-'); ?></td>
                <td style="font-size:13px;letter-spacing:1px">
                    <?php
                    $idp = $u['id_per'] ?? '';
                    echo $idp ? substr($idp,0,3).'-'.substr($idp,3,4).'-'.substr($idp,7,5).'-'.substr($idp,12) : '-';
                    ?>
                </td>
                <td style="font-size:13px;max-width:200px;word-break:break-word">
                    <?php echo htmlspecialchars($u['address'] ?? '-'); ?>
                </td>
                <td onclick="event.stopPropagation()">
                    <button onclick="openEdit('<?php echo $uB64; ?>')"
                        class="btn-icon" title="แก้ไข"
                        style="background:#e0e7ff;color:#6366f1;border-color:#c7d2fe">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
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

<!-- Edit User Modal -->
<div id="editUserModal" onclick="if(event.target===this)closeEdit()">
    <div class="edit-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-pen-to-square" style="color:#6366f1"></i> แก้ไขข้อมูลผู้ใช้
            </h3>
            <button onclick="closeEdit()" style="background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer">×</button>
        </div>
        <form action="/views/admin/users.php" method="POST">
            <input type="hidden" name="_action" value="update_user">
            <input type="hidden" name="id" id="eu_id">
            <div class="form-group">
                <label><i class="fa-solid fa-user"></i> ชื่อ-นามสกุล</label>
                <input type="text" name="fullname" id="eu_fullname" required>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-at"></i> ชื่อผู้ใช้</label>
                <input type="text" name="username" id="eu_username" required>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-phone"></i> เบอร์โทรศัพท์</label>
                <input type="text" name="tel" id="eu_tel">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-id-card"></i> เลขบัตรประชาชน</label>
                <input type="text" name="id_per" id="eu_id_per" maxlength="13" placeholder="13 หลัก">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-location-dot"></i> ที่อยู่</label>
                <textarea name="address" id="eu_address"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeEdit()" class="btn btn-secondary">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(b64) {
    var u = JSON.parse(decodeURIComponent(escape(atob(b64))));
    document.getElementById('eu_id').value       = u.id;
    document.getElementById('eu_fullname').value = u.fullname;
    document.getElementById('eu_username').value = u.username;
    document.getElementById('eu_tel').value      = u.tel;
    document.getElementById('eu_id_per').value   = u.id_per;
    document.getElementById('eu_address').value  = u.address;
    document.getElementById('editUserModal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('editUserModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEdit();
});
</script>
</body>
</html>