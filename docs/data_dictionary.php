<?php
require '../includes/db.php';

// คำอธิบายคอลัมน์ทุกตาราง (ใส่มือ 1 ครั้ง)
$descriptions = [
    'users' => [
        '_label' => 'ผู้ใช้งาน',
        'id' => 'รหัสผู้ใช้',
        'citizen_id' => 'เลขบัตรประชาชน',
        'password' => 'รหัสผ่าน (เข้ารหัส bcrypt)',
        'title_name' => 'คำนำหน้าชื่อ',
        'first_name' => 'ชื่อจริง',
        'last_name' => 'นามสกุล',
        'email' => 'อีเมล',
        'phone' => 'เบอร์โทรศัพท์',
        'address' => 'ที่อยู่',
        'role' => 'บทบาทผู้ใช้ (user / employee / admin)',
        'line_user_id' => 'รหัสผู้ใช้ LINE',
        'profile_image' => 'รูปโปรไฟล์',
        'created_at' => 'วันเวลาที่สร้างบัญชี',
    ],
    'sign_requests' => [
        '_label' => 'คำร้องขออนุญาตติดตั้งป้าย',
        'id' => 'รหัสคำร้อง',
        'request_no' => 'เลขที่คำร้อง',
        'user_id' => 'รหัสผู้ยื่นคำร้อง (FK → users.id)',
        'applicant_name' => 'ชื่อผู้ขออนุญาต',
        'applicant_address' => 'ที่อยู่ผู้ขออนุญาต',
        'email' => 'อีเมลติดต่อ',
        'sign_type' => 'ประเภทป้าย',
        'width' => 'ความกว้าง (เมตร)',
        'height' => 'ความสูง (เมตร)',
        'quantity' => 'จำนวนป้าย',
        'road_name' => 'ชื่อถนน/สถานที่ติดตั้ง',
        'location_lat' => 'ละติจูด (พิกัด GIS)',
        'location_lng' => 'ลองจิจูด (พิกัด GIS)',
        'fee' => 'ค่าธรรมเนียม (บาท)',
        'duration_days' => 'ระยะเวลาอนุญาต (วัน)',
        'install_date' => 'วันที่ติดตั้งป้าย',
        'end_date' => 'วันที่หมดอายุ',
        'sign_purpose' => 'วัตถุประสงค์ป้าย',
        'description' => 'รายละเอียดข้อความบนป้าย',
        'status' => 'สถานะคำร้อง',
        'decision_note' => 'หมายเหตุการพิจารณา',
        'approved_by' => 'รหัสผู้อนุมัติ (FK → users.id)',
        'permit_no' => 'เลขที่ใบอนุญาต',
        'permit_date' => 'วันที่ออกใบอนุญาต',
        'receipt_no' => 'เลขที่ใบเสร็จ',
        'receipt_date' => 'วันที่ออกใบเสร็จ',
        'created_at' => 'วันเวลาที่ยื่นคำร้อง',
        'receipt_issued_by' => 'ชื่อผู้ออกใบเสร็จ',
        'permit_signer_name' => 'ชื่อผู้ลงนามใบอนุญาต',
        'permit_signer_position' => 'ตำแหน่งผู้ลงนามใบอนุญาต',
        'receipt_downloaded_at' => 'วันเวลาที่ดาวน์โหลดใบเสร็จ',
    ],
    'sign_documents' => [
        '_label' => 'เอกสารประกอบคำร้อง',
        'id' => 'รหัสเอกสาร',
        'request_id' => 'รหัสคำร้อง (FK → sign_requests.id)',
        'doc_type' => 'ประเภทเอกสาร',
        'file_path' => 'ที่อยู่ไฟล์ในระบบ',
        'trans_ref' => 'รหัสอ้างอิงการชำระเงิน',
        'uploaded_at' => 'วันเวลาที่อัปโหลด',
    ],
    'request_logs' => [
        '_label' => 'บันทึกการดำเนินการคำร้อง',
        'id' => 'รหัสบันทึก',
        'request_id' => 'รหัสคำร้อง (FK → sign_requests.id)',
        'action' => 'รหัสการดำเนินการ',
        'action_label' => 'คำอธิบายการดำเนินการ',
        'actor_id' => 'รหัสผู้ดำเนินการ (FK → users.id)',
        'note' => 'หมายเหตุเพิ่มเติม',
        'created_at' => 'วันเวลาที่บันทึก',
    ],
    'audit_logs' => [
        '_label' => 'บันทึกการใช้งานระบบ (Audit Log)',
        'id' => 'รหัสบันทึก',
        'user_id' => 'รหัสผู้ใช้ (FK → users.id)',
        'action' => 'ประเภทการกระทำ',
        'target_table' => 'ตารางเป้าหมาย',
        'target_id' => 'รหัสข้อมูลเป้าหมาย',
        'details' => 'รายละเอียดเพิ่มเติม',
        'ip_address' => 'IP Address ผู้ใช้',
        'created_at' => 'วันเวลาที่บันทึก',
    ],
    'feedback' => [
        '_label' => 'ความคิดเห็น/ประเมินความพึงพอใจ',
        'id' => 'รหัสความคิดเห็น',
        'user_id' => 'รหัสผู้ใช้ (FK → users.id)',
        'request_id' => 'รหัสคำร้อง (FK → sign_requests.id)',
        'rating' => 'คะแนนความพึงพอใจ (1-5 ดาว)',
        'comment' => 'ข้อเสนอแนะ',
        'created_at' => 'วันเวลาที่ประเมิน',
    ],
    'password_resets' => [
        '_label' => 'รีเซ็ตรหัสผ่าน',
        'id' => 'รหัสรายการ',
        'citizen_id' => 'เลขบัตรประชาชน (FK → users.citizen_id)',
        'otp' => 'รหัส OTP',
        'expires_at' => 'วันเวลาหมดอายุ OTP',
        'used' => 'สถานะการใช้งาน (0=ยังไม่ใช้, 1=ใช้แล้ว)',
        'created_at' => 'วันเวลาที่ขอรีเซ็ต',
    ],
    'system_settings' => [
        '_label' => 'ตั้งค่าระบบ',
        'id' => 'รหัสการตั้งค่า',
        'setting_key' => 'ชื่อคีย์การตั้งค่า',
        'setting_value' => 'ค่าของการตั้งค่า',
        'updated_at' => 'วันเวลาที่อัปเดตล่าสุด',
    ],
];

