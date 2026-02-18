<?php
/**
 * ไฟล์ตั้งค่ากลาง (Config) — เก็บ credentials และค่าคงที่ทั้งหมดไว้ที่เดียว
 * เพื่อความปลอดภัยและง่ายต่อการดูแลรักษา
 */

// ─── Database ───
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'project');

// ─── MapTiler API ───
define('MAPTILER_API_KEY', 'gVaBedISR95MOrxn6IIp');

// ─── Gmail SMTP (สำหรับ SMTPMailer) ───
define('SMTP_USER', 'riwlove1230@gmail.com');
define('SMTP_PASS', 'wzmiidvidhsbkqcu'); // App Password

// ─── LINE Login ───
define('LINE_LOGIN_CHANNEL_ID', '2008891589');
define('LINE_LOGIN_CHANNEL_SECRET', '18d3225d0acdbfb87a3671037ea27d90');
define('LINE_LOGIN_CALLBACK_URL', 'http://localhost/Project2026/callback_line.php');

// ─── Thunder Slip API ───
define('THUNDER_API_TOKEN', '1a4e92a3-11d0-400e-9079-aa374779682a');

// ─── Base URL ───
define('BASE_URL', '/Project2026');
