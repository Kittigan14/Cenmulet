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
    SELECT c.id, c.category_name, COALESCE(c.is_hidden, 0) as is_hidden,
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
    <style>
        .cat-form-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            margin-bottom: 24px;
        }
        .cat-form-card h3 {
            font-size: 15px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cat-form-card h3 i { color: #6366f1; }
        .cat-input-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .cat-input-row input {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 9px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s;
        }
        .cat-input-row input:focus { border-color: #6366f1; }

        /* Edit inline */
        .edit-input {
            padding: 7px 12px;
            border: 2px solid #6366f1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            width: 220px;
        }
        .btn-purple {
            background: #6366f1;
            color: #fff;
        }
        .btn-purple:hover { background: #4f46e5; }
        .btn-icon.edit-cat   { background: #ede9fe; color: #7c3aed; }
        .btn-icon.hide-cat   { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }
        .btn-icon.show-cat   { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .btn-icon.save-cat   { background: #d1fae5; color: #059669; }
        .btn-icon.cancel-cat { background: #f3f4f6; color: #6b7280; }
        tr.cat-hidden td { opacity: .55; }
        .btn-icon { border: none; padding: 7px 10px; border-radius: 8px; cursor: pointer; font-size: 14px; transition: opacity .2s; }
        .btn-icon:hover { opacity: .8; }

        .cat-count-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #e0e7ff; color: #4338ca;
            padding: 3px 11px; border-radius: 99px; font-size: 12px; font-weight: 600;
        }
    </style>
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

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php if ($_GET['success'] === 'hidden'): ?>
        <i class="fa-solid fa-eye-slash"></i> <span>ซ่อนหมวดหมู่เรียบร้อยแล้ว</span>
        <?php elseif ($_GET['success'] === 'shown'): ?>
        <i class="fa-solid fa-eye"></i> <span>แสดงหมวดหมู่เรียบร้อยแล้ว</span>
        <?php else: ?>
        <i class="fa-solid fa-circle-check"></i> <span><?php
            $ok = ['added' => 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว', 'edited' => 'แก้ไขหมวดหมู่เรียบร้อยแล้ว'];
            echo $ok[$_GET['success']] ?? 'ดำเนินการสำเร็จ';
        ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?php
            $errs = [
                'empty'     => 'กรุณากรอกชื่อหมวดหมู่',
                'duplicate' => 'ชื่อหมวดหมู่นี้มีอยู่แล้ว',
                'db'        => 'เกิดข้อผิดพลาด กรุณาลองใหม่',
            ];
            echo $errs[$_GET['error']] ?? 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        ?></span>
    </div>
    <?php endif; ?>

    <!-- Add Category Form -->
    <div class="cat-form-card">
        <h3><i class="fa-solid fa-plus-circle"></i> เพิ่มหมวดหมู่ใหม่</h3>
        <form action="/admin/category_action.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="cat-input-row">
                <input type="text" name="category_name" id="newCategoryName"
                       placeholder="ชื่อหมวดหมู่ใหม่ เช่น พระปิดตา" required maxlength="100"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm" style="white-space:nowrap">
                    <i class="fa-solid fa-plus"></i> เพิ่ม
                </button>
            </div>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="card">
        <div class="table-wrapper">
        <?php if (count($categories) > 0): ?>
        <table id="catTable">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>ชื่อหมวดหมู่</th>
                    <th>จำนวนพระเครื่อง</th>
                    <th style="width:140px">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $i => $c): ?>
            <tr id="row-<?php echo $c['id']; ?>" class="<?php echo $c['is_hidden'] ? 'cat-hidden' : ''; ?>">
                <td style="color:#9ca3af"><?php echo $i + 1; ?></td>
                <td>
                    <!-- View mode -->
                    <div id="view-<?php echo $c['id']; ?>"
                         style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:8px;background:<?php echo $c['is_hidden'] ? '#e5e7eb' : 'linear-gradient(135deg,#6366f1,#8b5cf6)'; ?>;display:flex;align-items:center;justify-content:center;color:<?php echo $c['is_hidden'] ? '#9ca3af' : '#fff'; ?>;flex-shrink:0">
                            <i class="fa-solid <?php echo $c['is_hidden'] ? 'fa-eye-slash' : 'fa-tag'; ?>"></i>
                        </div>
                        <strong style="font-size:15px"><?php echo htmlspecialchars($c['category_name']); ?></strong>
                        <?php if ($c['is_hidden']): ?>
                        <span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:11px"><i class="fa-solid fa-eye-slash"></i> ซ่อนอยู่</span>
                        <?php endif; ?>
                    </div>
                    <!-- Edit mode -->
                    <div id="edit-<?php echo $c['id']; ?>" style="display:none">
                        <input type="text" class="edit-input"
                               id="editInput-<?php echo $c['id']; ?>"
                               value="<?php echo htmlspecialchars($c['category_name']); ?>"
                               maxlength="100">
                    </div>
                </td>
                <td>
                    <span class="cat-count-chip">
                        <i class="fa-solid fa-box"></i>
                        <?php echo number_format($c['product_count']); ?> พระเครื่อง
                    </span>
                </td>
                <td>
                    <!-- View mode buttons -->
                    <div id="viewBtns-<?php echo $c['id']; ?>" style="display:flex;gap:6px">
                        <button class="btn-icon edit-cat" title="แก้ไข"
                                onclick="startEdit(<?php echo $c['id']; ?>)">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form action="/admin/category_action.php" method="POST">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <?php if ($c['is_hidden']): ?>
                            <button type="submit" class="btn-icon show-cat" title="แสดงหมวดหมู่">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?php else: ?>
                            <button type="submit" class="btn-icon hide-cat" title="ซ่อนหมวดหมู่">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <!-- Edit mode buttons -->
                    <div id="editBtns-<?php echo $c['id']; ?>" style="display:none;gap:6px">
                        <form action="/admin/category_action.php" method="POST" id="editForm-<?php echo $c['id']; ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                            <input type="hidden" name="category_name" id="editHidden-<?php echo $c['id']; ?>">
                            <div style="display:flex;gap:6px">
                                <button type="submit" class="btn-icon save-cat" title="บันทึก"
                                        onclick="prepareEdit(<?php echo $c['id']; ?>)">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button type="button" class="btn-icon cancel-cat" title="ยกเลิก"
                                        onclick="cancelEdit(<?php echo $c['id']; ?>)">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-tags"></i>
            <h2>ยังไม่มีหมวดหมู่</h2>
            <p>เพิ่มหมวดหมู่ใหม่ได้จากฟอร์มด้านบน</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>

<script>
function startEdit(id) {
    document.getElementById('view-' + id).style.display = 'none';
    document.getElementById('edit-' + id).style.display = 'block';
    document.getElementById('viewBtns-' + id).style.display = 'none';
    document.getElementById('editBtns-' + id).style.display = 'flex';
    document.getElementById('editInput-' + id).focus();
}
function cancelEdit(id) {
    document.getElementById('view-' + id).style.display = 'flex';
    document.getElementById('edit-' + id).style.display = 'none';
    document.getElementById('viewBtns-' + id).style.display = 'flex';
    document.getElementById('editBtns-' + id).style.display = 'none';
}
function prepareEdit(id) {
    const val = document.getElementById('editInput-' + id).value.trim();
    if (!val) { alert('กรุณากรอกชื่อหมวดหมู่'); return false; }
    document.getElementById('editHidden-' + id).value = val;
}
</script>
</body>
</html>