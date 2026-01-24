<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>เข้าสู่ระบบ - Cenmulet</title>
</head>

<body>

    <div class="navbar">
        <ul>
            <li><a href="/views/index.php">หน้าแรก</a></li>
            <li><a href="">หมวดหมู่พระเครื่อง</a></li>
            <li><a href="">รายการเช่าพระเครื่อง</a></li>
        </ul>

        <div class="logo">
            <div class="content-logo">
                <img src="/images/image.png" alt="">
                <h2>Cenmulet</h2>
            </div>
            <p>Amulet market place ตลาดพระเครื่อง</p>
        </div>

        <div class="serverbar">
            <div class="searchbar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="ค้นหาพระเครื่อง...">
            </div>

            <div class="btn-user">
                <a href="/views/auth/login.php"> <i class="fa-solid fa-user"></i> </a>
                <a href=""> <i class="fa-solid fa-cart-shopping"></i> </a>
            </div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>เข้าสู่ระบบ</h1>
                <p>ยินดีต้อนรับกลับสู่ Cenmulet</p>
            </div>

            <!-- <div class="info-box">
                <i class="fa-solid fa-circle-info"></i>
                <span>ระบบจะตรวจสอบประเภทผู้ใช้โดยอัตโนมัติ ไม่ต้องเลือกประเภทผู้ใช้</span>
            </div> -->

            <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php
                        if ($_GET['error'] == 'empty') {
                            echo 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
                        } elseif ($_GET['error'] == 'user_not_found') {
                            echo 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
                        } elseif ($_GET['error'] == 'wrong_password') {
                            echo 'รหัสผ่านไม่ถูกต้อง';
                        } else {
                            echo 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
                        }
                        ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <span>สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</span>
            </div>
            <?php endif; ?>

            <form action="/auth/login_process.php" method="POST">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input type="text" name="username" id="username" placeholder="กรอกชื่อผู้ใช้" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" name="password" id="password" placeholder="กรอกรหัสผ่าน" required>
                </div>

                <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
            </form>

            <div class="register-link">
                ยังไม่มีบัญชี?
                <a href="/views/auth/register_user.php">สมัครสมาชิกผู้ใช้</a> |
                <a href="/views/auth/register_seller.php">สมัครผู้ขาย</a>
            </div>
        </div>
    </div>

</body>

</html>