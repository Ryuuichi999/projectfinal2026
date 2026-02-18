<?php
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin/Employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// ดึงข้อมูลคำขอทั้งหมด
$sql = "SELECT r.id, r.sign_type, r.width, r.height, r.quantity, r.fee, r.status, r.created_at,
               r.applicant_name, r.email, r.road_name,
               u.title_name, u.first_name, u.last_name, u.citizen_id, u.phone
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.id ASC";
$result = $conn->query($sql);

// สร้าง CSV
$filename = "requests_export_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM สำหรับ Excel รองรับภาษาไทย
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header row
fputcsv($output, [
    'รหัสคำร้อง',
    'ชื่อผู้ยื่น',
    'เลขบัตรประชาชน',
    'เบอร์โทร',
    'อีเมล',
    'ประเภทป้าย',
    'กว้าง(ม.)',
    'สูง(ม.)',
    'จำนวน',
    'ค่าธรรมเนียม',
    'สถานะ',
    'สถานที่',
    'วันที่ยื่น'
]);

$status_labels = [
    'pending' => 'รอพิจารณา',
    'reviewing' => 'กำลังพิจารณา',
    'need_documents' => 'ขอเอกสารเพิ่ม',
    'waiting_payment' => 'รอชำระเงิน',
    'waiting_receipt' => 'รอออกใบเสร็จ',
    'approved' => 'อนุมัติแล้ว',
    'rejected' => 'ไม่อนุมัติ'
];

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['title_name'] . ' ' . $row['first_name'] . ' ' . $row['last_name'],
        $row['citizen_id'],
        $row['phone'],
        $row['email'] ?? '',
        $row['sign_type'],
        $row['width'],
        $row['height'],
        $row['quantity'],
        $row['fee'],
        $status_labels[$row['status']] ?? $row['status'],
        $row['road_name'] ?? '',
        date('d/m/Y H:i', strtotime($row['created_at']))
    ]);
}

fclose($output);
exit;
