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

if (!function_exists('get_status_color')) {
    function get_status_color($status)
    {
        switch ($status) {
            case 'pending':
                return '#ffc107'; // Warning Yellow
            case 'reviewing':
                return '#0d6efd'; // Primary Blue
            case 'need_documents':
                return '#17a2b8'; // Info Cyan
            case 'waiting_payment':
                return '#fd7e14'; // Orange
            case 'waiting_receipt':
                return '#6610f2'; // Purple
            case 'approved':
                return '#198754'; // Success Green
            case 'rejected':
                return '#dc3545'; // Danger Red
            default:
                return '#6c757d'; // Grey
        }
    }
}

if (!function_exists('send_status_notification')) {
    function send_status_notification($request_id, $conn)
    {
        // 1. ดึงข้อมูลคำขอและอีเมล
        $sql = "SELECT r.status, r.email, r.sign_type, r.applicant_name, r.fee 
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
        // Subject ภาษาไทย (Plain Text)
        $plain_subject = "[เทศบาลเมืองศิลา] แจ้งสถานะคำร้องขอติดตั้งป้าย (รหัส: #{$request_id})";

        $status_text = get_status_label($request['status']);
        $status_color = get_status_color($request['status']);

        // สร้างเนื้อหา HTML
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
                .header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .status-badge { 
                    display: inline-block; 
                    padding: 8px 15px; 
                    background-color: {$status_color}; 
                    color: white; 
                    border-radius: 20px; 
                    font-weight: bold;
                    margin: 10px 0;
                }
                .details-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .details-table th, .details-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
                .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white !important; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>แจ้งสถานะคำร้อง</h2>
                </div>
                <div class='content'>
                    <p>เรียน คุณ {$request['applicant_name']},</p>
                    <p>คำร้องขอติดตั้งป้ายของท่านได้รับการปรับปรุงสถานะเรียบร้อยแล้ว โดยมีรายละเอียดดังนี้:</p>
                    
                    <div style='text-align:center;'>
                        <span class='status-badge'>{$status_text}</span>
                    </div>

                    <table class='details-table'>
                        <tr>
                            <th width='40%'>เลขที่คำร้อง:</th>
                            <td>#{$request_id}</td>
                        </tr>
                        <tr>
                            <th>ประเภทป้าย:</th>
                            <td>{$request['sign_type']}</td>
                        </tr>
                        <tr>
                            <th>ค่าธรรมเนียม:</th>
                            <td>" . ($request['fee'] > 0 ? number_format($request['fee'], 2) . ' บาท' : '-') . "</td>
                        </tr>
                         <tr>
                            <th>วันที่อัปเดต:</th>
                            <td>" . date('d/m/Y H:i') . "</td>
                        </tr>
                    </table>

                    <p style='text-align:center; margin-top: 25px;'>
                        <a href='http://localhost/Project2026/users/my_request.php' class='btn'>ตรวจสอบรายละเอียด</a>
                    </p>
                    
                    <p style='margin-top: 20px; font-size: 14px; color: #666;'>
                        หากมีข้อสงสัย กรุณาติดต่อ เทศบาลเมืองศิลา โทร 043-xxx-xxx<br>
                        ในวันและเวลาราชการ
                    </p>
                </div>
                <div class='footer'>
                    อีเมลฉบับนี้เป็นการแจ้งเตือนอัตโนมัติ กรุณาอย่าตอบกลับ<br>
                    &copy; " . date('Y') . " เทศบาลเมืองศิลา
                </div>
            </div>
        </body>
        </html>
        ";

        // 2. ส่งอีเมลด้วย SMTPMailer (Direct SSL Socket)
        require_once 'SMTPMailer.php';
        require_once __DIR__ . '/config.php';

        $mailer = new SMTPMailer(SMTP_USER, SMTP_PASS);
        // Param 5 = true (HTML Mode)
        $mail_sent = $mailer->send($to, $plain_subject, $message, 'เทศบาลเมืองศิลา', true);

        // 3. บันทึก Log
        $log_dir = __DIR__ . "/../logs/";
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $log_content = "[" . date('Y-m-d H:i:s') . "] ID: #{$request_id}, "
            . "Status: {$request['status']}, "
            . "Email: {$to}, "
            . "Sent: " . ($mail_sent ? "Yes (SMTP HTML)" : "No (SMTP Error)") . "\n";

        if (!$mail_sent) {
            $log_content .= "SMTP Logs:\n" . print_r($mailer->getLogs(), true) . "\n";
        }

        file_put_contents($log_dir . "email_log.txt", $log_content, FILE_APPEND);

        return $mail_sent;
    }
}
?>