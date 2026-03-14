<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id  = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: /views/seller/products.php");
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM amulets WHERE id = :id AND sellerId = :seller_id");
    $stmt->execute([':id' => $product_id, ':seller_id' => $seller_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        header("Location: /views/seller/products.php?error=not_found");
        exit;
    }
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

try {
    $categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

/* ── ดึงรูปทั้งหมดจาก amulet_images ── */
try {
    $stmt = $db->prepare("SELECT * FROM amulet_images WHERE amulet_id = :id ORDER BY sort_order ASC");
    $stmt->execute([':id' => $product_id]);
    $existing_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $existing_images = []; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>แก้ไขพระเครื่อง - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&display=swap');
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Kanit",sans-serif; background:#f3f4f6; }

        .dashboard-container { display:flex; min-height:100vh; }
        .sidebar {
            width:260px; background:linear-gradient(135deg,#10b981 0%,#059669 100%);
            color:#fff; padding:20px; position:fixed; height:100vh; overflow-y:auto;
        }
        .sidebar-header { text-align:center; padding:20px 0; border-bottom:1px solid rgba(255,255,255,.2); margin-bottom:20px; }
        .sidebar-header h2 { font-size:24px; margin-bottom:5px; }
        .sidebar-header p  { font-size:14px; opacity:.9; }
        .sidebar-user { background:rgba(255,255,255,.1); padding:15px; border-radius:10px; margin-bottom:20px; }
        .sidebar-user h3 { font-size:16px; margin-bottom:5px; }
        .sidebar-user p  { font-size:13px; opacity:.9; }
        .sidebar-menu { list-style:none; }
        .sidebar-menu li { margin-bottom:5px; }
        .sidebar-menu a {
            display:flex; align-items:center; gap:12px; padding:12px 15px;
            color:#fff; text-decoration:none; border-radius:8px; transition:all .3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active { background:rgba(255,255,255,.2); }
        .sidebar-menu i { font-size:18px; width:20px; }
        .main-content { margin-left:260px; flex:1; padding:30px; }
        .top-bar {
            background:#fff; padding:20px 30px; border-radius:15px;
            box-shadow:0 2px 10px rgba(0,0,0,.05); margin-bottom:30px;
        }
        .top-bar h1 { font-size:28px; color:#1a1a1a; margin-bottom:10px; }
        .breadcrumb { display:flex; gap:10px; align-items:center; font-size:14px; color:#6b7280; }
        .breadcrumb a { color:#10b981; text-decoration:none; }
        .breadcrumb a:hover { text-decoration:underline; }

        /* Form */
        .form-container {
            background:#fff; padding:30px; border-radius:15px;
            box-shadow:0 2px 10px rgba(0,0,0,.05); max-width:900px;
        }
        .form-section { margin-bottom:30px; }
        .section-title {
            font-size:18px; color:#1a1a1a; margin-bottom:20px; padding-bottom:10px;
            border-bottom:2px solid #f3f4f6; display:flex; align-items:center; gap:10px;
        }
        .section-title i { color:#10b981; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        .form-group { margin-bottom:20px; }
        .form-group.full-width { grid-column:1/-1; }
        .form-group label { display:block; font-size:14px; color:#374151; margin-bottom:8px; font-weight:500; }
        .required { color:#ef4444; margin-left:4px; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:12px 15px; border:2px solid #e5e7eb;
            border-radius:10px; font-family:"Kanit",sans-serif; font-size:14px; outline:none; transition:all .3s;
        }
        .form-group textarea { resize:vertical; min-height:120px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.1);
        }

        /* Existing images grid */
        .img-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(130px,1fr));
            gap:14px;
            margin-bottom:20px;
        }
        .img-card {
            position:relative; border-radius:10px; overflow:hidden;
            border:2px solid #e5e7eb; background:#f9fafb;
            transition:border-color .2s, box-shadow .2s;
        }
        .img-card:hover { border-color:#10b981; box-shadow:0 4px 14px rgba(16,185,129,.15); }
        .img-card img { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; }
        .img-badge {
            position:absolute; top:5px; left:5px;
            background:#10b981; color:#fff;
            font-size:10px; font-weight:700; padding:2px 8px;
            border-radius:99px;
        }
        .img-order {
            position:absolute; top:5px; left:5px;
            background:rgba(0,0,0,.48); color:#fff;
            font-size:10px; font-weight:600; padding:2px 8px;
            border-radius:99px;
        }
        .img-del {
            position:absolute; top:5px; right:5px;
            background:rgba(239,68,68,.85); color:#fff;
            border:none; border-radius:50%;
            width:26px; height:26px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:11px;
            transition:background .2s, transform .15s;
        }
        .img-del:hover { background:#dc2626; transform:scale(1.12); }

        /* Marked-for-delete state */
        .img-card.marked-delete { opacity:.38; border-color:#ef4444 !important; }
        .img-card.marked-delete .img-del { background:rgba(16,185,129,.85); }
        .img-card.marked-delete::after {
            content:'ลบ';
            position:absolute; inset:0;
            background:rgba(239,68,68,.12);
            display:flex; align-items:center; justify-content:center;
            font-size:14px; font-weight:700; color:#dc2626;
            pointer-events:none;
        }

        .empty-images {
            text-align:center; padding:28px;
            background:#f9fafb; border:2px dashed #d1d5db;
            border-radius:10px; color:#9ca3af; font-size:14px;
        }
        .empty-images i { font-size:32px; display:block; margin-bottom:10px; }

        /* Upload area */
        .image-upload-area {
            border:2px dashed #d1d5db; border-radius:10px; padding:28px;
            text-align:center; background:#f9fafb; transition:all .3s; cursor:pointer;
        }
        .image-upload-area:hover { border-color:#10b981; background:#f0fdf4; }
        .upload-icon { font-size:44px; color:#10b981; margin-bottom:12px; }
        .upload-text h3 { font-size:15px; color:#1a1a1a; margin-bottom:5px; }
        .upload-text p  { font-size:13px; color:#6b7280; }
        #newImagesInput { display:none; }

        /* New images preview */
        .new-preview-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(100px,1fr));
            gap:10px; margin-top:14px;
        }
        .new-preview-item {
            position:relative; border-radius:8px; overflow:hidden;
            border:2px solid #10b981;
        }
        .new-preview-item img { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; }
        .rm-new {
            position:absolute; top:4px; right:4px;
            background:rgba(239,68,68,.85); color:#fff;
            border:none; border-radius:50%;
            width:22px; height:22px;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:10px;
        }
        .rm-new:hover { background:#dc2626; }

        /* Alerts */
        .alert {
            padding:15px 20px; border-radius:10px; margin-bottom:20px;
            display:flex; align-items:center; gap:12px; font-size:14px;
        }
        .alert-success { background:#d1fae5; color:#059669; border-left:4px solid #10b981; }
        .alert-error   { background:#fee2e2; color:#dc2626; border-left:4px solid #ef4444; }
        .alert i { font-size:18px; }

        /* Actions */
        .form-actions {
            display:flex; gap:15px; justify-content:flex-end;
            margin-top:30px; padding-top:20px; border-top:2px solid #f3f4f6;
        }
        .btn {
            padding:12px 30px; border-radius:10px; font-size:15px; font-weight:500;
            cursor:pointer; transition:all .3s;
            display:inline-flex; align-items:center; gap:8px;
            border:none; text-decoration:none; font-family:"Kanit",sans-serif;
        }
        .btn-primary   { background:#10b981; color:#fff; }
        .btn-primary:hover   { background:#059669; transform:translateY(-2px); box-shadow:0 4px 12px rgba(16,185,129,.3); }
        .btn-secondary { background:#f3f4f6; color:#6b7280; }
        .btn-secondary:hover { background:#e5e7eb; }
    </style>
</head>
<body>
<div class="dashboard-container">

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="/public/images/image.png" alt="" width="64px">
            <h2>Cenmulet</h2>
            <p>แดชบอร์ดร้านค้า</p>
        </div>
        <div class="sidebar-user">
            <h3><?php echo htmlspecialchars($seller['store_name']); ?></h3>
            <p><?php echo htmlspecialchars($seller['fullname']); ?></p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="/views/seller/dashboard.php"><i class="fa-solid fa-chart-line"></i> แดชบอร์ด</a></li>
            <li><a href="/views/seller/products.php" class="active"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
            <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
            <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> การเช่า</a></li>
            <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
            <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
            <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1>แก้ไขพระเครื่อง</h1>
            <div class="breadcrumb">
                <a href="/views/seller/dashboard.php">แดชบอร์ด</a>
                <span>/</span>
                <a href="/views/seller/products.php">จัดการพระเครื่อง</a>
                <span>/</span>
                <span>แก้ไข</span>
            </div>
        </div>

        <div class="form-container">

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>แก้ไขพระเครื่องสำเร็จ!</span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo $_GET['error']==='empty' ? 'กรุณากรอกข้อมูลให้ครบถ้วน' : 'เกิดข้อผิดพลาดในการแก้ไขพระเครื่อง'; ?></span>
            </div>
            <?php endif; ?>

            <form action="/seller/edit_product_process.php" method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="product_id"       value="<?php echo $product['id']; ?>">
                <input type="hidden" name="old_image"        value="<?php echo $product['image']; ?>">
                <!-- id ของรูปใน amulet_images ที่ต้องการลบ คั่นด้วย comma -->
                <input type="hidden" name="delete_image_ids" id="deleteImageIds" value="">

                <!-- ══ ข้อมูลพระเครื่อง ══ -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-info-circle"></i> ข้อมูลพระเครื่อง
                    </h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amulet_name">ชื่อพระเครื่อง<span class="required">*</span></label>
                            <input type="text" id="amulet_name" name="amulet_name"
                                   value="<?php echo htmlspecialchars($product['amulet_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="categoryId">หมวดหมู่<span class="required">*</span></label>
                            <select id="categoryId" name="categoryId" required>
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($cat['id'] == $product['categoryId']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="source">ที่มา/รายละเอียด<span class="required">*</span></label>
                        <textarea id="source" name="source" required><?php echo htmlspecialchars($product['source']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">ราคา (บาท)<span class="required">*</span></label>
                            <input type="number" id="price" name="price"
                                   value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="quantity">จำนวน<span class="required">*</span></label>
                            <input type="number" id="quantity" name="quantity"
                                   value="<?php echo $product['quantity']; ?>" min="0" required>
                        </div>
                    </div>
                </div>

                <!-- ══ รูปภาพพระเครื่อง ══ -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-images"></i>
                        รูปภาพพระเครื่อง
                        <span style="font-size:13px;color:#6b7280;font-weight:400;margin-left:6px">
                            (<?php echo count($existing_images); ?> รูป)
                        </span>
                    </h2>

                    <!-- รูปที่มีอยู่แล้ว -->
                    <?php if (!empty($existing_images)): ?>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:12px">
                        <i class="fa-solid fa-circle-info" style="color:#10b981"></i>
                        คลิก <i class="fa-solid fa-trash" style="color:#ef4444;font-size:11px"></i>
                        ที่รูปเพื่อทำเครื่องหมายลบ · คลิกอีกครั้งเพื่อยกเลิก · กด "บันทึก" เพื่อยืนยัน
                    </p>
                    <div class="img-grid" id="existingGrid">
                        <?php foreach ($existing_images as $idx => $img): ?>
                        <div class="img-card" id="imgcard-<?php echo $img['id']; ?>">
                            <?php if ($idx === 0): ?>
                            <span class="img-badge">หลัก</span>
                            <?php else: ?>
                            <span class="img-order">#<?php echo $idx + 1; ?></span>
                            <?php endif; ?>

                            <img src="/uploads/amulets/<?php echo htmlspecialchars($img['image']); ?>"
                                 alt="รูป <?php echo $idx + 1; ?>">

                            <button type="button" class="img-del"
                                    onclick="toggleDelete(<?php echo $img['id']; ?>)"
                                    title="ทำเครื่องหมายลบ / ยกเลิก">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-images">
                        <i class="fa-solid fa-image"></i>
                        ยังไม่มีรูปภาพพระเครื่อง
                    </div>
                    <?php endif; ?>

                    <!-- Upload รูปใหม่ -->
                    <div class="form-group full-width" style="margin-top:20px">
                        <label>เพิ่มรูปภาพใหม่</label>
                        <div class="image-upload-area"
                             onclick="document.getElementById('newImagesInput').click()">
                            <div class="upload-icon">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div class="upload-text">
                                <h3>คลิกเพื่อเลือกรูปภาพ</h3>
                                <p>รองรับ JPG, PNG, GIF · เลือกได้หลายรูปพร้อมกัน · ขนาดไม่เกิน 5MB/รูป</p>
                            </div>
                            <input type="file" id="newImagesInput" name="new_images[]"
                                   accept="image/*" multiple>
                        </div>
                        <div class="new-preview-grid" id="newPreviewGrid"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="/views/seller/products.php" class="btn btn-secondary">
                        <i class="fa-solid fa-xmark"></i> ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
/* ─────────────────────────────────────────────
   1. Mark / unmark existing images for deletion
───────────────────────────────────────────── */
const deleteSet = new Set();

function toggleDelete(imgId) {
    const card = document.getElementById('imgcard-' + imgId);
    if (!card) return;

    if (deleteSet.has(imgId)) {
        deleteSet.delete(imgId);
        card.classList.remove('marked-delete');
    } else {
        deleteSet.add(imgId);
        card.classList.add('marked-delete');
    }
    // sync hidden field
    document.getElementById('deleteImageIds').value = [...deleteSet].join(',');
}

/* ─────────────────────────────────────────────
   2. New images: multi-file preview & remove
───────────────────────────────────────────── */
const fileInput   = document.getElementById('newImagesInput');
const previewGrid = document.getElementById('newPreviewGrid');
let   fileList    = [];   // managed array of File objects

fileInput.addEventListener('change', function () {
    Array.from(this.files).forEach(f => {
        if (f.size > 5 * 1024 * 1024) {
            alert(`ไฟล์ "${f.name}" มีขนาดใหญ่เกิน 5MB กรุณาเลือกไฟล์ใหม่`);
            return;
        }
        const key = f.name + '_' + f.size;
        if (!fileList.find(x => x._key === key)) {
            f._key = key;
            fileList.push(f);
        }
    });
    syncFiles();
    renderNewPreviews();
});

function removeNewFile(key) {
    fileList = fileList.filter(f => f._key !== key);
    syncFiles();
    renderNewPreviews();
}

function syncFiles() {
    const dt = new DataTransfer();
    fileList.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
}

function renderNewPreviews() {
    previewGrid.innerHTML = '';
    fileList.forEach(f => {
        const wrap = document.createElement('div');
        wrap.className = 'new-preview-item';

        const img = document.createElement('img');
        const url = URL.createObjectURL(f);
        img.src = url;
        img.alt = f.name;
        img.onload = () => URL.revokeObjectURL(url);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rm-new';
        btn.title = 'ลบรูปนี้';
        btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            removeNewFile(f._key);
        });

        wrap.append(img, btn);
        previewGrid.appendChild(wrap);
    });
}

/* ─────────────────────────────────────────────
   3. Guard: ห้าม submit เมื่อรูปจะเหลือ 0 รูป
───────────────────────────────────────────── */
document.getElementById('editForm').addEventListener('submit', function (e) {
    const totalExisting = <?php echo count($existing_images); ?>;
    const remaining     = (totalExisting - deleteSet.size) + fileList.length;

    if (remaining < 1) {
        e.preventDefault();
        alert('ต้องมีรูปภาพอย่างน้อย 1 รูป\nกรุณาเพิ่มรูปใหม่ก่อนลบรูปทั้งหมด');
    }
});
</script>
</body>
</html>