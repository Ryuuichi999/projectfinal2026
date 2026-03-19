<?php
/**
 * Auto Cron Runner — รัน cron jobs อัตโนมัติทุกชั่วโมง
 * 
 * ทำงาน: ถูก include จาก db.php ทุกครั้งที่เปิดหน้าเว็บ
 *         เช็คไฟล์ lock ว่ารันชั่วโมงนี้แล้วหรือยัง
 *         ถ้ายัง → รัน cron scripts ผ่าน include (รองรับ shared hosting ที่ disable shell_exec)
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

$cron_dir = __DIR__ . '/../cron/';
$scripts = [
    $cron_dir . 'check_permit_expiry.php',
    $cron_dir . 'check_payment_timeout.php',
];

$_is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// ลองใช้ shell_exec ก่อน (Windows/XAMPP) ถ้าไม่ได้ fallback เป็น include
$_can_exec = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))));

foreach ($scripts as $script) {
    if (!file_exists($script)) continue;
    
    if ($_can_exec) {
        $php = $_is_windows ? 'C:\\xampp\\php\\php.exe' : (PHP_BINARY ?: '/usr/bin/php');
        $output = shell_exec('"' . $php . '" "' . $script . '" 2>&1');
    } else {
        // Shared hosting: include ตรง ๆ (config.php, db.php จะ require_once ไม่ซ้ำ)
        try {
            ob_start();
            include $script;
            ob_end_clean();
        } catch (Exception $e) {
            if (ob_get_level()) ob_end_clean();
            @file_put_contents($lock_dir . 'cron_runner.log',
                "[" . date('Y-m-d H:i:s') . "] Error in {$script}: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        // คืนค่า error/exception handler เดิม (cron scripts อาจ set_error_handler ทับ)
        @restore_error_handler();
        @restore_exception_handler();
    }
}

// Log ว่า auto-run สำเร็จ
@file_put_contents($lock_dir . 'cron_runner.log',
    "[" . date('Y-m-d H:i:s') . "] Auto-run cron jobs: " . count($scripts) . " scripts (method: " . ($_can_exec ? 'shell_exec' : 'include') . ")\n", FILE_APPEND);
