<?php
/**
 * Auto Cron Runner — รัน cron jobs อัตโนมัติทุกชั่วโมง
 * 
 * ทำงาน: ถูก include จาก db.php ทุกครั้งที่เปิดหน้าเว็บ
 *         เช็คไฟล์ lock ว่ารันชั่วโมงนี้แล้วหรือยัง
 *         ถ้ายัง → รัน cron scripts แบบ synchronous (shell_exec)
 *         รองรับ deadline ชำระเงิน 24 ชม. และตรวจใบอนุญาตหมดอายุ
 */

// ป้องกันการรันซ้ำ — เช็คไฟล์ lock ที่บันทึกชั่วโมงล่าสุดที่รัน
$lock_dir = __DIR__ . '/../logs/';
if (!file_exists($lock_dir)) {
    mkdir($lock_dir, 0755, true);
}

$lock_file = $lock_dir . 'cron_last_run.lock';
$current_hour = date('Y-m-d H'); // ล็อคระดับชั่วโมง (เช่น 2026-03-17 16)

// ถ้ารันชั่วโมงนี้แล้ว ไม่ต้องทำอะไร
if (file_exists($lock_file) && trim(file_get_contents($lock_file)) === $current_hour) {
    return;
}

// บันทึกว่ารันชั่วโมงนี้แล้ว (ทำก่อนเพื่อป้องกัน race condition)
file_put_contents($lock_file, $current_hour);

// Spawn background process ไปรัน cron scripts
$php = 'C:\\xampp\\php\\php.exe';
$cron_dir = __DIR__ . '/../cron/';

$scripts = [
    $cron_dir . 'check_permit_expiry.php',
    $cron_dir . 'check_payment_timeout.php',
];

foreach ($scripts as $script) {
    if (!file_exists($script)) continue;
    
    // รัน cron scripts synchronously ใน isolated scope (ป้องกันตัวแปร/ฟังก์ชันชนกัน)
    // ใช้ shell_exec เรียก PHP แยก process เพื่อให้แต่ละ script มี scope อิสระ
    $output = shell_exec('"' . $php . '" "' . $script . '" 2>&1');
}

// Log ว่า auto-run สำเร็จ
file_put_contents($lock_dir . 'cron_runner.log',
    "[" . date('Y-m-d H:i:s') . "] Auto-run cron jobs: " . count($scripts) . " scripts\n", FILE_APPEND);
