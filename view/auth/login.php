<?php if (isset($_GET['error'])): ?>
    <p style="color:red;">ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</p>
<?php endif; ?>

<section class="auth-container">
    <div class="auth-box">
        <h2>เข้าสู่ระบบ</h2>

        <form action="/auth/login_process.php" method="POST">
            <select name="role" required>
                <option value="">เลือกประเภทผู้ใช้งาน</option>
                <option value="user">ผู้ใช้งานทั่วไป</option>
                <option value="seller">ผู้ขาย</option>
                <option value="admin">ผู้ดูแลระบบ</option>
            </select>

            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
            <input type="password" name="password" placeholder="รหัสผ่าน" required>

            <button type="submit">เข้าสู่ระบบ</button>
        </form>

        <p>
            ยังไม่มีบัญชี?
            <a href="/view/auth/register_user.php">สมัครสมาชิก</a>
        </p>
    </div>
</section>
