<?php
function ensureSettingsTable($conn)
{
    // Determine database name to check table existence
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db_name'] : null;

    if (!$dbName)
        return;

    $sqlCheck = "SELECT COUNT(*) AS cnt 
                 FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'system_settings'";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $cnt = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    if ($cnt === 0) {
        $sql = "CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($sql);

        // Insert defaults
        $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES 
            ('receipt_signer_name', 'ระบบอัตโนมัติ'),
            ('receipt_signer_position', 'เจ้าพนักงานธุรการ'),
            ('receipt_signature_path', 'image/ลายเซ็น2.png')
        ");
    }
}

function getSetting($conn, $key, $default = '')
{
    // Ensure table exists first (lightweight check or assume exists if called often)
    // For now, let's assume it exists or check once in header. 
    // But to be safe, we can try-catch or just query.

    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function updateSetting($conn, $key, $value)
{
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
    } else {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
    }
    return $stmt->execute();
}
?>