<?php

function ensureReceiptColumnsExist($conn)
{
    $table = 'sign_requests';
    $columns = [
        'receipt_issued_by' => "VARCHAR(255) NULL",
        'receipt_no' => "VARCHAR(50) NULL",
        'receipt_date' => "DATE NULL"
    ];

    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db_name'] : null;
    if (!$dbName)
        return;

    foreach ($columns as $column => $definition) {
        // Only run check if we suspect column missing to save query time?
        // Actually, INFORMATION_SCHEMA is fast enough for dev.
        // But for production, better to wrap in try-catch or check existence once.

        $sql = "SELECT COUNT(*) AS cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $dbName, $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt = 0;
        if ($res) {
            $row = $res->fetch_assoc();
            $cnt = (int) ($row['cnt'] ?? 0);
        }

        if ($cnt === 0) {
            // Use simple query since we are in a helper
            $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

function generateNextReceiptNumber($conn)
{
    // Ensure columns exist first
    ensureReceiptColumnsExist($conn);

    // Current Thai Year (2 digits)
    $year = date('Y') + 543;
    $shortYear = substr($year, -2);

    // Pattern to search: RCPT-%/$shortYear
    // Example: RCPT-00001/67

    $sql = "SELECT receipt_no FROM sign_requests 
            WHERE receipt_no LIKE ? 
            ORDER BY id DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $likeParam = "RCPT-%/{$shortYear}";
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    $lastNo = 0;
    if ($row = $result->fetch_assoc()) {
        if (preg_match('/RCPT-(\d+)\/\d+/', $row['receipt_no'], $matches)) {
            $lastNo = (int) $matches[1];
        }
    }

    $nextNo = $lastNo + 1;
    $paddedNo = str_pad($nextNo, 5, '0', STR_PAD_LEFT);

    return "RCPT-{$paddedNo}/{$shortYear}";
}

function ensureRequestNumberColumn($conn)
{
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db_name'] : null;
    if (!$dbName) return;

    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sign_requests' AND COLUMN_NAME = 'request_no'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $cnt = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    if ($cnt === 0) {
        $conn->query("ALTER TABLE `sign_requests` ADD COLUMN `request_no` VARCHAR(30) NULL AFTER `id`");
    }
}

function generateNextRequestNumber($conn)
{
    ensureRequestNumberColumn($conn);

    // รูปแบบ: REQ-6802-00001 (ปีพ.ศ.2หลัก + เดือน2หลัก + ลำดับ5หลัก)
    $thYear = substr((string)(date('Y') + 543), -2);
    $month  = date('m');
    $prefix = "REQ-{$thYear}{$month}-";

    $sql = "SELECT request_no FROM sign_requests
            WHERE request_no LIKE ?
            ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $likeParam = $prefix . '%';
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    $lastNo = 0;
    if ($row = $result->fetch_assoc()) {
        if (preg_match('/REQ-\d{4}-(\d+)/', $row['request_no'], $matches)) {
            $lastNo = (int) $matches[1];
        }
    }

    return $prefix . str_pad($lastNo + 1, 5, '0', STR_PAD_LEFT);
}
?>