<?php

if (!isset($user) || !isset($cart_count)) {
    die("Error: Required variables not set for navbar");
}

$active_page = $active_page ?? 'home';
?>

<style>

    .navbar {
        width: 100%;
        height: 100px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .navbar-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .nav-left ul {
        list-style: none;
        display: flex;
        gap: 25px;
        margin: 0;
        padding: 0;
    }

    .nav-left a {
        text-decoration: none;
        color: #1a1a1a;
        font-size: 15px;
        transition: color 0.3s;
        font-weight: 500;
    }

    .nav-left a:hover,
    .nav-left a.active {
        color: #10b981;
    }

    .logo {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }

    .logo .content-logo {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }
    
    .logo h2 {
        font-size: 28px;
        color: #444547;
        margin: 0 0 5px 0;
        font-family: "Sriracha", cursive;
    }

    .logo p {
        font-size: 14px;
        color: #6b7280;
        margin: 0;
        font-family: "Sriracha", cursive;

    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-menu {
        position: relative;
    }

    .user-button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        background: #f3f4f6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .user-button:hover {
        background: #e5e7eb;
    }

    .user-button i {
        font-size: 18px;
        color: #1a1a1a;
    }

    .user-button span {
        font-size: 14px;
        color: #1a1a1a;
    }

    .user-button .chevron {
        font-size: 12px;
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        min-width: 200px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s;
    }

    .user-menu:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        color: #1a1a1a;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }

    .dropdown-menu a:first-child {
        border-radius: 10px 10px 0 0;
    }

    .dropdown-menu a:last-child {
        border-radius: 0 0 10px 10px;
    }

    .dropdown-menu a:hover {
        background: #f3f4f6;
    }

    .dropdown-menu a i {
        width: 20px;
        color: #10b981;
    }

    .cart-icon {
        position: relative;
        font-size: 22px;
        color: #1a1a1a;
        cursor: pointer;
        transition: all 0.3s;
    }

    .cart-icon:hover {
        color: #10b981;
    }

    .cart-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ef4444;
        color: #fff;
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: bold;
        min-width: 18px;
        text-align: center;
    }

    @media (max-width: 768px) {
        .navbar-container {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .logo {
            position: relative;
            left: 0;
            transform: none;
        }

        .nav-left ul {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
</style>

<nav class="navbar">
    <div class="navbar-container">
        <div class="nav-left">
            <ul>
                <li>
                    <a href="/views/user/home.php" class="<?php echo $active_page === 'home' ? 'active' : ''; ?>">
                        หน้าแรก
                    </a>
                </li>
                <li>
                    <a href="/views/user/orders.php" class="<?php echo $active_page === 'orders' ? 'active' : ''; ?>">
                        คำสั่งซื้อของฉัน
                    </a>
                </li>
            </ul>
        </div>

        <div class="logo">
            <div class="content-logo">
                <img src="/public/images/image.png" alt="Cenmulet Logo" width="64">
                <h2>Cenmulet</h2>
            </div>
            <p>ตลาดพระเครื่อง</p>
        </div>

        <div class="nav-right">
            <a href="/views/user/cart.php">
                <div class="cart-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </div>
            </a>

            <div class="user-menu">
                <div class="user-button">
                    <i class="fa-solid fa-user"></i>
                    <span><?php echo htmlspecialchars($user['fullname']); ?></span>
                    <i class="fa-solid fa-chevron-down chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="/views/user/profile.php">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>ข้อมูลส่วนตัว</span>
                    </a>
                    <a href="/views/user/orders.php">
                        <i class="fa-solid fa-shopping-bag"></i>
                        <span>คำสั่งซื้อของฉัน</span>
                    </a>
                    <a href="/auth/logout.php">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>