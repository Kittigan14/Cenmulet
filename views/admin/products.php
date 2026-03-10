<?php
session_start();
require_once __DIR__ . "/../../config/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /views/auth/login.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT id, fullname FROM admins WHERE id = :id");
$stmt->execute([':id' => $admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$search   = trim($_GET['search']   ?? '');
$cat_id   = (int)($_GET['cat']     ?? 0);
$seller_f = (int)($_GET['seller']  ?? 0);

$conditions = [];
$params     = [];
if ($search)   { $conditions[] = "a.amulet_name LIKE :q"; $params[':q'] = "%$search%"; }
if ($cat_id)   { $conditions[] = "a.categoryId = :cat";   $params[':cat'] = $cat_id; }
if ($seller_f) { $conditions[] = "a.sellerId = :sel";      $params[':sel'] = $seller_f; }
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$stmt = $db->prepare("
    SELECT a.*, c.category_name, s.store_name
    FROM amulets a
    LEFT JOIN categories c ON a.categoryId = c.id
    LEFT JOIN sellers s ON a.sellerId = s.id
    $where
    ORDER BY a.id DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
$sellers_list = $db->query("SELECT id, store_name FROM sellers WHERE status='approved' ORDER BY store_name")->fetchAll(PDO::FETCH_ASSOC);

$total_products = $db->query("SELECT COUNT(*) FROM amulets")->fetchColumn();
$in_stock       = $db->query("SELECT COUNT(*) FROM amulets WHERE quantity > 0")->fetchColumn();
$out_stock      = $total_products - $in_stock;
$pending_sellers = $db->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>จัดการสินค้า - Cenmulet Admin</title>
    <style>
        #prodModal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:998;align-items:center;justify-content:center; }
        .prod-detail-box { background:#fff;border-radius:16px;padding:28px;max-width:520px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2); }
        tr.clickable-row { cursor:pointer; }
        tr.clickable-row:hover td { background:#f5f3ff !important; }
    </style>
</head>
<body class="admin">
<div class="dashboard-container">
<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="main-content">
    <div class="top-bar">
        <h1><i class="fa-solid fa-box"></i> จัดการสินค้า</h1>
        <span class="badge badge-info" style="font-size:14px;padding:8px 16px">
            ทั้งหมด <?php echo number_format($total_products); ?> รายการ
        </span>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <?php if ($_GET['success'] === 'hidden'): ?>
        <div class="alert alert-success"><i class="fa-solid fa-eye-slash"></i> <span>ซ่อนสินค้าเรียบร้อยแล้ว</span></div>
        <?php elseif ($_GET['success'] === 'shown'): ?>
        <div class="alert alert-success"><i class="fa-solid fa-eye"></i> <span>แสดงสินค้าเรียบร้อยแล้ว</span></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <span>เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง</span></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-mini" style="margin-bottom:20px">
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $total_products; ?></div><div class="stat-mini-label">สินค้าทั้งหมด</div></div>
            <div class="stat-mini-icon" style="background:#e0e7ff;color:#6366f1"><i class="fa-solid fa-box"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $in_stock; ?></div><div class="stat-mini-label">มีสินค้า</div></div>
            <div class="stat-mini-icon" style="background:#d1fae5;color:#10b981"><i class="fa-solid fa-check-circle"></i></div>
        </div>
        <div class="stat-mini">
            <div><div class="stat-mini-value"><?php echo $out_stock; ?></div><div class="stat-mini-label">สินค้าหมด</div></div>
            <div class="stat-mini-icon" style="background:#fef3c7;color:#f59e0b"><i class="fa-solid fa-exclamation-circle"></i></div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
               placeholder="ค้นหาชื่อสินค้า..."
               style="flex:2;min-width:180px;padding:9px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
        <select name="cat" style="flex:1;min-width:140px;padding:9px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            <option value="0">ทุกหมวดหมู่</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo $cat_id == $c['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['category_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="seller" style="flex:1;min-width:140px;padding:9px 14px;border:2px solid #e5e7eb;border-radius:8px;font-family:inherit;font-size:14px">
            <option value="0">ทุกร้านค้า</option>
            <?php foreach ($sellers_list as $sl): ?>
            <option value="<?php echo $sl['id']; ?>" <?php echo $seller_f == $sl['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sl['store_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i> ค้นหา</button>
        <?php if ($search || $cat_id || $seller_f): ?>
        <a href="/views/admin/products.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-times"></i> ล้าง</a>
        <?php endif; ?>
    </form>

    <div class="card">
        <div class="card-header">
            <h2><i class="fa-solid fa-box"></i> รายการสินค้า (<?php echo count($products); ?>)</h2>
        </div>
        <div class="table-wrapper">
        <?php if (count($products) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>รูปภาพ</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>ร้านค้า</th>
                    <th>ราคา</th>
                    <th>คงเหลือ</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr class="clickable-row" onclick="openProdDetail(<?php echo htmlspecialchars(json_encode([
                'id'       => $p['id'],
                'name'     => $p['amulet_name'],
                'source'   => $p['source'] ?? '',
                'category' => $p['category_name'] ?? '-',
                'store'    => $p['store_name'] ?? '-',
                'price'    => $p['price'],
                'qty'      => $p['quantity'],
                'image'    => $p['image'] ?? '',
                'hidden'   => !empty($p['is_hidden']),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>)">
                <td>
                    <?php if (!empty($p['image'])): ?>
                    <img src="/uploads/amulets/<?php echo htmlspecialchars($p['image']); ?>"
                         class="product-img" alt="">
                    <?php else: ?>
                    <div class="no-image"><i class="fa-solid fa-image"></i></div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($p['amulet_name']); ?></strong>
                    <?php if (!empty($p['source'])): ?>
                    <div style="font-size:12px;color:#9ca3af"><?php echo htmlspecialchars($p['source']); ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-purple"><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></span></td>
                <td>
                    <div style="font-size:13px;font-weight:500"><?php echo htmlspecialchars($p['store_name'] ?? '-'); ?></div>
                </td>
                <td><strong style="color:#10b981">฿<?php echo number_format($p['price'], 2); ?></strong></td>
                <td style="font-weight:600;color:<?php echo $p['quantity'] > 0 ? '#1a1a1a' : '#ef4444'; ?>">
                    <?php echo number_format($p['quantity']); ?>
                </td>
                <td>
                    <?php if (!empty($p['is_hidden'])): ?>
                    <span class="badge" style="background:#f3f4f6;color:#6b7280"><i class="fa-solid fa-eye-slash"></i> ซ่อนอยู่</span>
                    <?php elseif ($p['quantity'] > 0): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> มีสินค้า</span>
                    <?php else: ?>
                    <span class="badge badge-warning"><i class="fa-solid fa-circle-exclamation"></i> หมด</span>
                    <?php endif; ?>
                </td>
                <td onclick="event.stopPropagation()">
                    <?php if (!empty($p['is_hidden'])): ?>
                    <a href="/admin/toggle_product_visibility.php?id=<?php echo $p['id']; ?>"
                       class="btn-icon" title="แสดงสินค้า"
                       style="background:#d1fae5;color:#059669;border-color:#a7f3d0">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <?php else: ?>
                    <a href="/admin/toggle_product_visibility.php?id=<?php echo $p['id']; ?>"
                       class="btn-icon" title="ซ่อนสินค้า"
                       style="background:#fef3c7;color:#d97706;border-color:#fde68a">
                        <i class="fa-solid fa-eye-slash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-box-open"></i>
            <h2>ไม่พบสินค้า</h2>
            <p>ลองเปลี่ยนตัวกรองหรือคำค้นหา</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
</main>
</div>

<!-- Product Detail Modal -->
<div id="prodModal" onclick="if(event.target===this)closeProdDetail()">
    <div class="prod-detail-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-box" style="color:#6366f1"></i> ข้อมูลสินค้า
            </h3>
            <button onclick="closeProdDetail()" style="background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer">×</button>
        </div>
        <div id="prodDetailContent"></div>
    </div>
</div>

<script>
function openProdDetail(p) {
    const qtyColor = p.hidden ? '#6b7280' : (p.qty > 0 ? '#059669' : '#dc2626');
    const qtyText  = p.hidden ? 'ซ่อนอยู่' : (p.qty > 0 ? 'มีสินค้า (' + Number(p.qty).toLocaleString() + ')' : 'สินค้าหมด');
    document.getElementById('prodDetailContent').innerHTML = `
        ${p.image ? `<img src="/uploads/amulets/${p.image}" style="width:100%;max-height:220px;object-fit:contain;border-radius:10px;border:2px solid #e5e7eb;margin-bottom:16px">` : ''}
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:8px 4px;color:#9ca3af;width:40%">ชื่อสินค้า</td><td style="padding:8px 4px;font-weight:600">${p.name}</td></tr>
            <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:8px 4px;color:#9ca3af">ที่มา / แหล่งที่มา</td><td style="padding:8px 4px">${p.source || '-'}</td></tr>
            <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:8px 4px;color:#9ca3af">หมวดหมู่</td><td style="padding:8px 4px"><span style="background:#e0e7ff;color:#6366f1;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600">${p.category}</span></td></tr>
            <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:8px 4px;color:#9ca3af">ร้านค้า</td><td style="padding:8px 4px"><span style="background:#ede9fe;color:#6d28d9;padding:2px 10px;border-radius:99px;font-size:12px;font-weight:600">${p.store}</span></td></tr>
            <tr style="border-bottom:1px solid #f3f4f6"><td style="padding:8px 4px;color:#9ca3af">ราคา</td><td style="padding:8px 4px;font-weight:700;color:#10b981;font-size:16px">฿${Number(p.price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td></tr>
            <tr><td style="padding:8px 4px;color:#9ca3af">สถานะ / คงเหลือ</td><td style="padding:8px 4px;font-weight:700;color:${qtyColor}">${qtyText}</td></tr>
        </table>
    `;
    document.getElementById('prodModal').style.display = 'flex';
}
function closeProdDetail() { document.getElementById('prodModal').style.display = 'none'; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProdDetail(); });
</script>
</body>
</html>