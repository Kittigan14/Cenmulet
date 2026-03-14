<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>เข้าสู่ระบบ - Cenmulet</title>
</head>
<body>

<div class="navbar">
    <a href="/views/user/home.php" class="logo" style="text-decoration:none;color:inherit;cursor:pointer;">
        <div class="content-logo">
            <img src="/public/images/image.png" alt="Cenmulet">
            <h2>Cenmulet</h2>
        </div>
        <p>Amulet market place ตลาดพระเครื่อง</p>
    </a>
</div>

<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <div class="icon-wrap"><i class="fa-solid fa-right-to-bracket"></i></div>
            <h1>เข้าสู่ระบบ</h1>
            <p>ยินดีต้อนรับกลับสู่ Cenmulet</p>
        </div>
        <div class="auth-body">

            <?php if (isset($_GET['error'])): ?>
                <?php $err = $_GET['error']; ?>
                <?php if ($err === 'seller_pending'): ?>
                <div class="info-message">
                    <i class="fa-solid fa-clock"></i>
                    <span>บัญชีร้านค้าของคุณอยู่ระหว่างรอการอนุมัติจากผู้ดูแลระบบ กรุณารอการยืนยัน</span>
                </div>
                <?php elseif ($err === 'seller_rejected'): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>คำขอสมัครร้านค้าถูกปฏิเสธ</strong><br>
                        <?php if (!empty($_GET['reason'])): ?>
                        <span style="font-size:13px">เหตุผล: <?php echo htmlspecialchars(urldecode($_GET['reason'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>
                        <?php
                        $errors = [
                            'empty'          => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน',
                            'user_not_found' => 'ไม่พบชื่อผู้ใช้นี้ในระบบ',
                            'wrong_password' => 'รหัสผ่านไม่ถูกต้อง',
                        ];
                        echo $errors[$err] ?? 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ';
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'registered'): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <span>สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</span>
            </div>
            <?php endif; ?>

            <form action="/auth/login_process.php" method="POST">
                <div class="form-group">
                    <label for="username"><i class="fa-solid fa-user"></i> ชื่อผู้ใช้</label>
                    <input type="text" name="username" id="username"
                           placeholder="กรอกชื่อผู้ใช้" required autofocus
                           value="<?php echo htmlspecialchars($_GET['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> รหัสผ่าน</label>
                    <input type="password" name="password" id="password"
                           placeholder="กรอกรหัสผ่าน" required>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
                </button>
            </form>

            <div class="auth-footer" style="margin-top:18px;">
                ยังไม่มีบัญชี?
                <a href="/views/auth/register_user.php">สมัครผู้ใช้</a> &nbsp;|&nbsp;
                <a href="/views/auth/register_seller.php">สมัครร้านค้า</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>