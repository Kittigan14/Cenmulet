<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>สมัครผู้ขาย - Cenmulet</title>
</head>
<body>

<div class="navbar">
    <div class="logo">
        <div class="content-logo">
            <img src="/public/images/image.png" alt="Cenmulet">
            <h2>Cenmulet</h2>
        </div>
        <p>Amulet market place ตลาดพระเครื่อง</p>
    </div>
</div>

<div class="auth-page">
    <div class="auth-box wide">
        <div class="auth-header">
            <div class="icon-wrap"><i class="fa-solid fa-store"></i></div>
            <h1>สมัครผู้ขาย</h1>
            <p>เริ่มต้นขายพระเครื่องกับ Cenmulet</p>
        </div>
        <div class="auth-body">

            <!-- Notice about approval -->
            <div class="info-message">
                <i class="fa-solid fa-circle-info"></i>
                <span>การสมัครผู้ขายต้องผ่านการตรวจสอบและอนุมัติจากผู้ดูแลระบบก่อน คุณจะได้รับแจ้งผลภายใน 1-3 วันทำการ</span>
            </div>

            <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php
                    $errs = [
                        'username_exists' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว',
                        'empty'           => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน',
                        'invalid_file'    => 'ประเภทไฟล์ไม่ถูกต้อง',
                    ];
                    echo $errs[$_GET['error']] ?? 'เกิดข้อผิดพลาดในการสมัครสมาชิก';
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'pending'): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <span>ส่งข้อมูลสมัครเรียบร้อยแล้ว! ระบบจะแจ้งผลการอนุมัติทางผู้ดูแลระบบ กรุณารอการยืนยัน</span>
            </div>
            <?php endif; ?>

            <form action="/auth/register_seller_process.php" method="POST" enctype="multipart/form-data">

                <!-- ข้อมูลร้านค้า -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-store"></i> ข้อมูลร้านค้า
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="store_name">ชื่อร้าน <span style="color:red">*</span></label>
                            <input type="text" name="store_name" id="store_name" placeholder="กรอกชื่อร้าน" required>
                        </div>
                        <div class="form-group">
                            <label for="img_store">รูปร้านค้า</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_store" id="img_store" accept="image/*">
                                <label for="img_store" class="file-input-label">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span id="store-file-name">เลือกรูปร้าน</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">ที่อยู่ร้านค้า <span style="color:red">*</span></label>
                        <textarea name="address" id="address" placeholder="กรอกที่อยู่ร้านค้า" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="pay_contax">ช่องทางการชำระเงิน</label>
                        <input type="text" name="pay_contax" id="pay_contax" placeholder="เช่น เลขบัญชีธนาคาร, พร้อมเพย์">
                    </div>
                </div>

                <!-- ข้อมูลส่วนตัว -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-id-card"></i> ข้อมูลส่วนตัว (สำหรับยืนยันตัวตน)
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">ชื่อ-นามสกุล <span style="color:red">*</span></label>
                            <input type="text" name="fullname" id="fullname" placeholder="ตามบัตรประชาชน" required>
                        </div>
                        <div class="form-group">
                            <label for="tel">เบอร์โทรศัพท์ <span style="color:red">*</span></label>
                            <input type="tel" name="tel" id="tel" placeholder="กรอกเบอร์โทรศัพท์" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_per">เลขบัตรประชาชน <span style="color:red">*</span></label>
                            <input type="text" name="id_per" id="id_per" placeholder="13 หลัก" maxlength="13" required>
                        </div>
                        <div class="form-group">
                            <label for="img_per">รูปบัตรประชาชน <span style="color:red">*</span></label>
                            <div class="file-input-wrapper">
                                <input type="file" name="img_per" id="img_per" accept="image/*" required>
                                <label for="img_per" class="file-input-label">
                                    <i class="fa-solid fa-id-card"></i>
                                    <span id="id-file-name">เลือกรูปบัตรประชาชน</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลบัญชี -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fa-solid fa-lock"></i> ข้อมูลบัญชีเข้าสู่ระบบ
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">ชื่อผู้ใช้ <span style="color:red">*</span></label>
                            <input type="text" name="username" id="username" placeholder="กรอกชื่อผู้ใช้" required>
                        </div>
                        <div class="form-group">
                            <label for="password">รหัสผ่าน <span style="color:red">*</span></label>
                            <input type="password" name="password" id="password" placeholder="กรอกรหัสผ่าน" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> ส่งคำขอสมัครผู้ขาย
                </button>
            </form>

            <div class="auth-footer">
                มีบัญชีอยู่แล้ว? <a href="/views/auth/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('img_store').addEventListener('change', function(e) {
    const f = e.target.files[0];
    if (f) document.getElementById('store-file-name').textContent = f.name;
});
document.getElementById('img_per').addEventListener('change', function(e) {
    const f = e.target.files[0];
    if (f) document.getElementById('id-file-name').textContent = f.name;
});
document.getElementById('id_per').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g,'');
});
</script>
</body>
</html>