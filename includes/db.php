<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "project");
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// MapTiler API Key (สมัครได้ที่ maptiler.com)
define('MAPTILER_API_KEY', 'gVaBedISR95MOrxn6IIp');