// ตัวอย่างข้อมูล
$examples = [
    'users' => [
        'id' => '1', 'citizen_id' => '1234567890123', 'password' => '$2y$10$xN8rK...',
        'title_name' => 'นาย', 'first_name' => 'รัชชานนท์', 'last_name' => 'อินกันหา',
        'email' => 'user@email.com', 'phone' => '0812345678',
        'address' => '123 ต.ศิลา อ.เมือง จ.ขอนแก่น',
        'role' => 'user', 'line_user_id' => 'U1a2b3c4d...', 'profile_image' => 'uploads/profile/1.jpg',
        'created_at' => '2025-03-01 10:30:00',
    ],
    'sign_requests' => [
        'id' => '1', 'request_no' => 'REQ-2503-00001', 'user_id' => '1',
        'applicant_name' => 'นาย รัชชานนท์ อินกันหา',
        'applicant_address' => '99 ต.สาวะถี อ.เมือง จ.ขอนแก่น',
        'email' => 'user@email.com', 'sign_type' => 'ไวนิล',
        'width' => '1.20', 'height' => '2.30', 'quantity' => '1',
        'road_name' => 'ถนนมิตรภาพ', 'location_lat' => '16.4321', 'location_lng' => '102.8236',
        'fee' => '200.00', 'duration_days' => '30',
        'install_date' => '2025-03-15', 'end_date' => '2025-04-14',
        'sign_purpose' => 'non_commercial', 'description' => 'โปรโมชั่นสินค้าลดราคา',
        'status' => 'approved', 'decision_note' => 'อนุมัติตามระเบียบ', 'approved_by' => '2',
        'permit_no' => '00001/69', 'permit_date' => '2025-03-10',
        'receipt_no' => 'RCPT-00001/69', 'receipt_date' => '2025-03-07',
        'created_at' => '2025-03-01 14:20:00',
        'receipt_issued_by' => 'นางสาว สมหญิง รักดี',
        'permit_signer_name' => 'นาย สมชาย นายกฯ',
        'permit_signer_position' => 'นายกเทศมนตรีเมืองศิลา',
        'receipt_downloaded_at' => '2025-03-07 16:43:00',
    ],
    'sign_documents' => [
        'id' => '1', 'request_id' => '1', 'doc_type' => 'รูปป้าย',
        'file_path' => 'uploads/docs/sign_1_abc.jpg', 'trans_ref' => '202503071430REF001',
        'uploaded_at' => '2025-03-01 14:25:00',
    ],
    'request_logs' => [
        'id' => '1', 'request_id' => '1', 'action' => 'approved',
        'action_label' => 'อนุมัติคำร้อง', 'actor_id' => '2',
        'note' => 'ตรวจสอบเอกสารครบถ้วน', 'created_at' => '2025-03-05 09:15:00',
    ],
    'audit_logs' => [
        'id' => '1', 'user_id' => '2', 'action' => 'login',
        'target_table' => 'sign_requests', 'target_id' => '5',
        'details' => 'เข้าสู่ระบบสำเร็จ', 'ip_address' => '192.168.1.100',
        'created_at' => '2025-03-05 09:15:00',
    ],
    'feedback' => [
        'id' => '1', 'user_id' => '1', 'request_id' => '1',
        'rating' => '5', 'comment' => 'ระบบใช้งานง่าย สะดวกมาก',
        'created_at' => '2025-03-20 11:00:00',
    ],
    'password_resets' => [
        'id' => '1', 'citizen_id' => '1234567890123', 'otp' => '482917',
        'expires_at' => '2025-03-01 10:45:00', 'used' => '0',
        'created_at' => '2025-03-01 10:30:00',
    ],
    'system_settings' => [
        'id' => '1', 'setting_key' => 'receipt_signer_name',
        'setting_value' => 'นางสาว สมหญิง รักดี', 'updated_at' => '2025-03-01 08:00:00',
    ],
];

