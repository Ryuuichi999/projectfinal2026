<?php
/**
 * Migration: สร้างตาราง audit_logs + feedback
 */
require __DIR__ . '/../includes/db.php';

// ─── Audit Logs ───
$sql1 = "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'เช่น login, approve, reject, delete_user',
    target_table VARCHAR(50) NULL,
    target_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql1)) {
    echo "✅ ตาราง audit_logs สร้างสำเร็จ\n";
} else {
    echo "❌ Error audit_logs: " . $conn->error . "\n";
}

// ─── Feedback ───
$sql2 = "CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT NULL,
    rating TINYINT NOT NULL COMMENT '1-5 ดาว',
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_request_id (request_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql2)) {
    echo "✅ ตาราง feedback สร้างสำเร็จ\n";
} else {
    echo "❌ Error feedback: " . $conn->error . "\n";
}
?>