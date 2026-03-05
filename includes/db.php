<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง");
}

// Auto-run cron jobs วันละ 1 ครั้ง (ไม่บล็อกหน้าเว็บ)
if (php_sapi_name() !== 'cli') {
    @include_once __DIR__ . '/cron_runner.php';
}
