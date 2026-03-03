<?php
require_once __DIR__ . "/../config/db.php";

/* ---------------- USERS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT,
    username TEXT UNIQUE,
    password TEXT,
    tel TEXT,
    address TEXT,
    image TEXT,
    id_per TEXT
);
");

/* ---------------- ADMINS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT,
    username TEXT UNIQUE,
    password TEXT,
    tel TEXT
);
");

/* ---------------- SELLERS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS sellers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT,
    store_name TEXT,
    username TEXT UNIQUE,
    password TEXT,
    address TEXT,
    img_store TEXT,
    tel TEXT,
    pay_contax TEXT,
    id_per TEXT,
    img_per TEXT,

    status TEXT DEFAULT 'pending',
    reject_reason TEXT,
    reviewed_at DATETIME
);
");

/* ---------------- CATEGORIES ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_name TEXT
);
");

/* ---------------- AMULETS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS amulets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amulet_name TEXT,
    source TEXT,
    quantity INTEGER,
    price REAL,
    image TEXT,
    sellerId INTEGER,
    categoryId INTEGER,
    FOREIGN KEY (sellerId) REFERENCES sellers(id),
    FOREIGN KEY (categoryId) REFERENCES categories(id)
);
");

/* ---------------- AMULETS IMAGES ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS amulet_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amulet_id INTEGER NOT NULL,
    image TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    FOREIGN KEY (amulet_id) REFERENCES amulets(id)
);
");

$existing = $db->query("SELECT id, image FROM amulets WHERE image IS NOT NULL AND image != ''")->fetchAll(PDO::FETCH_ASSOC);
$stmt = $db->prepare("
    INSERT OR IGNORE INTO amulet_images (amulet_id, image, sort_order)
    SELECT :amulet_id, :image, 0
    WHERE NOT EXISTS (
        SELECT 1 FROM amulet_images WHERE amulet_id = :amulet_id AND image = :image
    )
");
$count = 0;
foreach ($existing as $row) {
    $stmt->execute([':amulet_id' => $row['id'], ':image' => $row['image']]);
    if ($stmt->rowCount()) $count++;
}

/* ---------------- ORDERS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    total_price REAL,
    status TEXT DEFAULT 'pending',
    tracking_number TEXT,
    shipped_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
");

/* ---------------- ORDER ITEMS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER,
    amulet_id INTEGER,
    price REAL,
    quantity INTEGER,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (amulet_id) REFERENCES amulets(id)
);
");

/* ---------------- CART ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS cart (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    amulet_id INTEGER,
    quantity INTEGER DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (amulet_id) REFERENCES amulets(id)
);
");

/* ---------------- PAYMENTS ---------------- */
$db->exec("
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER,
    slip_image TEXT,
    status TEXT DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
");

echo "✅ Database tables created successfully";
