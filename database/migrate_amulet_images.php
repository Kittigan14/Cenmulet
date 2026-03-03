<?php
/**
 * database/migrate_amulet_images.php
 * Migration: สร้าง table amulet_images สำหรับ multiple images
 */
require_once __DIR__ . "/../config/db.php";

$db->exec("
CREATE TABLE IF NOT EXISTS amulet_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amulet_id INTEGER NOT NULL,
    image TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0,
    FOREIGN KEY (amulet_id) REFERENCES amulets(id)
);
");

// ย้ายรูปจาก amulets.image ที่มีอยู่แล้วเข้า amulet_images (ถ้ายังไม่มี)
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

echo "✅ Migration สำเร็จ: สร้าง table amulet_images เรียบร้อย\n";
echo "📸 ย้ายรูปสินค้าเดิมเข้า amulet_images: $count รายการ\n";
?>

