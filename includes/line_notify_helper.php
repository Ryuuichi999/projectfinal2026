<?php
/**
 * LINE Notification Helper
 * ส่ง LINE Message ผ่าน LINE Messaging API Push Message
 */
require_once __DIR__ . '/config.php';

if (!function_exists('sendLineNotification')) {
    /**
     * ส่ง LINE Push Message ให้ผู้ใช้
     * @param string $line_user_id - LINE User ID ของผู้รับ
     * @param string $message - ข้อความที่ต้องการส่ง
     * @return bool
     */
    function sendLineNotification($line_user_id, $message)
    {
        if (empty($line_user_id) || empty($message))
            return false;

        // ใช้ LINE Messaging API
        $channel_access_token = defined('LINE_CHANNEL_ACCESS_TOKEN') ? LINE_CHANNEL_ACCESS_TOKEN : '';
        if (empty($channel_access_token))
            return false;

        $url = 'https://api.line.me/v2/bot/message/push';
        $data = [
            'to' => $line_user_id,
            'messages' => [
                ['type' => 'text', 'text' => $message]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channel_access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }
}

if (!function_exists('notifyRequestStatusViaLine')) {
    /**
     * แจ้งเตือนสถานะคำร้องผ่าน LINE
     */
    function notifyRequestStatusViaLine($conn, $request_id)
    {
        $stmt = $conn->prepare(
            "SELECT r.status, r.permit_no, r.fee, u.line_user_id, u.first_name
             FROM sign_requests r
             JOIN users u ON r.user_id = u.id
             WHERE r.id = ?"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data || empty($data['line_user_id']))
            return false;

        $status_messages = [
            'pending' => "📝 คำร้อง #{$request_id} ถูกยื่นเรียบร้อยแล้ว\nรอเจ้าหน้าที่ตรวจสอบ",
            'waiting_payment' => "✅ คำร้อง #{$request_id} ได้รับการอนุมัติ!\n💳 กรุณาชำระค่าธรรมเนียม " . number_format($data['fee']) . " บาท",
            'approved' => "🎉 คำร้อง #{$request_id} ดำเนินการเสร็จสิ้น!\n📄 เลขที่ใบอนุญาต: " . ($data['permit_no'] ?? '-'),
            'rejected' => "❌ คำร้อง #{$request_id} ถูกปฏิเสธ\nกรุณาตรวจสอบรายละเอียดในระบบ",
        ];

        $message = $status_messages[$data['status']] ?? "📌 สถานะคำร้อง #{$request_id} ถูกอัปเดต";
        $message .= "\n\nเข้าดูรายละเอียด:\nhttp://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/Project2026/users/request_detail.php?id={$request_id}";

        return sendLineNotification($data['line_user_id'], $message);
    }
}
?>