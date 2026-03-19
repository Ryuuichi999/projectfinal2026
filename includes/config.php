<?php
// Set Timezone
date_default_timezone_set('Asia/Bangkok');
/**
 * ไฟล์ตั้งค่ากลาง (Config) — เก็บ credentials และค่าคงที่ทั้งหมดไว้ที่เดียว
 * เพื่อความปลอดภัยและง่ายต่อการดูแลรักษา
 */

// ─── ตรวจจับ Environment ───
// PHP_OS_FAMILY ไม่มีใน PHP < 7.2 → ใช้ PHP_OS แทน
$_is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$_is_production = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'surveygis39.com') !== false)
    || (!$_is_windows); // CLI บน Linux = production

// ─── Database ───
if ($_is_production) {
    // Production server
    define('DB_HOST', 'localhost');
    define('DB_USER', 'surveygi_student');
    define('DB_PASS', 'wePVPGPNERucgMxENx8c');
    define('DB_NAME', 'surveygi_student');
} else {
    // Localhost / XAMPP
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'project');
}

// ─── MapTiler API ───
define('MAPTILER_API_KEY', 'gVaBedISR95MOrxn6IIp');
// ─── Gmail SMTP (สำหรับ SMTPMailer) ───
define('SMTP_USER', 'riwlove1230@gmail.com');
define('SMTP_PASS', 'wzmiidvidhsbkqcu'); // App Password


// ─── Thunder Slip API ───
define('THUNDER_API_TOKEN', '1a4e92a3-11d0-400e-9079-aa374779682a');

// ─── Base URL ───
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) {
    define('BASE_URL', ''); // ngrok ใช้ root path
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
} elseif ($_is_production) {
    define('BASE_URL', '/coop68/653380070-1'); // production server
    define('SITE_URL', 'https://www.surveygis39.com/coop68/653380070-1');
} else {
    define('BASE_URL', '/Project2026'); // localhost/XAMPP
    define('SITE_URL', 'http://localhost/Project2026');
}
