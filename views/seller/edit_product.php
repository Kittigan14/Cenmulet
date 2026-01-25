<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: /views/auth/login.php");
    exit;
}

$seller_id = $_SESSION['user_id'];
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
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

try {
    $stmt = $db->prepare("SELECT * FROM sellers WHERE id = :id");
    $stmt->execute([':id' => $seller_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

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
    <title>แก้ไขสินค้า - Cenmulet</title>
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
            margin-bottom: 10px;
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

        .current-image {
            margin-top: 10px;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }

        .current-image p {
            font-size: 13px;
            color: #6b7280;
            margin-top: 8px;
        }

        .image-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f9fafb;
            transition: all 0.3s;
            cursor: pointer;
        }

        .image-upload-area:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .image-upload-area.has-image {
            border-color: #10b981;
            background: #fff;
        }

        .upload-icon {
            font-size: 48px;
            color: #10b981;
            margin-bottom: 15px;
        }

        .upload-text h3 {
            font-size: 16px;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .upload-text p {
            font-size: 14px;
            color: #6b7280;
        }

        #imageInput {
            display: none;
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            margin-top: 15px;
            display: none;
        }

        .image-preview.show {
            display: block;
        }

        .remove-image {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #fee2e2;
            color: #ef4444;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .remove-image:hover {
            background: #fecaca;
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

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        .btn-danger:hover {
            background: #dc2626;
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
                <li><a href="/views/seller/products.php" class="active"><i class="fa-solid fa-box"></i> จัดการสินค้า</a></li>
                <li><a href="/views/seller/add_product.php"><i class="fa-solid fa-plus"></i> เพิ่มสินค้า</a></li>
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
                <li><a href="/views/seller/seller_profile.php"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>แก้ไขสินค้า</h1>
                <div class="breadcrumb">
                    <a href="/views/seller/dashboard.php">แดชบอร์ด</a>
                    <span>/</span>
                    <a href="/views/seller/products.php">จัดการสินค้า</a>
                    <span>/</span>
                    <span>แก้ไข</span>
                </div>
            </div>

            <div class="form-container">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>แก้ไขสินค้าสำเร็จ!</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>
                            <?php
                            if ($_GET['error'] == 'empty') {
                                echo 'กรุณากรอกข้อมูลให้ครบถ้วน';
                            } else {
                                echo 'เกิดข้อผิดพลาดในการแก้ไขสินค้า';
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <form action="/seller/edit_product_process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="old_image" value="<?php echo $product['image']; ?>">

                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-info-circle"></i>
                            ข้อมูลสินค้า
                        </h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="amulet_name">ชื่อพระเครื่อง<span class="required">*</span></label>
                                <input type="text" id="amulet_name" name="amulet_name" value="<?php echo htmlspecialchars($product['amulet_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="categoryId">หมวดหมู่<span class="required">*</span></label>
                                <select id="categoryId" name="categoryId" required>
                                    <option value="">-- เลือกหมวดหมู่ --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($category['id'] == $product['categoryId']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
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
                                <input type="number" id="price" name="price" value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label for="quantity">จำนวน<span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" value="<?php echo $product['quantity']; ?>" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-image"></i>
                            รูปภาพสินค้า
                        </h2>

                        <?php if ($product['image']): ?>
                            <div class="current-image">
                                <img src="/uploads/amulets/<?php echo htmlspecialchars($product['image']); ?>" alt="Current Image">
                                <p>รูปภาพปัจจุบัน (อัพโหลดรูปใหม่เพื่อเปลี่ยน)</p>
                            </div>
                        <?php endif; ?>

                        <div class="form-group full-width" style="margin-top: 20px;">
                            <label for="imageInput">เปลี่ยนรูปภาพ (ถ้าต้องการ)</label>
                            <div class="image-upload-area" id="uploadArea">
                                <div class="upload-icon">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div class="upload-text">
                                    <h3>คลิกเพื่อเลือกรูปภาพใหม่</h3>
                                    <p>รองรับไฟล์ JPG, PNG, GIF (ขนาดไม่เกิน 5MB)</p>
                                </div>
                                <input type="file" id="imageInput" name="image" accept="image/*">
                                <img id="imagePreview" class="image-preview" alt="Preview">
                                <div id="removeImageBtn" class="remove-image" style="display: none;">
                                    <i class="fa-solid fa-trash"></i> ลบรูปภาพ
                                </div>
                            </div>
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
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const removeImageBtn = document.getElementById('removeImageBtn');

        uploadArea.addEventListener('click', (e) => {
            if (e.target !== removeImageBtn && !removeImageBtn.contains(e.target)) {
                imageInput.click();
            }
        });

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('ไฟล์มีขนาดใหญ่เกินไป! กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 5MB');
                    imageInput.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.add('show');
                    removeImageBtn.style.display = 'inline-block';
                    uploadArea.classList.add('has-image');
                }
                reader.readAsDataURL(file);
            }
        });

        removeImageBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            imageInput.value = '';
            imagePreview.src = '';
            imagePreview.classList.remove('show');
            removeImageBtn.style.display = 'none';
            uploadArea.classList.remove('has-image');
        });
    </script>
</body>

</html>