<?php
/**
 * Cron Job: ตรวจจับคำร้องที่รอชำระเงินเกินกำหนด
 * 
 * ทำงาน: ยกเลิกอัตโนมัติเมื่อเกิน 24 ชั่วโมง + ส่ง Email แจ้ง
 * 
 * นับเวลาจาก: request_logs ที่เปลี่ยนสถานะเป็น waiting_payment
 * 
 * ตั้ง Cron: รันทุกชั่วโมง (ระบบจะ auto-run ผ่าน cron_runner.php)
 */

// ─── Config ───
$PAYMENT_DEADLINE_HOURS = 24;  // จำนวนชั่วโมงที่ให้ชำระเงิน (24 ชม.)

$log_file = __DIR__ . '/../logs/cron_payment_timeout.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

if (!function_exists('cronLog')) {
    function cronLog($msg, $log_file) {
        $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
        file_put_contents($log_file, $line, FILE_APPEND);
        echo $line;
    }
}

// Global error handler — จับ error ทุกประเภทและบันทึกลง log
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($log_file) {
    cronLog("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}", $log_file);
    return false;
});
set_exception_handler(function($e) use ($log_file) {
    cronLog("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), $log_file);
});
register_shutdown_function(function() use ($log_file) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        cronLog("Fatal Error: {$err['message']} in {$err['file']}:{$err['line']}", $log_file);
    }
});

// ─── Bootstrap ───
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';

// ─── ฟังก์ชันส่ง Email (ต้องประกาศก่อนเรียกใช้) ───
if (!function_exists('sendPaymentEmail')) {
function sendPaymentEmail($request, $days, $type) {
    require_once __DIR__ . '/../includes/SMTPMailer.php';
    require_once __DIR__ . '/../includes/config.php';
    
    $to = $request['email'];
    $request_id = $request['id'];
    $request_display = !empty($request['request_no']) ? $request['request_no'] : "#{$request_id}";
    $site_url = defined('SITE_URL') ? SITE_URL : 'http://localhost/Project2026';

    $subject = "[เทศบาลเมืองศิลา] คำร้อง {$request_display} ถูกยกเลิก — ไม่ชำระเงินภายใน 24 ชั่วโมง";
    $header_bg = '#dc3545';
    $header_text = 'คำร้องถูกยกเลิก';
    $body_text = "คำร้องขอติดตั้งป้ายของท่าน <strong>เลขที่ {$request_display}</strong> 
                  ได้ถูก<span style='color:#dc3545;font-weight:bold;'>ยกเลิก</span>โดยอัตโนมัติ 
                  เนื่องจากไม่ได้ชำระค่าธรรมเนียมภายใน <strong>24 ชั่วโมง</strong>
                  <br><br>หากท่านยังต้องการขออนุญาต กรุณายื่นคำร้องใหม่อีกครั้ง";
    $btn_text = 'ยื่นคำร้องใหม่';
    $btn_url = $site_url . '/users/request_form.php';
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
            .header { background-color: {$header_bg}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white !important; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='margin:0;'>{$header_text}</h2>
            </div>
            <div class='content'>
                <p>เรียน คุณ {$request['applicant_name']},</p>
                <p>{$body_text}</p>
                
                <table style='width:100%;border-collapse:collapse;margin:15px 0;'>
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>ประเภทป้าย:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>{$request['sign_type']}</td></tr>
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>ค่าธรรมเนียม:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>" . number_format($request['fee'], 2) . " บาท</td></tr>
                </table>
                
                <p style='text-align:center; margin-top: 25px;'>
                    <a href='{$btn_url}' class='btn'>{$btn_text}</a>
                </p>
                
                <p style='margin-top:20px;font-size:14px;color:#666;'>
                    หากมีข้อสงสัย กรุณาติดต่อ เทศบาลเมืองศิลา โทร 043-246-505-6
                </p>
            </div>
            <div class='footer'>
                อีเมลฉบับนี้เป็นการแจ้งเตือนอัตโนมัติ กรุณาอย่าตอบกลับ<br>
                &copy; " . date('Y') . " เทศบาลเมืองศิลา
            </div>
        </div>
    </body>
    </html>";
    
    $mailer = new SMTPMailer(SMTP_USER, SMTP_PASS);
    return $mailer->send($to, $subject, $message, 'เทศบาลเมืองศิลา', true);
}
}

cronLog("=== เริ่มตรวจสอบคำร้องรอชำระเงินเกินกำหนด ({$PAYMENT_DEADLINE_HOURS} ชั่วโมง) ===", $log_file);

$cancelled_count = 0;
$warned_count = 0;

// ═══════════════════════════════════════════════
// ยกเลิกอัตโนมัติ — เกินกำหนดชำระเงิน 24 ชั่วโมง
// นับจากเวลาที่เปลี่ยนเป็น waiting_payment ใน request_logs
// ═══════════════════════════════════════════════
$sql_cancel = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                      rl.created_at AS status_changed_at
               FROM sign_requests sr
               INNER JOIN (
                   SELECT request_id, MAX(created_at) AS created_at
                   FROM request_logs
                   WHERE action = 'waiting_payment'
                   GROUP BY request_id
               ) rl ON rl.request_id = sr.id
               WHERE sr.status = 'waiting_payment'
               AND rl.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)";

