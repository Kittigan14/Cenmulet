<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/customer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สมัครสมาชิกผู้ใช้ - Cenmulet</title>
</head>

<body>

    <div class="navbar">

        <div class="logo">
            <div class="content-logo">
                <img src="/public/images/image.png" alt="">
                <h2>Cenmulet</h2>
            </div>
            <p>Amulet market place ตลาดพระเครื่อง</p>
        </div>

    </div>

    <div class="register-container">
        <div class="register-box">
            <div class="register-header">
                <h1>สมัครสมาชิกผู้ใช้</h1>
                <p>เริ่มต้นใช้งาน Cenmulet วันนี้</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>
                        <?php
                        if ($_GET['error'] == 'username_exists') {
                            echo 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
                        } else {
                            echo 'เกิดข้อผิดพลาดในการสมัครสมาชิก';
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</span>
                </div>
            <?php endif; ?>

            <form action="/auth/register_user_process.php" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fullname">ชื่อ-นามสกุล <span style="color: red;">*</span></label>
                        <input type="text" name="fullname" id="fullname" placeholder="กรอกชื่อ-นามสกุล" required>
                    </div>

                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้ <span style="color: red;">*</span></label>
                        <input type="text" name="username" id="username" placeholder="กรอกชื่อผู้ใช้" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">รหัสผ่าน <span style="color: red;">*</span></label>
                        <input type="password" name="password" id="password" placeholder="กรอกรหัสผ่าน" required>
                    </div>

                    <div class="form-group">
                        <label for="tel">เบอร์โทรศัพท์ <span style="color: red;">*</span></label>
                        <input type="tel" name="tel" id="tel" placeholder="กรอกเบอร์โทรศัพท์" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="address">ที่อยู่ <span style="color: red;">*</span></label>
                    <textarea name="address" id="address" placeholder="กรอกที่อยู่สำหรับจัดส่ง" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="id_per">เลขบัตรประชาชน <span style="color: red;">*</span></label>
                        <input type="text" name="id_per" id="id_per" placeholder="กรอกเลขบัตรประชาชน 13 หัก" maxlength="13" required>
                    </div>

                    <div class="form-group">
                        <label for="image">รูปโปรไฟล์</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="image" id="image" accept="image/*">
                            <label for="image" class="file-input-label">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span>เลือกรูปภาพ</span>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-register">สมัครสมาชิก</button>
            </form>

            <div class="login-link">
                มีบัญชีอยู่แล้ว? <a href="/views/auth/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('image').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-input-label span');
                label.textContent = fileName;
            }
        });

        document.getElementById('id_per').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>

</body>

</html>