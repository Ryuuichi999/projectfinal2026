<?php
/**
 * CLI Worker Script — ส่งอีเมลแจ้งเตือนเบื้องหลัง
 * ถูกเรียกจาก queue_status_notification() ผ่าน popen()
 * Usage: php send_email_worker.php <request_id>
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

if (!isset($argv[1]) || !is_numeric($argv[1])) {
    exit('Missing request_id');
}

$request_id = (int) $argv[1];

// Load config & connect DB
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SMTPMailer.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    file_put_contents(__DIR__ . '/../logs/email_log.txt',
        "[" . date('Y-m-d H:i:s') . "] Worker DB Error: " . $conn->connect_error . "\n", FILE_APPEND);
    exit('DB error');
}

// Fetch request data
$sql = "SELECT status, email, sign_type, applicant_name, fee, request_no FROM sign_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request || empty($request['email'])) {
    $conn->close();
    exit('No email');
}

// Status labels
function worker_status_label($s) {
    $map = [
        'pending' => 'รอพิจารณา',
        'reviewing' => 'กำลังพิจารณา',
        'need_documents' => 'ขอเอกสารเพิ่มเติม',
        'waiting_payment' => 'รอชำระเงิน',
        'waiting_receipt' => 'รอออกใบเสร็จ',
        'waiting_permit' => 'รอออกใบอนุญาต',
        'approved' => 'อนุมัติเรียบร้อย',
        'rejected' => 'ไม่ผ่านการพิจารณา',
        'expired' => 'ใบอนุญาตหมดอายุ',
        'cancelled_payment' => 'ยกเลิก (ไม่ชำระเงิน)',
    ];
    return $map[$s] ?? $s;
}
function worker_status_color($s) {
    $map = [
        'pending' => '#ffc107',
        'reviewing' => '#0d6efd',
        'need_documents' => '#17a2b8',
        'waiting_payment' => '#fd7e14',
        'waiting_receipt' => '#6610f2',
        'waiting_permit' => '#0d6efd',
        'approved' => '#198754',
        'rejected' => '#dc3545',
    ];
    return $map[$s] ?? '#6c757d';
}

$to = $request['email'];
$request_display = !empty($request['request_no']) ? $request['request_no'] : "#{$request_id}";
$plain_subject = "[เทศบาลเมืองศิลา] แจ้งสถานะคำร้องขอติดตั้งป้าย (รหัส: {$request_display})";
$status_text = worker_status_label($request['status']);
$status_color = worker_status_color($request['status']);

$base_url = defined('BASE_URL') ? BASE_URL : '/Project2026';

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
                <a href='http://localhost{$base_url}/users/my_request.php' class='btn'>ตรวจสอบรายละเอียด</a>
            </p>
            
            <p style='margin-top: 20px; font-size: 14px; color: #666;'>
                หากมีข้อสงสัย กรุณาติดต่อ เทศบาลเมืองศิลา โทร 043-246-505-6<br>
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

// Send
$mailer = new SMTPMailer(SMTP_USER, SMTP_PASS);
$mail_sent = $mailer->send($to, $plain_subject, $message, 'เทศบาลเมืองศิลา', true);

// Log
$log_dir = __DIR__ . '/../logs/';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_content = "[" . date('Y-m-d H:i:s') . "] ID: {$request_display}, "
    . "Status: {$request['status']}, "
    . "Email: {$to}, "
    . "Sent: " . ($mail_sent ? "Yes (Background Worker)" : "No (SMTP Error)") . "\n";
if (!$mail_sent) {
    $log_content .= "SMTP Logs:\n" . print_r($mailer->getLogs(), true) . "\n";
}
file_put_contents($log_dir . "email_log.txt", $log_content, FILE_APPEND);

$conn->close();
