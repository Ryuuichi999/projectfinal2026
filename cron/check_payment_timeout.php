<?php
/**
 * Cron Job: ตรวจจับคำร้องที่รอชำระเงินเกินกำหนด
 * 
 * ทำงาน:
 *   ส่วน 1 — เตือนล่วงหน้า 2 วันก่อนครบกำหนด (วันที่ 5 จาก 7)
 *   ส่วน 2 — ยกเลิกอัตโนมัติเมื่อเกิน 7 วัน + ส่ง Email แจ้ง
 * 
 * นับวันจาก: request_logs ที่เปลี่ยนสถานะเป็น waiting_payment
 * 
 * ตั้ง Cron: รันวันละ 1 ครั้ง (ระบบจะ auto-run ผ่าน cron_runner.php)
 */

// ─── Config ───
$PAYMENT_DEADLINE_DAYS = 7;  // จำนวนวันที่ให้ชำระเงิน
$WARN_BEFORE_DAYS = 2;       // เตือนล่วงหน้ากี่วันก่อนยกเลิก

// ─── Bootstrap ───
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';

$log_file = __DIR__ . '/../logs/cron_payment_timeout.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function cronLog($msg, $log_file) {
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

cronLog("=== เริ่มตรวจสอบคำร้องรอชำระเงินเกินกำหนด ({$PAYMENT_DEADLINE_DAYS} วัน) ===", $log_file);

$cancelled_count = 0;
$warned_count = 0;

// ═══════════════════════════════════════════════
// ส่วนที่ 1: ยกเลิกอัตโนมัติ — เกินกำหนดชำระเงิน
// นับจากวันที่ล่าสุดที่เปลี่ยนเป็น waiting_payment ใน request_logs
// ═══════════════════════════════════════════════
$sql_cancel = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                      rl.created_at AS status_changed_at
               FROM sign_requests sr
               INNER JOIN (
                   SELECT request_id, MAX(created_at) AS created_at
                   FROM request_logs
                   WHERE action = 'status_waiting_payment' OR action_label LIKE '%รอชำระเงิน%'
                   GROUP BY request_id
               ) rl ON rl.request_id = sr.id
               WHERE sr.status = 'waiting_payment'
               AND rl.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

$stmt = $conn->prepare($sql_cancel);
if (!$stmt) {
    // Fallback: ถ้าไม่มี log ที่ตรงกัน ใช้ created_at ของ sign_requests แทน
    cronLog("Fallback: ใช้ created_at แทน request_logs", $log_file);
    $sql_cancel = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                          sr.created_at AS status_changed_at
                   FROM sign_requests sr
                   WHERE sr.status = 'waiting_payment'
                   AND sr.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($sql_cancel);
}
$stmt->bind_param("i", $PAYMENT_DEADLINE_DAYS);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $request_id = $row['id'];
    $request_display = !empty($row['request_no']) ? $row['request_no'] : "#{$request_id}";
    
    cronLog("คำร้อง {$request_display} — รอชำระเงินเกิน {$PAYMENT_DEADLINE_DAYS} วัน (ตั้งแต่ {$row['status_changed_at']}) → ยกเลิก", $log_file);
    
    // 1. เปลี่ยนสถานะเป็น cancelled_payment
    $update = $conn->prepare("UPDATE sign_requests SET status = 'cancelled_payment' WHERE id = ? AND status = 'waiting_payment'");
    $update->bind_param("i", $request_id);
    
    if ($update->execute() && $update->affected_rows > 0) {
        // 2. บันทึก Log
        logRequestAction($conn, $request_id, 'cancelled_payment', 
            'ยกเลิกอัตโนมัติ — ไม่ชำระเงินภายในกำหนด', 
            null, 
            "เกินกำหนด {$PAYMENT_DEADLINE_DAYS} วัน (ตั้งแต่ {$row['status_changed_at']})"
        );
        
        // 3. ส่ง Email แจ้งยกเลิก
        if (!empty($row['email'])) {
            sendPaymentEmail($row, $PAYMENT_DEADLINE_DAYS, 'cancelled');
            cronLog("  → ส่ง Email แจ้งยกเลิก {$row['email']}", $log_file);
        }
        
        $cancelled_count++;
    }
}

// ═══════════════════════════════════════════════
// ส่วนที่ 2: เตือนล่วงหน้า — ใกล้ครบกำหนดชำระเงิน
// เตือนเมื่อเหลืออีก WARN_BEFORE_DAYS วัน (เช่น วันที่ 5 จาก 7)
// ═══════════════════════════════════════════════
$warn_after_days = $PAYMENT_DEADLINE_DAYS - $WARN_BEFORE_DAYS;

