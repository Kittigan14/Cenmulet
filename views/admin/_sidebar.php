<?php
if (!isset($pending_sellers)) {
    $pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status = 'pending'")->fetchColumn();
}

$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar admin">
    <div class="sidebar-header">
        <img src="/public/images/image.png" alt="">
        <h2>Cenmulet</h2>
        <p>แดชบอร์ดผู้ดูแลระบบ</p>
    </div>
    <div class="sidebar-user">
        <h3><?php echo htmlspecialchars($admin['fullname']); ?></h3>
        <p><i class="fa-solid fa-shield-halved"></i> ผู้ดูแลระบบ</p>
    </div>
    <ul class="sidebar-menu">
        <li class="menu-sep">เมนูหลัก</li>
        <li>
            <a href="/views/admin/dashboard.php"
               class="<?php echo $current === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> แดชบอร์ด
            </a>
        </li>
        <li>
            <a href="/views/admin/users.php"
               class="<?php echo $current === 'users.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> จัดการผู้ใช้
            </a>
        </li>
        <li>
            <a href="/views/admin/sellers.php"
               class="<?php echo in_array($current, ['sellers.php','approve_sellers.php']) ? 'active' : ''; ?>">
                <i class="fa-solid fa-store"></i> จัดการผู้ขาย
                <?php if ($pending_sellers > 0): ?>
                <span style="background:#ef4444;color:#fff;border-radius:99px;padding:1px 8px;font-size:11px;margin-left:auto;font-weight:700">
                    <?php echo $pending_sellers; ?>
                </span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/views/admin/products.php"
               class="<?php echo $current === 'products.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> จัดการสินค้า
            </a>
        </li>
        <li>
            <a href="/views/admin/categories.php"
               class="<?php echo $current === 'categories.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-tags"></i> จัดการหมวดหมู่
            </a>
        </li>
        <li>
            <a href="/views/admin/orders.php"
               class="<?php echo $current === 'orders.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-cart-shopping"></i> จัดการคำสั่งซื้อ
            </a>
        </li>
        <li class="menu-sep">ระบบ</li>
        <li>
            <a href="/auth/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
            </a>
        </li>
    </ul>
</aside>