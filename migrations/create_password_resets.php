<?php
/**
 * Migration: สร้างตาราง password_resets สำหรับเก็บ OTP
 */
require __DIR__ . '/../includes/db.php';

$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    citizen_id VARCHAR(20) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_citizen_otp (citizen_id, otp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo "✅ ตาราง password_resets สร้างสำเร็จ\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}
?>