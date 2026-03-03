<?php
/**
 * Cron Job: ตรวจจับคำร้องที่รอชำระเงินเกินกำหนด
 * 
 * ทำงาน: ตรวจสอบคำร้องที่อยู่ในสถานะ 'waiting_payment' เกิน X วัน
 *         → เปลี่ยนสถานะเป็น 'cancelled_payment' 
 *         → ส่ง Email แจ้งเตือนผู้ยื่นคำร้อง
 * 
 * ตั้ง Cron: รันวันละ 1 ครั้ง เช่น ทุกวันเวลา 08:00
 * Windows Task Scheduler: php C:\xampp\htdocs\Project2026\cron\check_payment_timeout.php
 * Linux Cron: 0 8 * * * php /var/www/html/Project2026/cron/check_payment_timeout.php
 */

// ─── Config ───
$PAYMENT_DEADLINE_DAYS = 7; // จำนวนวันที่ให้ชำระเงิน

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
    echo $line; // สำหรับดูผลตอนรัน manual
}

cronLog("=== เริ่มตรวจสอบคำร้องรอชำระเงินเกินกำหนด ($PAYMENT_DEADLINE_DAYS วัน) ===", $log_file);

// ─── ค้นหาคำร้องที่รอชำระเงินเกินกำหนด ───
// ใช้ created_at ของ sign_requests ในการตรวจสอบ (เนื่องจากไม่มี updated_at column)
$sql = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee,
               sr.created_at
        FROM sign_requests sr
        WHERE sr.status = 'waiting_payment'
        AND sr.created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $PAYMENT_DEADLINE_DAYS);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;

while ($row = $result->fetch_assoc()) {
    $request_id = $row['id'];
    
    cronLog("คำร้อง #{$request_id} — รอชำระเงินเกิน {$PAYMENT_DEADLINE_DAYS} วัน → ยกเลิก", $log_file);
    
    // 1. เปลี่ยนสถานะเป็น cancelled_payment
    $update = $conn->prepare("UPDATE sign_requests SET status = 'cancelled_payment' WHERE id = ? AND status = 'waiting_payment'");
    $update->bind_param("i", $request_id);
    
    if ($update->execute() && $update->affected_rows > 0) {
        // 2. บันทึก Log
        logRequestAction($conn, $request_id, 'cancelled_payment', 
            'ยกเลิกอัตโนมัติ — ไม่ชำระเงินภายในกำหนด', 
            null, 
            "เกินกำหนด {$PAYMENT_DEADLINE_DAYS} วัน"
        );
        
        // 3. ส่ง Email แจ้งเตือน
        if (!empty($row['email'])) {
            sendPaymentTimeoutEmail($row, $PAYMENT_DEADLINE_DAYS, $conn);
            cronLog("  → ส่ง Email แจ้ง {$row['email']} เรียบร้อย", $log_file);
        }
        
        $count++;
    }
}

cronLog("=== เสร็จสิ้น: ยกเลิก {$count} คำร้อง ===\n", $log_file);

// ─── ฟังก์ชันส่ง Email แจ้งยกเลิก ───
function sendPaymentTimeoutEmail($request, $deadline_days, $conn) {
    require_once __DIR__ . '/../includes/SMTPMailer.php';
    require_once __DIR__ . '/../includes/config.php';
    
    $to = $request['email'];
    $request_id = $request['id'];
    $subject = "[เทศบาลเมืองศิลา] คำร้อง #{$request_id} ถูกยกเลิก — ไม่ชำระเงินภายในกำหนด";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white !important; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='margin:0;'>แจ้งยกเลิกคำร้อง</h2>
            </div>
            <div class='content'>
                <p>เรียน คุณ {$request['applicant_name']},</p>
                <p>คำร้องขอติดตั้งป้ายของท่าน <strong>เลขที่ #{$request_id}</strong> 
                   ได้ถูก<span style='color:#dc3545;font-weight:bold;'>ยกเลิก</span>โดยอัตโนมัติ 
                   เนื่องจากไม่ได้ชำระค่าธรรมเนียมภายใน <strong>{$deadline_days} วัน</strong></p>
                
                <table style='width:100%;border-collapse:collapse;margin:15px 0;'>
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>ประเภทป้าย:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>{$request['sign_type']}</td></tr>
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>ค่าธรรมเนียม:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>" . number_format($request['fee'], 2) . " บาท</td></tr>
                </table>
                
                <p>หากท่านยังต้องการขออนุญาต กรุณายื่นคำร้องใหม่อีกครั้ง</p>
                
                <p style='text-align:center; margin-top: 25px;'>
                    <a href='http://localhost/Project2026/users/request_form.php' class='btn'>ยื่นคำร้องใหม่</a>
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
