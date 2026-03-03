<?php
/**
 * Cron Job: ตรวจจับใบอนุญาตที่หมดอายุ
 * 
 * ทำงาน: ตรวจสอบคำร้องที่สถานะ 'approved' และหมดอายุแล้ว
 *         → เปลี่ยนสถานะเป็น 'expired'
 *         → ส่ง Email แจ้งเตือนผู้ยื่นคำร้อง
 *         → จุดบนแผนที่จะไม่แสดง (เพราะ map_public กรองเฉพาะ approved)
 * 
 * ตั้ง Cron: รันวันละ 1 ครั้ง เช่น ทุกวันเวลา 00:30
 * Windows Task Scheduler: php C:\xampp\htdocs\Project2026\cron\check_permit_expiry.php
 * Linux Cron: 30 0 * * * php /var/www/html/Project2026/cron/check_permit_expiry.php
 */

// ─── Config ───
$WARN_BEFORE_DAYS = 7; // แจ้งเตือนล่วงหน้ากี่วันก่อนหมดอายุ

// ─── Bootstrap ───
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/log_helper.php';

$log_file = __DIR__ . '/../logs/cron_permit_expiry.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function cronLog($msg, $log_file) {
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

cronLog("=== เริ่มตรวจสอบใบอนุญาตหมดอายุ ===", $log_file);

$today = date('Y-m-d');
$expired_count = 0;
$warned_count = 0;

// ═══════════════════════════════════════════════
// ส่วนที่ 1: ใบอนุญาตที่หมดอายุแล้ว → เปลี่ยนสถานะเป็น expired
// ═══════════════════════════════════════════════
$sql_expired = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee,
                       sr.duration_days, sr.permit_date, sr.created_at, sr.road_name
                FROM sign_requests sr
                WHERE sr.status = 'approved'
                AND sr.duration_days > 0
                AND (
                    -- ใช้ permit_date ถ้ามี ไม่งั้นใช้ created_at
                    (sr.permit_date IS NOT NULL AND DATE_ADD(sr.permit_date, INTERVAL sr.duration_days DAY) < CURDATE())
                    OR
                    (sr.permit_date IS NULL AND DATE_ADD(sr.created_at, INTERVAL sr.duration_days DAY) < CURDATE())
                )";

$result_expired = $conn->query($sql_expired);

while ($row = $result_expired->fetch_assoc()) {
    $request_id = $row['id'];
    $base_date = !empty($row['permit_date']) ? $row['permit_date'] : date('Y-m-d', strtotime($row['created_at']));
    $expire_date = date('Y-m-d', strtotime($base_date . ' + ' . $row['duration_days'] . ' days'));
    
    cronLog("คำร้อง #{$request_id} — หมดอายุ {$expire_date} → เปลี่ยนเป็น expired", $log_file);
    
    // 1. เปลี่ยนสถานะ
    $update = $conn->prepare("UPDATE sign_requests SET status = 'expired' WHERE id = ? AND status = 'approved'");
    $update->bind_param("i", $request_id);
    
    if ($update->execute() && $update->affected_rows > 0) {
        // 2. บันทึก Log
        logRequestAction($conn, $request_id, 'expired', 
            'ใบอนุญาตหมดอายุอัตโนมัติ', 
            null, 
            "หมดอายุวันที่ {$expire_date}"
        );
        
        // 3. ส่ง Email แจ้งเตือน
        if (!empty($row['email'])) {
            sendExpiryEmail($row, $expire_date, 'expired', $conn);
            cronLog("  → ส่ง Email แจ้ง {$row['email']} (หมดอายุ)", $log_file);
        }
        
        $expired_count++;
    }
}

// ═══════════════════════════════════════════════
// ส่วนที่ 2: ใบอนุญาตที่จะหมดอายุใน X วัน → ส่ง Email เตือนล่วงหน้า
// ═══════════════════════════════════════════════
$warn_date = date('Y-m-d', strtotime("+{$WARN_BEFORE_DAYS} days"));

