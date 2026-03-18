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
            case 'waiting_permit':
                return 'รอออกใบอนุญาต';
            case 'approved':
                return 'อนุมัติเรียบร้อย';
            case 'rejected':
                return 'ไม่ผ่านการพิจารณา';
            case 'expired':
                return 'ใบอนุญาตหมดอายุ';
            case 'cancelled_payment':
                return 'ยกเลิก';
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
            case 'waiting_permit':
                return '#0d6efd'; // Primary Blue
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
        $sql = "SELECT r.status, r.email, r.sign_type, r.applicant_name, r.fee, r.request_no
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
        // ใช้ request_no ถ้ามี ถ้าไม่มีใช้แบบเดิม
        $request_display = !empty($request['request_no']) ? $request['request_no'] : "#{$request_id}";
        // Subject ภาษาไทย (Plain Text)
        $plain_subject = "[เทศบาลเมืองศิลา] แจ้งสถานะคำร้องขอติดตั้งป้าย (รหัส: {$request_display})";

        $status_text = get_status_label($request['status']);
        $status_color = get_status_color($request['status']);

        // ข้อความเพิ่มเติมสำหรับสถานะรอชำระเงิน — แจ้ง deadline 24 ชม.
        $payment_notice = '';
        if ($request['status'] === 'waiting_payment') {
            $deadline_dt = date('d/m/Y H:i น.', strtotime('+24 hours'));
            $payment_notice = "
                <div style='background:#fff8e1;border-left:4px solid #ffc107;border-radius:4px;padding:16px;margin:16px 0;'>
                    <p style='margin:0 0 8px;color:#856404;font-weight:600;font-size:15px;'>
                        ⏰ กรุณาชำระค่าธรรมเนียมภายใน 24 ชั่วโมง
                    </p>
                    <p style='margin:0;color:#856404;font-size:14px;line-height:1.4;'>
                        ต้องชำระก่อน <strong style='color:#dc3545;'>{$deadline_dt}</strong><br>
                        <small style='color:#856404;'>หากไม่ชำระภายในกำหนด คำร้องจะถูกยกเลิกโดยอัตโนมัติ</small>
                    </p>
                </div>";
        }

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
                            <td>{$request_display}</td>
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

                    {$payment_notice}

                    <p style='text-align:center; margin-top: 25px;'>
                        <a href='" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . "/users/my_request.php' class='btn'>ตรวจสอบรายละเอียด</a>
                    </p>
                    
                    <p style='margin-top: 20px; font-size: 14px; color: #666;'>
                        หากมีข้อสงสัย กรุณาติดต่อ เทศบาลเมืองศิลา <br> โทร 043-246-505-6 ในวันและเวลาราชการ
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
            mkdir($log_dir, 0755, true);
        }
        $log_content = "[" . date('Y-m-d H:i:s') . "] ID: {$request_display}, "
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

/**
 * ส่งอีเมลแบบ Background Process — เปิด PHP process แยกส่งอีเมล
 * หน้าเว็บไม่ต้องรอเลย กดปุ่มปั๊บตอบกลับทันที
 */
if (!function_exists('queue_status_notification')) {
    function queue_status_notification($request_id, $conn)
    {
        $request_id = (int) $request_id;
        $worker = __DIR__ . '/send_email_worker.php';
        // PHP_BINARY บน Apache จะคืน httpd.exe ซึ่งใช้ไม่ได้ ต้องใช้ php.exe CLI ตรงๆ
        $php = 'C:\\xampp\\php\\php.exe';

        $log_dir = __DIR__ . '/../logs/';
        if (!file_exists($log_dir)) mkdir($log_dir, 0755, true);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'start /B "" "' . $php . '" "' . $worker . '" ' . $request_id;
            file_put_contents($log_dir . 'email_log.txt',
                "[" . date('Y-m-d H:i:s') . "] Queue: ID={$request_id}, PHP={$php}, CMD={$cmd}\n", FILE_APPEND);
            $handle = popen($cmd, 'r');
            if ($handle) {
                pclose($handle);
            }
        } else {
            $cmd = '"' . $php . '" "' . $worker . '" ' . $request_id . ' > /dev/null 2>&1 &';
            exec($cmd);
        }
    }
}
?>