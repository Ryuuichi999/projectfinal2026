<?php
/**
 * Audit Log Helper
 * บันทึกประวัติการใช้งานระบบ
 */

if (!function_exists('logAudit')) {
    /**
     * บันทึก Audit Log
     * @param mysqli $conn
     * @param string $action - เช่น login, approve, reject, delete_user
     * @param string|null $target_table - ตารางเป้าหมาย
     * @param int|null $target_id - ID ของเป้าหมาย
     * @param string|null $details - รายละเอียดเพิ่มเติม
     */
    function logAudit($conn, $action, $target_table = null, $target_id = null, $details = null)
    {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $conn->prepare(
            "INSERT INTO audit_logs (user_id, action, target_table, target_id, details, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ississ", $user_id, $action, $target_table, $target_id, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}
?>