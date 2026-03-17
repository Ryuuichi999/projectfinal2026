<?php

function ensurePermitColumnsExist($conn)
{
    $table = 'sign_requests';
    $columns = [
        'permit_no' => "VARCHAR(50) NULL",
        'permit_date' => "DATE NULL",
        'permit_signer_name' => "VARCHAR(255) NULL",
        'permit_signer_position' => "VARCHAR(255) NULL"
    ];

    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbRow = $dbRes ? $dbRes->fetch_assoc() : null;
    $dbName = $dbRow ? $dbRow['db_name'] : null;
    if (!$dbName)
        return;

    foreach ($columns as $column => $definition) {
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
            $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

function generateNextPermitNumber($conn)
{
    // Ensure columns exist first
    ensurePermitColumnsExist($conn);

    // Current Thai Year (4 digits) e.g. 2569
    $year = date('Y') + 543;

    // นับจำนวนใบอนุญาตที่ออกแล้วในปีนี้ (เฉพาะที่มี permit_no ลงท้ายด้วย /2569)
    $sql = "SELECT permit_no FROM sign_requests 
            WHERE permit_no LIKE ?
            AND permit_no IS NOT NULL AND permit_no != ''
            ORDER BY CAST(SUBSTRING_INDEX(permit_no, '/', 1) AS UNSIGNED) DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $likeParam = "%/{$year}";
    $stmt->bind_param("s", $likeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    $lastNo = 0;

    if ($row = $result->fetch_assoc()) {
        $parts = explode('/', $row['permit_no']);
        if (count($parts) === 2) {
            $intVal = (int) $parts[0];
            if ($intVal > 0) {
                $lastNo = $intVal;
            }
        }
    }

    $nextNo = $lastNo + 1;

    return "{$nextNo}/{$year}";
}
?>