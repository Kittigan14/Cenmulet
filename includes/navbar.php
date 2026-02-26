<?php

if (!isset($user) || !isset($cart_count)) {
    die("Error: Required variables not set for navbar");
}

$active_page = $active_page ?? 'home';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sriracha&display=swap');

:root {
    --primary:        #FF8C00;
    --primary-dark:   #FF5F00;
    --primary-light:  #FFC300;
    --primary-glow:   #ff8c009b;

    --accent-blue:       #F5824A;
    --accent-blue-dark:  #f77433;
    --accent-blue-light: #f5834ad0;
    --accent-blue-bg:    #d97706;
}

.navbar {
    width: 100%;
    height: 100px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 1px 8px rgba(0,0,0,0.06);
    position: sticky;
    top: 0;
    z-index: 200;
}

.navbar-container {
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 32px;
    height: 100%;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
}

/* ── Left: Nav Links ── */
.nav-left {
    display: flex;
    align-items: center;
}

.nav-left ul {
    list-style: none;
    display: flex;
    gap: 4px;
    margin: 0;
    padding: 0;
}

.nav-left a {
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    color: #6b7280;
    font-family: "Kanit", sans-serif;
    font-size: 14px;
    font-weight: 500;
    padding: 7px 14px;
    border-radius: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.nav-left a:hover {
    color: var(--primary);
    background: rgba(255,140,0,0.08);
}

.nav-left a.active {
    color: var(--primary-dark);
    background: rgba(255,140,0,0.18);
    font-weight: 600;
}

.nav-left a i {
    font-size: 13px;
}

/* ── Center: Logo ── */
.nav-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    gap: 0;
    line-height: 1;
    margin: 0 320px 0 320px;
}

.nav-logo-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-logo img {
    height: 40px;
    width: 40px;
    object-fit: contain;
}

.nav-logo-name {
    font-family: "Sriracha", cursive;
    font-size: 22px;
    color: #111827;
    letter-spacing: 0.3px;
}

.nav-logo-sub {
    font-family: "Sriracha", cursive;
    font-size: 11px;
    color: #9ca3af;
    text-align: center;
    margin-top: 1px;
}

/* ── Right: Cart + User ── */
.nav-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

/* Cart Button */
.nav-cart {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    color: #374151;
    text-decoration: none;
    transition: all 0.2s ease;
    background: transparent;
}

.nav-cart:hover {
    background: #fff7ed;
    color: var(--primary);
}

.nav-cart i {
    font-size: 18px;
}

.cart-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background: #ef4444;
    color: #fff;
    font-family: "Kanit", sans-serif;
    font-size: 10px;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    border: 2px solid #fff;
}

/* User Dropdown */
.user-menu {
    position: relative;
}

.user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px 7px 10px;
    background: #f9fafb;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: "Kanit", sans-serif;
    user-select: none;
}

.user-btn:hover {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-glow);
}

.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255,140,0,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-avatar i {
    font-size: 13px;
    color: var(--primary-dark);
}

.user-name {
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-chevron {
    font-size: 10px;
    color: #9ca3af;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.user-menu:hover .user-chevron {
    transform: rotate(180deg);
}

/* Dropdown */
.dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.10);
    min-width: 190px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px);
    transition: all 0.2s ease;
    overflow: hidden;
}

.user-menu:hover .dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-header {
    padding: 12px 16px 10px;
    border-bottom: 1px solid #f3f4f6;
}

.dropdown-header .dh-name {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    font-family: "Kanit", sans-serif;
}

.dropdown-header .dh-role {
    font-size: 11px;
    color: var(--primary);
    font-family: "Kanit", sans-serif;
    margin-top: 1px;
}

.dropdown-body a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-family: "Kanit", sans-serif;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.15s;
}

.dropdown-body a:hover {
    background: #f9fafb;
}

.dropdown-body a .d-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dropdown-body a .d-icon i {
    font-size: 12px;
    color: #6b7280;
}

.dropdown-body a:hover .d-icon {
    background: rgba(255,140,0,0.18);
}

.dropdown-body a:hover .d-icon i {
    color: var(--primary-dark);
}

.dropdown-divider {
    height: 1px;
    background: #f3f4f6;
    margin: 4px 0;
}

.dropdown-body a.logout-link .d-icon {
    background: #fee2e2;
}

.dropdown-body a.logout-link .d-icon i {
    color: #ef4444;
}

.dropdown-body a.logout-link {
    color: #ef4444;
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .navbar { height: auto; }

    .navbar-container {
        grid-template-columns: 1fr auto;
        grid-template-rows: auto auto;
        padding: 12px 16px;
        gap: 10px;
    }

    .nav-logo {
        grid-column: 1;
        grid-row: 1;
        align-items: flex-start;
    }

    .nav-right {
        grid-column: 2;
        grid-row: 1;
    }

    .nav-left {
        grid-column: 1 / -1;
        grid-row: 2;
    }

    .nav-left ul { gap: 2px; }

    .nav-left a {
        font-size: 13px;
        padding: 6px 10px;
    }

    .user-name { display: none; }
}
</style>

<nav class="navbar">
    <div class="navbar-container">

        <!-- Left: Nav Links -->
        <div class="nav-left">
            <ul>
                <li>
                    <a href="/views/user/home.php"
                       class="<?php echo $active_page === 'home' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-house"></i>
                        หน้าแรก
                    </a>
                </li>
                <li>
                    <a href="/views/user/orders.php"
                       class="<?php echo $active_page === 'orders' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-receipt"></i>
                        คำสั่งซื้อของฉัน
                    </a>
                </li>
            </ul>
        </div>

        <!-- Center: Logo -->
        <a href="/views/user/home.php" class="nav-logo">
            <div class="nav-logo-row">
                <img src="/public/images/image.png" alt="Cenmulet">
                <span class="nav-logo-name">Cenmulet</span>
            </div>
            <span class="nav-logo-sub">ตลาดพระเครื่อง</span>
        </a>

        <!-- Right: Cart + User -->
        <div class="nav-right">

            <!-- Cart -->
            <a href="/views/user/cart.php" class="nav-cart" title="ตะกร้าสินค้า">
                <i class="fa-solid fa-cart-shopping"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count > 99 ? '99+' : $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <!-- User Dropdown -->
            <div class="user-menu">
                <div class="user-btn">
                    <div class="user-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                    <i class="fa-solid fa-chevron-down user-chevron"></i>
                </div>

                <div class="dropdown">
                    <div class="dropdown-header">
                        <div class="dh-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                        <div class="dh-role">สมาชิก</div>
                    </div>
                    <div class="dropdown-body">
                        <a href="/views/user/profile.php">
                            <div class="d-icon"><i class="fa-solid fa-user-circle"></i></div>
                            <span>ข้อมูลส่วนตัว</span>
                        </a>
                        <a href="/views/user/orders.php">
                            <div class="d-icon"><i class="fa-solid fa-shopping-bag"></i></div>
                            <span>คำสั่งซื้อของฉัน</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/auth/logout.php" class="logout-link">
                            <div class="d-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
                            <span>ออกจากระบบ</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</nav>