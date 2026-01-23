<?php
require_once "config/db.php";

$categories = [
    'พระสมเด็จ',
    'พระหลวงพ่อ',
    'พระเหรียญ',
    'พระกริ่ง',
    'พระผง',
    'พระบูชา',
    'พระเครื่องทั่วไป'
];

$sql = "INSERT OR IGNORE INTO categories (category_name) VALUES (:name)";
$stmt = $db->prepare($sql);

foreach ($categories as $name) {
    $stmt->execute([
        ':name' => $name
    ]);
}

echo "✅ Seed categories successfully";
