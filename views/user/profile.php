<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: /views/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $tel      = $_POST['tel']      ?? '';
    $address  = $_POST['address']  ?? '';
    $image    = $user['image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_filename = 'user_' . uniqid() . '.' . $ext;
            $upload_path  = __DIR__ . "/../../uploads/users/";
            if (!is_dir($upload_path)) mkdir($upload_path, 0777, true);

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path . $new_filename)) {
                if ($image && file_exists($upload_path . $image)) unlink($upload_path . $image);
                $image = $new_filename;
            }
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE users SET fullname = :fullname, tel = :tel, address = :address, image = :image
            WHERE id = :id
        ");
        $stmt->execute([':fullname' => $fullname, ':tel' => $tel, ':address' => $address, ':image' => $image, ':id' => $user_id]);
        header("Location: /views/user/profile.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) { $cart_count = 0; }

$active_page = 'profile';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/profile.css">
    <title>ข้อมูลส่วนตัว - Cenmulet</title>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span class="separator"><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></span>
            <span>ข้อมูลส่วนตัว</span>
        </nav>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>บันทึกข้อมูลสำเร็จ!</span>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-card-header">
                <div class="header-icon"><i class="fa-solid fa-user-circle"></i></div>
                <div>
                    <h1>ข้อมูลส่วนตัว</h1>
                    <p>จัดการข้อมูลโปรไฟล์และที่อยู่สำหรับจัดส่ง</p>
                </div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data">

                <!-- Avatar -->
                <div class="avatar-section">
                    <div class="avatar-wrap">
                        <?php if ($user['image']): ?>
                            <img id="profile_preview"
                                 src="/uploads/users/<?php echo htmlspecialchars($user['image']); ?>"
                                 class="avatar-img" alt="Profile">
                        <?php else: ?>
                            <div id="default_avatar" class="avatar-default">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <img id="profile_preview" src="" class="avatar-img" alt="" style="display:none;">
                        <?php endif; ?>
                    </div>

                    <label for="image_upload" class="btn-upload-avatar">
                        <i class="fa-solid fa-camera"></i>
                        เปลี่ยนรูปโปรไฟล์
                    </label>
                    <input type="file" id="image_upload" name="image" accept="image/*"
                           style="display:none;" onchange="previewAvatar(this)">
                </div>

                <!-- Fields -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="fullname">ชื่อ-นามสกุล *</label>
                        <input type="text" id="fullname" name="fullname" class="form-control"
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tel">เบอร์โทรศัพท์ *</label>
                        <input type="tel" id="tel" name="tel" class="form-control"
                               value="<?php echo htmlspecialchars($user['tel']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">เลขบัตรประชาชน</label>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($user['id_per'] ?? 'ไม่ได้ระบุ'); ?>" disabled>
                    </div>

                    <div class="form-group full-span">
                        <label class="form-label" for="address">ที่อยู่สำหรับจัดส่ง *</label>
                        <textarea id="address" name="address" class="form-control" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                </div>

                <div class="info-box" style="margin-bottom:var(--space-xl);">
                    <h4><i class="fa-solid fa-info-circle"></i> หมายเหตุ</h4>
                    <p>
                        • กรุณากรอกข้อมูลให้ถูกต้องและครบถ้วน เพื่อความสะดวกในการจัดส่งสินค้า<br>
                        • หากต้องการเปลี่ยนรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ<br>
                        • ข้อมูลของคุณจะถูกเก็บรักษาความปลอดภัยตามนโยบายของเรา
                    </p>
                </div>

                <div class="form-btn-row">
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
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader  = new FileReader();
                reader.onload = e => {
                    const preview = document.getElementById('profile_preview');
                    const defAvt  = document.getElementById('default_avatar');
                    preview.src   = e.target.result;
                    preview.style.display = 'block';
                    if (defAvt) defAvt.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>