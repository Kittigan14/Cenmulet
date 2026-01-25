<?php
require_once "config/db.php";

$admin = [
    'Admin User',
    'admin',
    password_hash('admin123', PASSWORD_DEFAULT),
    '0123456789'
];

$sql = "INSERT OR IGNORE INTO admins (fullname, username, password, tel) VALUES (:name, :username, :password, :tel)";
$stmt = $db->prepare($sql);

$stmt->execute([
    ':name' => $admin[0],
    ':username' => $admin[1],
    ':password' => $admin[2],
    ':tel' => $admin[3]
]);

echo "✅ Seed admins successfully";