$sql_warn = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                    rl.created_at AS status_changed_at
             FROM sign_requests sr
             INNER JOIN (
                 SELECT request_id, MAX(created_at) AS created_at
                 FROM request_logs
                 WHERE action = 'status_waiting_payment' OR action_label LIKE '%รอชำระเงิน%'
                 GROUP BY request_id
             ) rl ON rl.request_id = sr.id
             WHERE sr.status = 'waiting_payment'
             AND DATE(rl.created_at) = DATE_SUB(CURDATE(), INTERVAL ? DAY)";

$stmt_warn = $conn->prepare($sql_warn);
if (!$stmt_warn) {
    $sql_warn = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee, sr.request_no,
                        sr.created_at AS status_changed_at
                 FROM sign_requests sr
                 WHERE sr.status = 'waiting_payment'
                 AND DATE(sr.created_at) = DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $stmt_warn = $conn->prepare($sql_warn);
}
$stmt_warn->bind_param("i", $warn_after_days);
$stmt_warn->execute();
$result_warn = $stmt_warn->get_result();

while ($row = $result_warn->fetch_assoc()) {
    $request_id = $row['id'];
    $request_display = !empty($row['request_no']) ? $row['request_no'] : "#{$request_id}";
    
    cronLog("คำร้อง {$request_display} — เหลือเวลาชำระเงินอีก {$WARN_BEFORE_DAYS} วัน → ส่งเตือน", $log_file);
    
    if (!empty($row['email'])) {
        sendPaymentEmail($row, $WARN_BEFORE_DAYS, 'warning');
        cronLog("  → ส่ง Email เตือน {$row['email']}", $log_file);
    }
    
    logRequestAction($conn, $request_id, 'payment_warning', 
        "แจ้งเตือนชำระเงิน — เหลืออีก {$WARN_BEFORE_DAYS} วัน", 
        null, 
        "กรุณาชำระภายใน {$WARN_BEFORE_DAYS} วัน มิฉะนั้นคำร้องจะถูกยกเลิกอัตโนมัติ"
    );
    
    $warned_count++;
}

cronLog("=== เสร็จสิ้น: ยกเลิก {$cancelled_count} คำร้อง, เตือนชำระเงิน {$warned_count} คำร้อง ===\n", $log_file);

// ─── ฟังก์ชันส่ง Email ───
function sendPaymentEmail($request, $days, $type) {
    require_once __DIR__ . '/../includes/SMTPMailer.php';
    require_once __DIR__ . '/../includes/config.php';
    
    $to = $request['email'];
    $request_id = $request['id'];
    $request_display = !empty($request['request_no']) ? $request['request_no'] : "#{$request_id}";
    $base_url = defined('BASE_URL') ? BASE_URL : '/Project2026';

    if ($type === 'cancelled') {
        $subject = "[เทศบาลเมืองศิลา] คำร้อง {$request_display} ถูกยกเลิก — ไม่ชำระเงินภายในกำหนด";
        $header_bg = '#dc3545';
        $header_text = 'คำร้องถูกยกเลิก';
        $body_text = "คำร้องขอติดตั้งป้ายของท่าน <strong>เลขที่ {$request_display}</strong> 
                      ได้ถูก<span style='color:#dc3545;font-weight:bold;'>ยกเลิก</span>โดยอัตโนมัติ 
                      เนื่องจากไม่ได้ชำระค่าธรรมเนียมภายใน <strong>{$days} วัน</strong>
                      <br><br>หากท่านยังต้องการขออนุญาต กรุณายื่นคำร้องใหม่อีกครั้ง";
        $btn_text = 'ยื่นคำร้องใหม่';
        $btn_url = 'http://localhost' . $base_url . '/users/request_form.php';
    } else {
        $subject = "[เทศบาลเมืองศิลา] เตือนชำระเงินคำร้อง {$request_display} — เหลืออีก {$days} วัน";
        $header_bg = '#fd7e14';
        $header_text = 'เตือนชำระค่าธรรมเนียม';
        $body_text = "คำร้องขอติดตั้งป้ายของท่าน <strong>เลขที่ {$request_display}</strong> 
                      <span style='color:#fd7e14;font-weight:bold;'>เหลือเวลาชำระเงินอีก {$days} วัน</span>
                      <br><br><strong>กรุณาชำระค่าธรรมเนียมภายในกำหนด</strong> มิฉะนั้นคำร้องจะถูกยกเลิกโดยอัตโนมัติ";
        $btn_text = 'ชำระเงินเลย';
        $btn_url = 'http://localhost' . $base_url . '/users/request_detail.php?id=' . $request_id;
    }
    
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