$sql_warning = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee,
                       sr.duration_days, sr.permit_date, sr.created_at, sr.road_name
                FROM sign_requests sr
                WHERE sr.status = 'approved'
                AND sr.duration_days > 0
                AND (
                    (sr.permit_date IS NOT NULL AND DATE_ADD(sr.permit_date, INTERVAL sr.duration_days DAY) = ?)
                    OR
                    (sr.permit_date IS NULL AND DATE_ADD(DATE(sr.created_at), INTERVAL sr.duration_days DAY) = ?)
                )";

$stmt_warn = $conn->prepare($sql_warning);
$stmt_warn->bind_param("ss", $warn_date, $warn_date);
$stmt_warn->execute();
$result_warning = $stmt_warn->get_result();

while ($row = $result_warning->fetch_assoc()) {
    $request_id = $row['id'];
    $base_date = !empty($row['permit_date']) ? $row['permit_date'] : date('Y-m-d', strtotime($row['created_at']));
    $expire_date = date('Y-m-d', strtotime($base_date . ' + ' . $row['duration_days'] . ' days'));
    
    cronLog("คำร้อง #{$request_id} — จะหมดอายุใน {$WARN_BEFORE_DAYS} วัน ({$expire_date}) → ส่งเตือน", $log_file);
    
    // ส่ง Email เตือนล่วงหน้า
    if (!empty($row['email'])) {
        sendExpiryEmail($row, $expire_date, 'warning', $conn);
        cronLog("  → ส่ง Email เตือน {$row['email']} (ใกล้หมดอายุ)", $log_file);
    }
    
    // บันทึก Log
    logRequestAction($conn, $request_id, 'expiry_warning', 
        "แจ้งเตือนใบอนุญาตจะหมดอายุใน {$WARN_BEFORE_DAYS} วัน", 
        null, 
        "หมดอายุวันที่ {$expire_date}"
    );
    
    $warned_count++;
}

// ═══════════════════════════════════════════════
// ส่วนที่ 3: ติดตามใบอนุญาตหมดอายุครบ 7 วัน → ส่ง Email เตือนเก็บป้ายครั้งสุดท้าย
// ═══════════════════════════════════════════════
$followup_count = 0;
$followup_date = date('Y-m-d', strtotime('-7 days'));

$sql_followup = "SELECT sr.id, sr.email, sr.applicant_name, sr.sign_type, sr.fee,
                       sr.duration_days, sr.permit_date, sr.created_at, sr.road_name
                FROM sign_requests sr
                WHERE sr.status = 'expired'
                AND sr.duration_days > 0
                AND (
                    (sr.permit_date IS NOT NULL AND DATE_ADD(sr.permit_date, INTERVAL sr.duration_days DAY) = ?)
                    OR
                    (sr.permit_date IS NULL AND DATE_ADD(DATE(sr.created_at), INTERVAL sr.duration_days DAY) = ?)
                )";

$stmt_followup = $conn->prepare($sql_followup);
$stmt_followup->bind_param("ss", $followup_date, $followup_date);
$stmt_followup->execute();
$result_followup = $stmt_followup->get_result();

while ($row = $result_followup->fetch_assoc()) {
    $request_id = $row['id'];
    $base_date = !empty($row['permit_date']) ? $row['permit_date'] : date('Y-m-d', strtotime($row['created_at']));
    $expire_date = date('Y-m-d', strtotime($base_date . ' + ' . $row['duration_days'] . ' days'));
    
    cronLog("คำร้อง #{$request_id} — หมดอายุครบ 7 วัน ({$expire_date}) → ส่งเตือนเก็บป้ายครั้งสุดท้าย", $log_file);
    
    if (!empty($row['email'])) {
        sendExpiryEmail($row, $expire_date, 'followup_7days', $conn);
        cronLog("  → ส่ง Email ติดตาม {$row['email']} (เกินกำหนดเก็บป้าย)", $log_file);
    }
    
    logRequestAction($conn, $request_id, 'followup_expired', 
        'แจ้งเตือนเก็บป้ายครั้งสุดท้าย (ครบ 7 วัน)', 
        null, 
        "หมดอายุตั้งแต่ {$expire_date} — เกินกำหนดเก็บป้ายแล้ว"
    );
    
    $followup_count++;
}

