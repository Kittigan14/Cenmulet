<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

// ตรวจสอบว่า login และเป็น seller หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];

// ดึงข้อมูลร้านค้า
try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// ดึงข้อมูลหมวดหมู่ทั้งหมด
try {
    $categories = $db->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>เพิ่มพระเครื่อง - Cenmulet</title>
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

        /* Sidebar */
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

        .sidebar-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .sidebar-user {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .sidebar-user h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .sidebar-user p {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Form Container */
        .form-container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 900px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            color: #1a1a1a;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #10b981;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #374151;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-family: "Kanit", sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .input-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        /* Image Upload */
        .image-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f9fafb;
            transition: all 0.3s;
            cursor: pointer;
        }
        .image-upload-area:hover, .image-upload-area.has-images {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .upload-icon { font-size: 48px; color: #10b981; margin-bottom: 15px; }
        .upload-text h3 { font-size: 16px; color: #1a1a1a; margin-bottom: 5px; }
        .upload-text p  { font-size: 14px; color: #6b7280; }
        #imageInput { display: none; }

        /* Image count badge */
        .img-count-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 99px; font-size: 13px; font-weight: 700;
            margin-top: 10px;
        }
        .img-count-badge.ok  { background: #d1fae5; color: #059669; }
        .img-count-badge.low { background: #fef3c7; color: #d97706; }
        .img-count-badge.bad { background: #fee2e2; color: #dc2626; }

        /* Preview grid */
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .preview-item {
            position: relative;
            aspect-ratio: 1;
        }
        .preview-item img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        .preview-item .rm-btn {
            position: absolute; top: -6px; right: -6px;
            background: #ef4444; color: #fff;
            border: none; border-radius: 50%;
            width: 22px; height: 22px;
            font-size: 11px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,.2);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #059669;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .alert i {
            font-size: 18px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f3f4f6;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: #10b981;
            color: #fff;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }
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
                <li><a href="/views/seller/products.php"><i class="fa-solid fa-box"></i> จัดการพระเครื่อง</a></li>
                <li><a href="/views/seller/add_product.php" class="active"><i class="fa-solid fa-plus"></i> เพิ่มพระเครื่อง</a></li>
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> เช่า</a></li>
                <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/views/seller/report.php"><i class="fa-solid fa-chart-bar"></i> รายงานการขาย</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>เพิ่มพระเครื่องใหม่</h1>
                    <div class="breadcrumb">
                        <a href="/views/seller/dashboard.php">แดชบอร์ด</a>
                        <span>/</span>
                        <a href="/views/seller/products.php">จัดการพระเครื่อง</a>
                        <span>/</span>
                        <span>เพิ่มพระเครื่อง</span>
                    </div>
                </div>
            </div>

            <div class="form-container">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>เพิ่มพระเครื่องสำเร็จ!</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>
                            <?php
                            if ($_GET['error'] == 'empty') {
                                echo 'กรุณากรอกข้อมูลให้ครบถ้วน';
                            } elseif ($_GET['error'] == 'min_images') {
                                echo '<strong>ต้องอัปโหลดรูปภาพอย่างน้อย 5 รูป</strong> เพื่อให้ลูกค้าเห็นพระเครื่องได้ชัดเจน';
                            } elseif ($_GET['error'] == 'upload') {
                                echo 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ';
                            } else {
                                echo 'เกิดข้อผิดพลาดในการเพิ่มพระเครื่อง';
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <form action="/seller/add_product_process.php" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-info-circle"></i>
                            ข้อมูลพระเครื่อง
                        </h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="amulet_name">ชื่อพระเครื่อง<span class="required">*</span></label>
                                <input type="text" id="amulet_name" name="amulet_name" placeholder="เช่น พระสมเด็จวัดระฆัง" required>
                                <p class="input-hint">กรอกชื่อพระเครื่องให้ชัดเจน</p>
                            </div>

                            <div class="form-group">
                                <label for="categoryId">หมวดหมู่<span class="required">*</span></label>
                                <select id="categoryId" name="categoryId" required>
                                    <option value="">-- เลือกหมวดหมู่ --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="source">ที่มา/รายละเอียด<span class="required">*</span></label>
                            <textarea id="source" name="source" placeholder="ระบุที่มา ประวัติ หรือรายละเอียดของพระเครื่อง" required></textarea>
                            <p class="input-hint">ข้อมูลที่ละเอียดจะช่วยให้ลูกค้าตัดสินใจได้ง่ายขึ้น</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">ราคา (บาท)<span class="required">*</span></label>
                                <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label for="quantity">จำนวน<span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" placeholder="0" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-images"></i>
                            รูปภาพพระเครื่อง <span style="color:#ef4444;font-size:14px">(ต้องอัปโหลดอย่างน้อย 5 รูป)</span>
                        </h2>

                        <div class="form-group full-width">
                            <label>รูปพระเครื่อง <span style="color:#ef4444">*</span></label>
                            <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageInput').click()">
                                <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                <div class="upload-text">
                                    <h3>คลิกเพื่อเลือกรูปภาพ (เลือกได้หลายรูปพร้อมกัน)</h3>
                                    <p>รองรับไฟล์ JPG, PNG, GIF – ขนาดไม่เกิน 5MB ต่อรูป</p>
                                </div>
                                <input type="file" id="imageInput" name="images[]" accept="image/*" multiple>
                            </div>
                            <div id="imgCountBadge" class="img-count-badge bad" style="display:none">
                                <i class="fa-solid fa-images"></i>
                                <span id="imgCountText">0 รูป</span>
                            </div>
                            <div class="preview-grid" id="previewGrid"></div>
                            <p class="input-hint" style="margin-top:10px">แนะนำขนาดรูป 800x800 พิกเซล – รูปแรกจะเป็นรูปปกพระเครื่อง</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="/views/seller/products.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark"></i> ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check"></i> บันทึกพระเครื่อง
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const imageInput   = document.getElementById('imageInput');
        const previewGrid  = document.getElementById('previewGrid');
        const imgCountBadge= document.getElementById('imgCountBadge');
        const imgCountText = document.getElementById('imgCountText');
        const uploadArea   = document.getElementById('uploadArea');

        let selectedFiles = []; // DataTransfer workaround

        function updateBadge() {
            const n = selectedFiles.length;
            imgCountBadge.style.display = n > 0 ? 'inline-flex' : 'none';
            imgCountText.textContent = n + ' รูป' + (n < 5 ? ' (ต้องการอีก ' + (5-n) + ' รูป)' : '');
            imgCountBadge.className = 'img-count-badge ' + (n >= 5 ? 'ok' : n > 0 ? 'low' : 'bad');
            uploadArea.classList.toggle('has-images', n > 0);
        }

        function renderPreviews() {
            previewGrid.innerHTML = '';
            selectedFiles.forEach((file, idx) => {
                const reader = new FileReader();
                reader.onload = e => {
                    const item = document.createElement('div');
                    item.className = 'preview-item';
                    item.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" class="rm-btn" onclick="removeImage(${idx})"><i class="fa-solid fa-times"></i></button>`
                                   + (idx===0 ? '<div style="position:absolute;bottom:4px;left:4px;background:rgba(16,185,129,.85);color:#fff;font-size:9px;padding:2px 6px;border-radius:4px">ปก</div>' : '');
                    previewGrid.appendChild(item);
                };
                reader.readAsDataURL(file);
            });
            syncFilesInput();
        }

        function removeImage(idx) {
            selectedFiles.splice(idx, 1);
            updateBadge();
            renderPreviews();
        }

        function syncFilesInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            imageInput.files = dt.files;
        }

        imageInput.addEventListener('change', function() {
            const maxSize = 5 * 1024 * 1024;
            Array.from(this.files).forEach(file => {
                if (file.size > maxSize) {
                    alert('ไฟล์ "' + file.name + '" มีขนาดเกิน 5MB กรุณาเลือกใหม่');
                    return;
                }
                selectedFiles.push(file);
            });
            updateBadge();
            renderPreviews();
        });

        // Drag & drop
        uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.style.borderColor='#10b981'; });
        uploadArea.addEventListener('dragleave', () => { uploadArea.style.borderColor=''; });
        uploadArea.addEventListener('drop', e => {
            e.preventDefault();
            uploadArea.style.borderColor='';
            const maxSize = 5 * 1024 * 1024;
            Array.from(e.dataTransfer.files).forEach(file => {
                if (file.size > maxSize) { alert('ไฟล์ "' + file.name + '" มีขนาดเกิน 5MB'); return; }
                selectedFiles.push(file);
            });
            updateBadge(); renderPreviews();
        });

        // Validate before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const price    = parseFloat(document.getElementById('price').value);
            const quantity = parseInt(document.getElementById('quantity').value);
            if (price < 0 || quantity < 0) {
                e.preventDefault();
                alert('ราคาและจำนวนต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0');
                return false;
            }
            if (selectedFiles.length < 5) {
                e.preventDefault();
                alert('กรุณาอัปโหลดรูปภาพอย่างน้อย 5 รูป (ปัจจุบัน: ' + selectedFiles.length + ' รูป)');
                return false;
            }
        });
    </script>
</body>

</html>