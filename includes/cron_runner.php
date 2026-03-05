<?php
/**
 * Auto Cron Runner — รัน cron jobs อัตโนมัติวันละ 1 ครั้ง
 * 
 * ทำงาน: ถูก include จาก db.php ทุกครั้งที่เปิดหน้าเว็บ
 *         เช็คไฟล์ lock ว่ารันวันนี้แล้วหรือยัง
 *         ถ้ายัง → spawn background PHP process ไปรัน cron scripts
 *         ไม่กระทบความเร็วของหน้าเว็บเลย (ใช้ popen non-blocking)
 */

// ป้องกันการรันซ้ำ — เช็คไฟล์ lock ที่บันทึกวันที่ล่าสุดที่รัน
$lock_dir = __DIR__ . '/../logs/';
if (!file_exists($lock_dir)) {
    mkdir($lock_dir, 0755, true);
}

$lock_file = $lock_dir . 'cron_last_run.lock';
$today = date('Y-m-d');

// ถ้ารันวันนี้แล้ว ไม่ต้องทำอะไร
if (file_exists($lock_file) && trim(file_get_contents($lock_file)) === $today) {
    return;
}

// บันทึกว่ารันวันนี้แล้ว (ทำก่อนเพื่อป้องกัน race condition)
file_put_contents($lock_file, $today);

// Spawn background process ไปรัน cron scripts
$php = 'C:\\xampp\\php\\php.exe';
$cron_dir = __DIR__ . '/../cron/';

$scripts = [
    $cron_dir . 'check_permit_expiry.php',
    $cron_dir . 'check_payment_timeout.php',
];

foreach ($scripts as $script) {
    if (!file_exists($script)) continue;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'start /B "" "' . $php . '" "' . $script . '"';
        $handle = popen($cmd, 'r');
        if ($handle) pclose($handle);
    } else {
        exec('"' . $php . '" "' . $script . '" > /dev/null 2>&1 &');
    }
}

// Log ว่า auto-run สำเร็จ
file_put_contents($lock_dir . 'cron_runner.log',
    "[" . date('Y-m-d H:i:s') . "] Auto-run cron jobs: " . count($scripts) . " scripts\n", FILE_APPEND);