$stmt = $conn->prepare($sql_cancel);
if (!$stmt) {
    cronLog("Fallback: ใช้ created_at แทน request_logs", $log_file);
    $sql_cancel = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                          sr.created_at AS status_changed_at
                   FROM sign_requests sr
                   WHERE sr.status = 'waiting_payment'
                   AND sr.created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)";
    $stmt = $conn->prepare($sql_cancel);
}
$stmt->bind_param("i", $PAYMENT_DEADLINE_HOURS);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $request_id = $row['id'];
    $request_display = !empty($row['request_no']) ? $row['request_no'] : "#{$request_id}";
    
    $deadline_time = date('H:i น.', strtotime($row['status_changed_at'] . " +{$PAYMENT_DEADLINE_HOURS} hours"));
    cronLog("คำร้อง {$request_display} — รอชำระเงินเกิน {$PAYMENT_DEADLINE_HOURS} ชม. (ตั้งแต่ {$row['status_changed_at']}) → ยกเลิก", $log_file);
    
    // 1. เปลี่ยนสถานะเป็น cancelled_payment
    $update = $conn->prepare("UPDATE sign_requests SET status = 'cancelled_payment' WHERE id = ? AND status = 'waiting_payment'");
    $update->bind_param("i", $request_id);
    
    if ($update->execute() && $update->affected_rows > 0) {
        // 2. บันทึก Log
        logRequestAction($conn, $request_id, 'cancelled_payment', 
            'ยกเลิกอัตโนมัติ — ไม่ชำระเงินภายใน 24 ชั่วโมง', 
            null, 
            "เกินกำหนด {$PAYMENT_DEADLINE_HOURS} ชั่วโมง (ตั้งแต่ {$row['status_changed_at']})"
        );
        
        // 3. ส่ง Email แจ้งยกเลิก
        if (!empty($row['email'])) {
            sendPaymentEmail($row, $PAYMENT_DEADLINE_HOURS, 'cancelled');
            cronLog("  → ส่ง Email แจ้งยกเลิก {$row['email']}", $log_file);
        }
        
        $cancelled_count++;
    }
}

// ไม่มีส่วนเตือนล่วงหน้า (เพราะ deadline แค่ 24 ชม. การแจ้งเตือนจะทำตอนอนุมัติผ่าน Email แทน)

cronLog("=== เสร็จสิ้น: ยกเลิก {$cancelled_count} คำร้อง ===\n", $log_file);

// (sendPaymentEmail ถูกย้ายไปประกาศก่อน cronLog ด้านบนแล้ว)
