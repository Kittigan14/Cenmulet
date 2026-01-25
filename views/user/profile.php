<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// จัดการการอัพเดทข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $address = $_POST['address'] ?? '';
    
    $image = $user['image'];
    
    // อัพโหลดรูปโปรไฟล์
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'user_' . uniqid() . '.' . $ext;
            $upload_path = __DIR__ . "/../../uploads/users/";
            
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path . $new_filename)) {
                if ($image && file_exists($upload_path . $image)) {
                    unlink($upload_path . $image);
                }
                $image = $new_filename;
            }
        }
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET fullname = :fullname,
                tel = :tel,
                address = :address,
                image = :image
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':fullname' => $fullname,
            ':tel' => $tel,
            ':address' => $address,
            ':image' => $image,
            ':id' => $user_id
        ]);
        
        header("Location: /views/user/profile.php?success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// นับจำนวนสินค้าในตะกร้า
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $cart_count = 0;
}

// ตั้งค่าหน้าปัจจุบันสำหรับ navbar
$active_page = 'profile';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>ข้อมูลส่วนตัว - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&family=Sriracha&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Kanit", sans-serif;
            background: #f9fafb;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
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
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f4f6;
        }

        .section-header i {
            font-size: 32px;
            color: #10b981;
        }

        .section-header h1 {
            font-size: 28px;
            color: #1a1a1a;
        }

        .profile-image-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .current-profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #10b981;
            margin-bottom: 20px;
        }

        .default-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid #10b981;
        }

        .default-avatar i {
            font-size: 64px;
            color: #9ca3af;
        }

        .upload-avatar-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .upload-avatar-btn:hover {
            background: #e5e7eb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
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

        .form-control:disabled {
            background: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .info-card {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
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

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10b981;
            color: #fff;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span>/</span>
            <span>ข้อมูลส่วนตัว</span>
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

        <div class="profile-section">
            <div class="section-header">
                <i class="fa-solid fa-user-circle"></i>
                <h1>ข้อมูลส่วนตัว</h1>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="profile-image-section">
                    <?php if ($user['image']): ?>
                        <img id="profile_preview" src="/uploads/users/<?php echo htmlspecialchars($user['image']); ?>" 
                             class="current-profile-image" alt="Profile">
                    <?php else: ?>
                        <div id="default_avatar" class="default-avatar">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <img id="profile_preview" src="" class="current-profile-image" alt="" style="display: none;">
                    <?php endif; ?>
                    <br>
                    <label for="image" class="upload-avatar-btn">
                        <i class="fa-solid fa-camera"></i>
                        เปลี่ยนรูปโปรไฟล์
                    </label>
                    <input type="file" id="image" name="image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล *</label>
                        <input type="text" name="fullname" class="form-control" 
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์ *</label>
                        <input type="tel" name="tel" class="form-control" 
                               value="<?php echo htmlspecialchars($user['tel']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เลขบัตรประชาชน</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($user['id_per'] ?? 'ไม่ได้ระบุ'); ?>" disabled>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">ที่อยู่สำหรับจัดส่ง *</label>
                        <textarea name="address" class="form-control" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                </div>

                <div class="info-card">
                    <h4>
                        <i class="fa-solid fa-info-circle"></i>
                        หมายเหตุ
                    </h4>
                    <p>
                        • กรุณากรอกข้อมูลให้ถูกต้องและครบถ้วน เพื่อความสะดวกในการจัดส่งสินค้า<br>
                        • หากต้องการเปลี่ยนรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ<br>
                        • ข้อมูลของคุณจะถูกเก็บรักษาความปลอดภัยตามนโยบายของเรา
                    </p>
                </div>

                <div class="button-group">
                    <a href="/views/user/home.php" class="btn btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i>
                        บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profile_preview');
                    const defaultAvatar = document.getElementById('default_avatar');
                    
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    if (defaultAvatar) {
                        defaultAvatar.style.display = 'none';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>