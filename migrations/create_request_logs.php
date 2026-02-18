<?php
/**
 * Migration: สร้างตาราง request_logs สำหรับเก็บประวัติการดำเนินการของแต่ละคำร้อง
 * ใช้สำหรับ Timeline UI ในหน้า request_detail.php
 */
require __DIR__ . '/../includes/db.php';

$sql = "CREATE TABLE IF NOT EXISTS request_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'เช่น created, reviewing, approved, rejected, waiting_payment, paid, receipt_issued',
    action_label VARCHAR(255) NOT NULL COMMENT 'ข้อความแสดงผล เช่น ยื่นคำร้องใหม่',
    actor_id INT NULL COMMENT 'ผู้ดำเนินการ (NULL = ระบบ)',
    note TEXT NULL COMMENT 'หมายเหตุเพิ่มเติม',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES sign_requests(id) ON DELETE CASCADE,
    INDEX idx_request_id (request_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "✅ ตาราง request_logs สร้างสำเร็จ\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

// เพิ่ม Log ย้อนหลังสำหรับคำร้องที่มีอยู่แล้ว (Backfill)
$backfill = "INSERT INTO request_logs (request_id, action, action_label, actor_id, note, created_at)
    SELECT id, 'created', 'ยื่นคำร้องใหม่', user_id, CONCAT('ประเภท: ', sign_type), created_at
    FROM sign_requests
    WHERE id NOT IN (SELECT DISTINCT request_id FROM request_logs WHERE action = 'created')";

if ($conn->query($backfill)) {
    echo "✅ Backfill logs สำหรับคำร้องที่มีอยู่แล้วสำเร็จ (" . $conn->affected_rows . " รายการ)\n";
}

// เพิ่ม Log สำหรับคำร้องที่ approved แล้ว
$backfill_approved = "INSERT INTO request_logs (request_id, action, action_label, actor_id, note, created_at)
    SELECT id, 'approved', 'อนุมัติคำร้อง', approved_by, CONCAT('เลขที่ใบอนุญาต: ', COALESCE(permit_no, '-')), 
        COALESCE(permit_date, created_at)
    FROM sign_requests
    WHERE status = 'approved' AND id NOT IN (SELECT DISTINCT request_id FROM request_logs WHERE action = 'approved')";

if ($conn->query($backfill_approved)) {
    echo "✅ Backfill approved logs สำเร็จ (" . $conn->affected_rows . " รายการ)\n";
}

// เพิ่ม Log สำหรับคำร้องที่มีใบเสร็จแล้ว
$backfill_receipt = "INSERT INTO request_logs (request_id, action, action_label, actor_id, note, created_at)
    SELECT id, 'receipt_issued', 'ออกใบเสร็จ', approved_by, CONCAT('เลขที่: ', COALESCE(receipt_no, '-')), 
        COALESCE(receipt_date, created_at)
    FROM sign_requests
    WHERE receipt_no IS NOT NULL AND id NOT IN (SELECT DISTINCT request_id FROM request_logs WHERE action = 'receipt_issued')";

if ($conn->query($backfill_receipt)) {
    echo "✅ Backfill receipt logs สำเร็จ (" . $conn->affected_rows . " รายการ)\n";
}

echo "\n🎉 Migration เสร็จสมบูรณ์!";
?>