cronLog("=== เสร็จสิ้น: หมดอายุ {$expired_count}, เตือนล่วงหน้า {$warned_count}, ติดตาม {$followup_count} ===\n", $log_file);

// ─── ฟังก์ชันส่ง Email ───
function sendExpiryEmail($request, $expire_date, $type, $conn) {
    require_once __DIR__ . '/../includes/SMTPMailer.php';
    require_once __DIR__ . '/../includes/config.php';
    
    $to = $request['email'];
    $request_id = $request['id'];
    
    // แปลงวันที่เป็นไทย
    $months_th = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
                  7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    $ts = strtotime($expire_date);
    $expire_th = date('j', $ts) . ' ' . $months_th[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
    
    if ($type === 'expired') {
        $subject = "[เทศบาลเมืองศิลา] ใบอนุญาตป้าย #{$request_id} หมดอายุแล้ว";
        $header_bg = '#dc3545';
        $header_text = 'ใบอนุญาตหมดอายุ';
        $body_text = "ใบอนุญาตติดตั้งป้ายของท่าน <strong>เลขที่ #{$request_id}</strong> 
                      ได้<span style='color:#dc3545;font-weight:bold;'>หมดอายุ</span>แล้ว 
                      เมื่อวันที่ <strong>{$expire_th}</strong>
                      <br><br>กรุณาดำเนินการถอดป้ายออกภายใน 7 วัน มิฉะนั้นอาจถูกดำเนินการตามกฎหมาย";
        $btn_text = 'ดูรายละเอียด';
        $btn_url = 'http://localhost/Project2026/users/request_detail.php?id=' . $request_id;
    } elseif ($type === 'followup_7days') {
        $subject = "[เทศบาลเมืองศิลา] ⚠️ เกินกำหนดเก็บป้าย #{$request_id} — กรุณาถอดป้ายทันที";
        $header_bg = '#1f2937';
        $header_text = 'เกินกำหนดเก็บป้าย';
        $body_text = "ใบอนุญาตติดตั้งป้ายของท่าน <strong>เลขที่ #{$request_id}</strong> 
                      ได้หมดอายุเมื่อวันที่ <strong>{$expire_th}</strong> 
                      ซึ่ง<span style='color:#dc3545;font-weight:bold;'>เกินระยะเวลาเก็บป้าย 7 วันแล้ว</span>
                      <br><br><strong>กรุณาดำเนินการถอดป้ายออกทันที</strong> มิฉะนั้นเทศบาลจะดำเนินการตามกฎหมายต่อไป";
        $btn_text = 'ดูรายละเอียด';
        $btn_url = 'http://localhost/Project2026/users/request_detail.php?id=' . $request_id;
    } else {
        $subject = "[เทศบาลเมืองศิลา] ใบอนุญาตป้าย #{$request_id} จะหมดอายุเร็วๆ นี้";
        $header_bg = '#f59e0b';
        $header_text = 'แจ้งเตือนใบอนุญาตใกล้หมดอายุ';
        $body_text = "ใบอนุญาตติดตั้งป้ายของท่าน <strong>เลขที่ #{$request_id}</strong> 
                      จะ<span style='color:#f59e0b;font-weight:bold;'>หมดอายุ</span>ในวันที่ 
                      <strong>{$expire_th}</strong>
                      <br><br>เมื่อหมดอายุแล้ว กรุณาดำเนินการถอดป้ายออกภายใน 7 วัน มิฉะนั้นอาจถูกดำเนินการตามกฎหมาย";
        $btn_text = 'ดูรายละเอียด';
        $btn_url = 'http://localhost/Project2026/users/request_detail.php?id=' . $request_id;
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
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>สถานที่ติดตั้ง:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;'>{$request['road_name']}</td></tr>
                    <tr><td style='padding:8px;border-bottom:1px solid #eee;color:#666;'>วันหมดอายุ:</td>
                        <td style='padding:8px;border-bottom:1px solid #eee;font-weight:bold;color:#dc3545;'>{$expire_th}</td></tr>
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