// ดึง schema จาก DB จริง
$tables_order = array_keys($descriptions);
$schema = [];

$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_KEY, IS_NULLABLE, COLUMN_TYPE, COLUMN_DEFAULT, EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME, ORDINAL_POSITION";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $schema[$row['TABLE_NAME']][] = $row;
}

// ฟังก์ชันแยก type กับ length
function parseType($colType) {
    if (preg_match('/^(\w+)\((.+)\)/', $colType, $m)) {
        return [strtoupper($m[1]), $m[2]];
    }
    if (strpos($colType, 'enum') === 0) return ['ENUM', '-'];
    return [strtoupper($colType), '-'];
}

function getKey($col) {
    if ($col['COLUMN_KEY'] === 'PRI') return 'PK';
    if ($col['COLUMN_KEY'] === 'UNI') return 'UNI';
    if ($col['COLUMN_KEY'] === 'MUL') return 'FK';
    return '';
}

function getConstraint($col) {
    $c = [];
    if ($col['IS_NULLABLE'] === 'NO' && $col['COLUMN_KEY'] !== 'PRI') $c[] = 'NOT NULL';
    if ($col['COLUMN_KEY'] === 'PRI') $c[] = 'NOT NULL';
    if (strpos($col['EXTRA'], 'auto_increment') !== false) $c[] = 'AUTO_INCREMENT';
    return implode(', ', $c);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Data Dictionary — ระบบขออนุญาตติดตั้งป้ายโฆษณา</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: #fff;
            color: #000;
            font-size: 11pt;
            padding: 15mm;
        }
        h1 {
            text-align: center;
            font-size: 18pt;
            font-weight: 700;
            margin-bottom: 25px;
        }
        .table-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .table-title {
            font-size: 13pt;
            font-weight: 700;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px 8px;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }
        td:nth-child(1) { font-family: 'Courier New', monospace; font-size: 9.5pt; }
        td:nth-child(3), td:nth-child(4) { text-align: center; font-size: 9.5pt; }
        td:nth-child(5) { text-align: center; font-family: 'Courier New', monospace; font-size: 9.5pt; }
        td:nth-child(6) { text-align: center; }
        td:nth-child(7) { font-size: 9pt; word-break: break-all; max-width: 200px; }

        .no-print {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .no-print button {
            padding: 10px 25px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            color: white;
            font-family: 'Sarabun', sans-serif;
            margin: 0 5px;
        }
        .btn-print { background: #007bff; }
        .btn-pdf { background: #28a745; }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .table-section { page-break-inside: avoid; }
        }

        @page {
            size: A4;
            margin: 15mm;
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨 พิมพ์ / บันทึก PDF (Ctrl+P)</button>
    <p style="margin-top:8px; color:#666; font-size:13px;">กด Ctrl+P แล้วเลือก "Save as PDF" เพื่อบันทึกเป็นไฟล์ PDF</p>
</div>

<h1>Data Dictionary</h1>

<?php
$num = 0;
foreach ($tables_order as $table):
    if (!isset($schema[$table])) continue;
    $num++;
    $label = $descriptions[$table]['_label'] ?? $table;
    $cols = $schema[$table];
    $ex = $examples[$table] ?? [];
?>

<div class="table-section">
    <div class="table-title"><?= $num ?>. <?= $table ?> (<?= $label ?>)</div>
    <table>
        <thead>
            <tr>
                <th>ชื่อคอลัมน์</th>
                <th>คำอธิบาย</th>
                <th>คีย์</th>
                <th>ข้อจำกัด</th>
                <th>ชนิดข้อมูล</th>
                <th>ความยาว</th>
                <th>ตัวอย่าง</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cols as $col):
            $colName = $col['COLUMN_NAME'];
            list($type, $length) = parseType($col['COLUMN_TYPE']);
            $desc = $descriptions[$table][$colName] ?? $colName;
            $key = getKey($col);
            $constraint = getConstraint($col);
            $example = $ex[$colName] ?? '';
        ?>
            <tr>
                <td><?= htmlspecialchars($colName) ?></td>
                <td><?= htmlspecialchars($desc) ?></td>
                <td><?= $key ?></td>
                <td><?= htmlspecialchars($constraint) ?></td>
                <td><?= htmlspecialchars($type) ?></td>
                <td><?= htmlspecialchars($length) ?></td>
                <td><?= htmlspecialchars($example) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endforeach; ?>

</body>
</html>
