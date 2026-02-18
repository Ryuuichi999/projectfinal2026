<?php
/**
 * ฟังก์ชันบันทึก Log การดำเนินการคำร้อง
 * ใช้สำหรับแสดง Timeline ในหน้า request_detail.php
 */

if (!function_exists('logRequestAction')) {
    /**
     * บันทึก Log การดำเนินการ
     * @param mysqli $conn
     * @param int $request_id
     * @param string $action - รหัส action เช่น created, approved, rejected
     * @param string $action_label - ข้อความแสดงผล
     * @param int|null $actor_id - ID ผู้ดำเนินการ (null = ระบบ)
     * @param string|null $note - หมายเหตุเพิ่มเติม
     */
    function logRequestAction($conn, $request_id, $action, $action_label, $actor_id = null, $note = null)
    {
        $stmt = $conn->prepare("INSERT INTO request_logs (request_id, action, action_label, actor_id, note) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $request_id, $action, $action_label, $actor_id, $note);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('getRequestLogs')) {
    /**
     * ดึง Log ทั้งหมดของคำร้อง
     * @param mysqli $conn
     * @param int $request_id
     * @return array
     */
    function getRequestLogs($conn, $request_id)
    {
        $stmt = $conn->prepare(
            "SELECT rl.*, u.title_name, u.first_name, u.last_name
             FROM request_logs rl
             LEFT JOIN users u ON rl.actor_id = u.id
             WHERE rl.request_id = ?
             ORDER BY rl.created_at ASC"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        return $logs;
    }
}

if (!function_exists('getTimelineIcon')) {
    /**
     * คืน icon และสีสำหรับแต่ละ action
     */
    function getTimelineIcon($action)
    {
        $icons = [
            'created' => ['icon' => '📝', 'color' => '#6c757d'],
            'reviewing' => ['icon' => '🔍', 'color' => '#17a2b8'],
            'waiting_payment' => ['icon' => '💳', 'color' => '#ffc107'],
            'paid' => ['icon' => '✅', 'color' => '#28a745'],
            'approved' => ['icon' => '✅', 'color' => '#28a745'],
            'rejected' => ['icon' => '❌', 'color' => '#dc3545'],
            'receipt_issued' => ['icon' => '🧾', 'color' => '#007bff'],
            'permit_issued' => ['icon' => '📄', 'color' => '#6f42c1'],
            'expired' => ['icon' => '⏰', 'color' => '#6c757d'],
            'renewed' => ['icon' => '🔄', 'color' => '#20c997'],
        ];
        return $icons[$action] ?? ['icon' => '📌', 'color' => '#6c757d'];
    }
}
?>