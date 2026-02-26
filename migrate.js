const Database = require('better-sqlite3');
const path     = require('path');
const fs       = require('fs');

const DB_PATH = path.resolve(__dirname, './database/cenmulet.sqlite');

if (!fs.existsSync(DB_PATH)) {
    console.error(`\n❌  ไม่พบไฟล์ database: ${DB_PATH}`);
    console.error('   กรุณาแก้ไข DB_PATH ในไฟล์ migrate.js ให้ตรงกับ path ของคุณ\n');
    process.exit(1);
}

const db = new Database(DB_PATH);

function columnExists(table, column) {
    const info = db.pragma(`table_info(${table})`);
    return info.some(col => col.name === column);
}

const migrations = [
    {
        table:  'sellers',
        column: 'status',
        sql:    `ALTER TABLE sellers ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'approved'`,
        after:  () => {
            const updated = db.prepare(`UPDATE sellers SET status = 'approved'`).run();
            console.log(`   ✔  อัปเดต seller เดิม ${updated.changes} ราย → status = 'approved'`);
        }
    },
    {
        table:  'sellers',
        column: 'reject_reason',
        sql:    `ALTER TABLE sellers ADD COLUMN reject_reason TEXT`
    },
    {
        table:  'sellers',
        column: 'reviewed_at',
        sql:    `ALTER TABLE sellers ADD COLUMN reviewed_at DATETIME`
    },
    {
        table:  'payments',
        column: 'confirmed_at',
        sql:    `ALTER TABLE payments ADD COLUMN confirmed_at DATETIME`
    }
];

console.log('\n🚀  Cenmulet Migration เริ่มต้น...');
console.log(`📂  Database: ${DB_PATH}\n`);

let added = 0;
let skipped = 0;

for (const m of migrations) {
    const label = `${m.table}.${m.column}`;

    if (columnExists(m.table, m.column)) {
        console.log(`   ⏭  ${label} — มีอยู่แล้ว (ข้าม)`);
        skipped++;
        continue;
    }

    try {
        db.prepare(m.sql).run();
        console.log(`   ✅  ${label} — เพิ่มสำเร็จ`);
        if (m.after) m.after();
        added++;
    } catch (err) {
        console.error(`   ❌  ${label} — เกิดข้อผิดพลาด: ${err.message}`);
        process.exit(1);
    }
}

console.log('\n─────────────────────────────────────────');
console.log(`✅  เพิ่ม column ใหม่   : ${added} รายการ`);
console.log(`⏭  ข้ามเพราะมีอยู่แล้ว : ${skipped} รายการ`);
console.log('─────────────────────────────────────────');

console.log('\n📋  โครงสร้าง sellers:');
db.pragma('table_info(sellers)').forEach(col => {
    const isNew = ['status','reject_reason','reviewed_at'].includes(col.name);
    console.log(`   ${isNew ? '🆕' : '  '} ${col.name.padEnd(18)} ${col.type}`);
});

console.log('\n📋  โครงสร้าง payments:');
db.pragma('table_info(payments)').forEach(col => {
    const isNew = ['confirmed_at'].includes(col.name);
    console.log(`   ${isNew ? '🆕' : '  '} ${col.name.padEnd(18)} ${col.type}`);
});

console.log('\n✨  Migration เสร็จสมบูรณ์!\n');
db.close();