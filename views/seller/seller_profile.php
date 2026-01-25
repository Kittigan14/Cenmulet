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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $store_name = $_POST['store_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $pay_contax = $_POST['pay_contax'] ?? '';
    
    $img_store = $seller['img_store'];
    $img_per = $seller['img_per'];
    
    if (isset($_FILES['img_store']) && $_FILES['img_store']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['img_store']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'store_' . uniqid() . '.' . $ext;
            $upload_path = __DIR__ . "/../../uploads/stores/";
            
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['img_store']['tmp_name'], $upload_path . $new_filename)) {
                if ($img_store && file_exists($upload_path . $img_store)) {
                    unlink($upload_path . $img_store);
                }
                $img_store = $new_filename;
            }
        }
    }
    
    if (isset($_FILES['img_per']) && $_FILES['img_per']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['img_per']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'id_' . uniqid() . '.' . $ext;
            $upload_path = __DIR__ . "/../../uploads/ids/";
            
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['img_per']['tmp_name'], $upload_path . $new_filename)) {
                // ลบรูปเก่า
                if ($img_per && file_exists($upload_path . $img_per)) {
                    unlink($upload_path . $img_per);
                }
                $img_per = $new_filename;
            }
        }
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE sellers 
            SET fullname = :fullname,
                store_name = :store_name,
                address = :address,
                tel = :tel,
                pay_contax = :pay_contax,
                img_store = :img_store,
                img_per = :img_per
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':fullname' => $fullname,
            ':store_name' => $store_name,
            ':address' => $address,
            ':tel' => $tel,
            ':pay_contax' => $pay_contax,
            ':img_store' => $img_store,
            ':img_per' => $img_per,
            ':id' => $seller_id
        ]);
        
        header("Location: /views/seller/profile.php?success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>ข้อมูลร้านค้า - Cenmulet</title>
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

        .user-info h3 {
            font-size: 16px;
            margin-bottom: 5px;
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

        .profile-section {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #10b981;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 14px;
            color: #374151;
            font-weight: 600;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: "Kanit", sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #10b981;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .image-upload-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .upload-box {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-box:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .upload-box i {
            font-size: 40px;
            color: #10b981;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .upload-box small {
            color: #9ca3af;
            font-size: 12px;
        }

        .current-image {
            margin-top: 15px;
            max-width: 100%;
            border-radius: 10px;
        }

        .btn-submit {
            padding: 14px 30px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .info-card {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .info-card h4 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card p {
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
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
                <li><a href="/views/seller/orders.php"><i class="fa-solid fa-shopping-cart"></i> คำสั่งซื้อ</a></li>
                <li><a href="/views/seller/profile.php" class="active"><i class="fa-solid fa-user"></i> ข้อมูลร้าน</a></li>
                <li><a href="/auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>ข้อมูลร้านค้า</h1>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>บันทึกข้อมูลสำเร็จ!</span>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fa-solid fa-store"></i>
                        ข้อมูลร้านค้า
                    </h2>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">ชื่อร้านค้า *</label>
                            <input type="text" name="store_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller['store_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล เจ้าของร้าน *</label>
                            <input type="text" name="fullname" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller['fullname']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์ *</label>
                            <input type="tel" name="tel" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller['tel']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">เลขบัตรประชาชน</label>
                            <input type="text" name="id_per" class="form-control" 
                                   value="<?php echo htmlspecialchars($seller['id_per'] ?? ''); ?>" readonly>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">ที่อยู่ร้านค้า *</label>
                            <textarea name="address" class="form-control" required><?php echo htmlspecialchars($seller['address']); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">ข้อมูลการชำระเงิน (เลขบัญชี, ธนาคาร) *</label>
                            <textarea name="pay_contax" class="form-control" 
                                      placeholder="ธนาคาร: กสิกรไทย&#10;เลขที่บัญชี: 123-4-56789-0&#10;ชื่อบัญชี: ร้านพระเครื่อง ABC" 
                                      required><?php echo htmlspecialchars($seller['pay_contax']); ?></textarea>
                        </div>
                    </div>

                    <div class="image-upload-section">
                        <div class="form-group">
                            <label class="form-label">รูปร้านค้า</label>
                            <div class="upload-box" onclick="document.getElementById('img_store').click()">
                                <i class="fa-solid fa-store"></i>
                                <p>คลิกเพื่ออัพโหลดรูปร้านค้า</p>
                                <small>JPG, PNG (ขนาดไม่เกิน 5MB)</small>
                            </div>
                            <input type="file" id="img_store" name="img_store" accept="image/*" style="display: none;" onchange="previewImage(this, 'store_preview')">
                            <?php if ($seller['img_store']): ?>
                                <img id="store_preview" src="/uploads/stores/<?php echo htmlspecialchars($seller['img_store']); ?>" class="current-image" alt="รูปร้านค้า">
                            <?php else: ?>
                                <img id="store_preview" src="" class="current-image" alt="" style="display: none;">
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">รูปบัตรประชาชน</label>
                            <div class="upload-box" onclick="document.getElementById('img_per').click()">
                                <i class="fa-solid fa-id-card"></i>
                                <p>คลิกเพื่ออัพโหลดบัตรประชาชน</p>
                                <small>JPG, PNG (ขนาดไม่เกิน 5MB)</small>
                            </div>
                            <input type="file" id="img_per" name="img_per" accept="image/*" style="display: none;" onchange="previewImage(this, 'id_preview')">
                            <?php if ($seller['img_per']): ?>
                                <img id="id_preview" src="/uploads/ids/<?php echo htmlspecialchars($seller['img_per']); ?>" class="current-image" alt="บัตรประชาชน">
                            <?php else: ?>
                                <img id="id_preview" src="" class="current-image" alt="" style="display: none;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4>
                            <i class="fa-solid fa-info-circle"></i>
                            ข้อมูลบัญชีผู้ใช้
                        </h4>
                        <p>
                            <strong>ชื่อผู้ใช้:</strong> <?php echo htmlspecialchars($seller['username']); ?><br>
                            <strong>หมายเหตุ:</strong> หากต้องการเปลี่ยนรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ
                        </p>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-save"></i>
                            บันทึกข้อมูล
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(previewId);
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>