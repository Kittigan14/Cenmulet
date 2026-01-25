<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/seller.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สมัครผู้ขาย - Cenmulet</title>
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
                <h1>สมัครผู้ขาย</h1>
                <p>เริ่มต้นขายพระเครื่องกับ Cenmulet</p>
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
                    <span>สมัครผู้ขายสำเร็จ! กรุณาเข้าสู่ระบบ</span>
                </div>
            <?php endif; ?>

            <form action="/auth/register_seller_process.php" method="POST" enctype="multipart/form-data">
                
                <!-- ข้อมูลร้านค้า -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fa-solid fa-store"></i>
                        ข้อมูลร้านค้า
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="store_name">ชื่อร้าน <span style="color: red;">*</span></label>
                            <input type="text" name="store_name" id="store_name" placeholder="กรอกชื่อร้าน" required>
                        </div>

                        <div class="form-group">
                            <label for="img_store">รูปร้าน</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_store" id="img_store" accept="image/*">
                                <label for="img_store" class="file-input-label">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span id="store-file-name">เลือกรูปร้าน</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">ที่อยู่ร้าน <span style="color: red;">*</span></label>
                        <textarea name="address" id="address" placeholder="กรอกที่อยู่ร้านค้า" required></textarea>
                    </div>
                </div>

                <!-- ข้อมูลส่วนตัว -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fa-solid fa-user"></i>
                        ข้อมูลส่วนตัว
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">ชื่อ-นามสกุล <span style="color: red;">*</span></label>
                            <input type="text" name="fullname" id="fullname" placeholder="กรอกชื่อ-นามสกุล" required>
                        </div>

                        <div class="form-group">
                            <label for="tel">เบอร์โทรศัพท์ <span style="color: red;">*</span></label>
                            <input type="tel" name="tel" id="tel" placeholder="กรอกเบอร์โทรศัพท์" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_per">เลขบัตรประชาชน <span style="color: red;">*</span></label>
                            <input type="text" name="id_per" id="id_per" placeholder="กรอกเลขบัตรประชาชน 13 หลัก" maxlength="13" required>
                        </div>

                        <div class="form-group">
                            <label for="img_per">รูปบัตรประชาชน</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_per" id="img_per" accept="image/*">
                                <label for="img_per" class="file-input-label">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span id="id-file-name">เลือกรูปบัตรประชาชน</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลบัญชี -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fa-solid fa-lock"></i>
                        ข้อมูลบัญชี
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">ชื่อผู้ใช้ <span style="color: red;">*</span></label>
                            <input type="text" name="username" id="username" placeholder="กรอกชื่อผู้ใช้" required>
                        </div>

                        <div class="form-group">
                            <label for="password">รหัสผ่าน <span style="color: red;">*</span></label>
                            <input type="password" name="password" id="password" placeholder="กรอกรหัสผ่าน" required>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="pay_contax">ช่องทางการชำระเงิน</label>
                        <input type="text" name="pay_contax" id="pay_contax" placeholder="เช่น เลขบัญชีธนาคาร, พร้อมเพย์">
                    </div>
                </div>

                <button type="submit" class="btn-register">สมัครผู้ขาย</button>
            </form>

            <div class="login-link">
                มีบัญชีอยู่แล้ว? <a href="/views/auth/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

    <script>
        // แสดงชื่อไฟล์เมื่อเลือก - รูปร้าน
        document.getElementById('img_store').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('store-file-name').textContent = fileName;
            }
        });

        document.getElementById('img_per').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('id-file-name').textContent = fileName;
            }
        });

        document.getElementById('id_per').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>

</body>

</html>