<?php
require_once "config/db.php";

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
    img_per TEXT
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

/* ---------------- ORDERS ---------------- */
$db->exec("
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    total_price REAL,
    status TEXT DEFAULT 'pending',
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
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER,
    slip_image TEXT,
    status TEXT DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
");

echo "✅ Database tables created successfully";
