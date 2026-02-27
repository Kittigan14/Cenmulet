/**
 * migrate_tracking.js
 * เพิ่ม tracking_number และ shipped_at ในตาราง orders
 * รันด้วย: node migrate_tracking.js
 */

const Database = require('better-sqlite3');
const path     = require('path');
const fs       = require('fs');

const DB_PATH = path.resolve(__dirname, 'database/cenmulet.sqlite');

if (!fs.existsSync(DB_PATH)) {
    console.error(`\n❌  ไม่พบไฟล์ database: ${DB_PATH}\n`);
    process.exit(1);
}

const db = new Database(DB_PATH);

function columnExists(table, column) {
    return db.pragma(`table_info(${table})`).some(c => c.name === column);
}

const migrations = [
    { table: 'orders', column: 'tracking_number', sql: 'ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100)' },
    { table: 'orders', column: 'shipped_at',      sql: 'ALTER TABLE orders ADD COLUMN shipped_at DATETIME' },
];

console.log('\n🚀  Migration: tracking_number + shipped_at\n');

let added = 0;
for (const m of migrations) {
    if (columnExists(m.table, m.column)) {
        console.log(`   ⏭  ${m.table}.${m.column} — มีอยู่แล้ว`);
    } else {
        db.prepare(m.sql).run();
        console.log(`   ✅  ${m.table}.${m.column} — เพิ่มสำเร็จ`);
        added++;
    }
}

console.log(`\n✨  เสร็จสมบูรณ์! เพิ่มใหม่ ${added} column\n`);
db.close();