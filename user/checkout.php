<?php
session_start();
require_once __DIR__ . "/../config/db.php";

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

try {
    $stmt = $db->prepare("
        SELECT c.*, a.amulet_name, a.price, a.image, a.quantity as stock,
               s.store_name, s.pay_bank, s.pay_contax, cat.category_name
        FROM cart c
        JOIN amulets a ON c.amulet_id = a.id
        LEFT JOIN sellers s ON a.sellerId = s.id
        LEFT JOIN categories cat ON a.categoryId = cat.id
        WHERE c.user_id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (count($cart_items) === 0) {
    header("Location: /views/user/cart.php");
    exit;
}

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

$stock_error = false;
foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        $stock_error = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>ชำระเงิน - Cenmulet</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit&family=Sriracha&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Kanit", sans-serif;
            background: #f9fafb;
        }

        .navbar {
            width: 100%;
            height: 100px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
        }

        .logo h2 {
            font-family: "Sriracha", cursive;
            font-size: 28px;
            color: #444547;
            margin-bottom: 5px;
        }

        .logo p {
            font-size: 12px;
            color: #6b7280;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 30px;
        }

        .section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #10b981;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #10b981;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .order-item-image {
            width: 80px;
            height: 80px;
            background: #e5e7eb;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-item-image i {
            font-size: 30px;
            color: #9ca3af;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-name {
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .order-item-details {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .order-item-price {
            font-size: 16px;
            color: #10b981;
            font-weight: bold;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            border-bottom: 1px solid #f3f4f6;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            margin-top: 15px;
            border-top: 2px solid #e5e7eb;
            font-size: 20px;
            font-weight: bold;
        }

        .summary-total span:last-child {
            color: #10b981;
        }

        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .upload-area:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .upload-area i {
            font-size: 48px;
            color: #10b981;
            margin-bottom: 15px;
        }

        .upload-area p {
            color: #6b7280;
            margin-bottom: 10px;
        }

        .upload-preview {
            margin-top: 15px;
            display: none;
        }

        .upload-preview img {
            max-width: 100%;
            border-radius: 10px;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-submit:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .payment-info {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .payment-info h4 {
            color: #059669;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .payment-info p {
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <h2>Cenmulet</h2>
            <p>ตลาดพระเครื่อง</p>
        </div>
    </nav>

    <div class="container">
        <div class="breadcrumb">
            <a href="/views/user/home.php">หน้าแรก</a>
            <span>/</span>
            <a href="/views/user/cart.php">ตะกร้าสินค้า</a>
            <span>/</span>
            <span>ชำระเงิน</span>
        </div>

        <div class="page-header">
            <h1>ชำระเงิน</h1>
            <a href="/views/user/cart.php"
               style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;border:2px solid #e5e7eb;border-radius:10px;color:#374151;text-decoration:none;font-size:14px;font-weight:600;transition:all .2s;margin-top:10px"
               onmouseover="this.style.borderColor='#10b981';this.style.color='#10b981'"
               onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                <i class="fa-solid fa-arrow-left"></i> ย้อนกลับไปตะกร้าสินค้า
            </a>
        </div>

        <?php if ($stock_error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>มีสินค้าบางรายการที่จำนวนสินค้าไม่เพียงพอ กรุณากลับไปตรวจสอบตะกร้าสินค้า</span>
            </div>
        <?php endif; ?>

        <form action="/user/place_order.php" method="POST" enctype="multipart/form-data">
            <div class="checkout-layout">
                <div>
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-location-dot"></i>
                            ข้อมูลการจัดส่ง
                        </h2>

                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="fullname" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" name="tel" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['tel']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">ที่อยู่จัดส่ง</label>
                            <textarea name="address" class="form-control" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                    </div>

                    <div class="section" style="margin-top: 20px;">
                        <h2 class="section-title">
                            <i class="fa-solid fa-credit-card"></i>
                            แนบหลักฐานการโอนเงิน
                        </h2>

                        <?php
                        // รวบรวมข้อมูลการชำระเงินแยกตามผู้ขาย (ไม่ซ้ำ)
                        $seller_payments = [];
                        foreach ($cart_items as $item) {
                            $key = $item['store_name'];
                            if (!isset($seller_payments[$key])) {
                                $seller_payments[$key] = [
                                    'store_name' => $item['store_name'],
                                    'pay_bank'   => $item['pay_bank'] ?? '',
                                    'pay_contax' => $item['pay_contax'] ?? '',
                                ];
                            }
                        }
                        foreach ($seller_payments as $sp):
                        ?>
                        <div class="payment-info">
                            <h4><i class="fa-solid fa-building-columns"></i> ข้อมูลการชำระเงิน — <?php echo htmlspecialchars($sp['store_name']); ?></h4>
                            <?php if ($sp['pay_bank']): ?>
                            <p><strong>ธนาคาร:</strong> <?php echo htmlspecialchars($sp['pay_bank']); ?></p>
                            <?php endif; ?>
                            <p style="display:flex;align-items:center;gap:10px">
                                <span><strong>เลขที่บัญชี / พร้อมเพย์:</strong> <span class="account-number"><?php echo htmlspecialchars($sp['pay_contax'] ?: '-'); ?></span></span>
                                <?php if ($sp['pay_contax']): ?>
                                <button type="button"
                                        onclick="copyAccount(this, '<?php echo htmlspecialchars($sp['pay_contax'], ENT_QUOTES); ?>')"
                                        style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;background:#10b981;color:#fff;border:none;border-radius:6px;font-size:13px;font-family:inherit;cursor:pointer;transition:all .2s;white-space:nowrap">
                                    <i class="fa-solid fa-copy"></i> คัดลอก
                                </button>
                                <?php endif; ?>
                            </p>
                            <p><strong>ยอดที่ต้องชำระ:</strong> <span style="color: #10b981; font-weight: bold;">฿<?php echo number_format($total_price, 2); ?></span></p>
                        </div>
                        <?php endforeach; ?>

                        <!-- จำนวนเงินและเวลาที่โอน -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">จำนวนเงินที่โอน (บาท) <span style="color:#ef4444">*</span></label>
                                <input type="number" name="transfer_amount" step="0.01" min="0"
                                       class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label">เวลาที่โอน <span style="color:#ef4444">*</span></label>
                                <input type="datetime-local" name="transfer_time"
                                       class="form-control" required>
                            </div>
                        </div>

                        <div class="upload-area" onclick="document.getElementById('slip').click()">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p>คลิกเพื่ออัพโหลดสลิปโอนเงิน</p>
                            <small style="color: #9ca3af;">รองรับไฟล์: JPG, PNG (ขนาดไม่เกิน 5MB)</small>
                        </div>
                        <input type="file" id="slip" name="slip" accept="image/*" style="display: none;" required onchange="previewSlip(this)">

                        <div class="upload-preview" id="slipPreview">
                            <img id="slipImage" src="" alt="Preview">
                        </div>
                    </div>
                </div>

                <div>
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fa-solid fa-file-invoice"></i>
                            สรุปคำสั่งซื้อ
                        </h2>

                        <div style="margin-bottom: 20px;">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <?php if ($item['image']): ?>
                                            <img src="/uploads/amulets/<?php echo htmlspecialchars($item['image']); ?>" alt="">
                                        <?php else: ?>
                                            <i class="fa-solid fa-image"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-item-info">
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['amulet_name']); ?></div>
                                        <div class="order-item-details">จำนวน: <?php echo $item['quantity']; ?> ชิ้น</div>
                                        <div class="order-item-price">฿<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-row">
                            <span>จำนวนสินค้า</span>
                            <span><?php echo count($cart_items); ?> รายการ</span>
                        </div>

                        <div class="summary-row">
                            <span>ค่าจัดส่ง</span>
                            <span style="color: #10b981;">ฟรี</span>
                        </div>

                        <div class="summary-total">
                            <span>ยอดรวมทั้งหมด</span>
                            <span>฿<?php echo number_format($total_price, 2); ?></span>
                        </div>

                        <button type="submit" class="btn-submit" <?php echo $stock_error ? 'disabled' : ''; ?>>
                            <i class="fa-solid fa-check-circle"></i>
                            ยืนยันคำสั่งซื้อ
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function copyAccount(btn, text) {
            navigator.clipboard.writeText(text).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> คัดลอกแล้ว';
                btn.style.background = '#059669';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.background = '#10b981';
                }, 2000);
            });
        }

        function previewSlip(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('slipImage').src = e.target.result;
                    document.getElementById('slipPreview').style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>