<?php
/**
 * ฟังก์ชันส่งอีเมลแจ้งเตือนสถานะคำร้อง
 * ใช้ร่วมกับไฟล์ employee/ เมื่อมีการเปลี่ยนสถานะ
 */

if (!function_exists('get_status_label')) {
    function get_status_label($status)
    {
        switch ($status) {
            case 'pending':
                return 'รอพิจารณา';
            case 'reviewing':
                return 'กำลังพิจารณา';
            case 'need_documents':
                return 'ขอเอกสารเพิ่มเติม';
            case 'waiting_payment':
                return 'รอชำระเงิน';
            case 'waiting_receipt':
                return 'รอออกใบเสร็จ';
            case 'approved':
                return 'อนุมัติเรียบร้อย';
            case 'rejected':
                return 'ไม่ผ่านการพิจารณา';
            default:
                return $status;
        }
    }
}

if (!function_exists('send_status_notification')) {
    function send_status_notification($request_id, $conn)
    {
        // 1. ดึงข้อมูลคำขอและอีเมล
        $sql = "SELECT r.status, r.email, r.sign_type, r.applicant_name 
                FROM sign_requests r 
                WHERE r.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();

        if (!$request || empty($request['email'])) {
            return false;
        }

        $to = $request['email'];
        $subject = "=?UTF-8?B?" . base64_encode("อัปเดตสถานะคำขอติดตั้งป้าย (ID: #{$request_id})") . "?=";
        $status_text = get_status_label($request['status']);

        $message = "เรียนคุณ {$request['applicant_name']},\n\n";
        $message .= "คำขอติดตั้งป้ายประเภท: {$request['sign_type']} (ID: #{$request_id})\n";
        $message .= "มีการอัปเดตสถานะเป็น: {$status_text}\n\n";
        $message .= "กรุณาตรวจสอบรายละเอียดเพิ่มเติมในระบบ:\n";
        $message .= "http://localhost/Project2026/users/my_request.php\n\n";
        $message .= "ขอบคุณที่ใช้บริการ\nเทศบาลเมืองศิลา";

        $headers = "From: noreply@sila.go.th\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // 2. ส่งอีเมล
        $mail_sent = @mail($to, $subject, $message, $headers);

        // 3. บันทึก Log
        $log_dir = __DIR__ . "/../logs/";
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_content = "[" . date('Y-m-d H:i:s') . "] ID: #{$request_id}, "
            . "Status: {$request['status']}, "
            . "Email: {$to}, "
            . "Sent: " . ($mail_sent ? "Yes" : "No") . "\n";
        file_put_contents($log_dir . "email_log.txt", $log_content, FILE_APPEND);

        return $mail_sent;
    }
}
