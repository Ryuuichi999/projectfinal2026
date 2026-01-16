<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

// *** 1. ดึงข้อมูลชื่อผู้ใช้งานจากฐานข้อมูล ***
$user_id = $_SESSION['user_id'];
$user_name = 'ผู้ใช้งาน'; // ค่าเริ่มต้น

$sql_user = "SELECT title_name, first_name, last_name FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($result_user->num_rows === 1) {
    $user_data = $result_user->fetch_assoc();
    $user_name = $user_data['title_name'] . ' ' . $user_data['first_name'] . " " . $user_data['last_name'];
}
// *** สิ้นสุดส่วนดึงข้อมูลชื่อ ***

// *** ส่วน PHP: ดึงข้อมูลสถิติของผู้ใช้งานคนปัจจุบัน (โค้ดเดิม) ***
// ... (ส่วนการดึงสถิติคำขอ: $total_requests, $pending_review, etc. ที่ยังเป็น 0 อยู่)
// *** ส่วน PHP: ดึงข้อมูลสถิติของผู้ใช้งานคนปัจจุบัน (Update) ***
$sql_stats = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'waiting_payment' THEN 1 ELSE 0 END) as waiting,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
              FROM sign_requests 
              WHERE user_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();

$total_requests = $stats['total'] ?? 0;
$pending_review = $stats['pending'] ?? 0;
$awaiting_payment = $stats['waiting'] ?? 0;
$approved = $stats['approved'] ?? 0;
// *** สิ้นสุดส่วน PHP (Update) ***
// *** สิ้นสุดส่วน PHP ***
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - ผู้ใช้งาน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-2">ภาพรวมคำขอติดตั้งป้ายชั่วคราว</h2>
        <p class="text-muted mb-1 fs-5">
        ยินดีต้อนรับคุณ <span class="fw-bold text-primary"><?= htmlspecialchars($user_name) ?></span>
    </p>
    
    <p class="text-muted mb-4 small">
        โปรดตรวจสอบสถานะคำขอของคุณด้านล่างเพื่อดำเนินการต่อไป
    </p>

        <h3 class="mt-4 mb-3">📈 สรุปสถานะคำขอของฉัน</h3>
        <div class="row">
            <div class="col-md-3">
                <div class="card dashboard-card bg-light-primary hover-lift">
                    <h6>📄 คำขอทั้งหมด</h6>
                    <div class="count text-primary"><?= $total_requests ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-warning hover-lift">
                    <h6>⏳ รอกำลังพิจารณา</h6>
                    <div class="count text-warning"><?= $pending_review ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-danger hover-lift">
                    <h6>⚠️ รอชำระเงิน</h6>
                    <div class="count text-danger"><?= $awaiting_payment ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-success hover-lift">
                    <h6>✅ อนุมัติแล้ว</h6>
                    <div class="count text-success"><?= $approved ?></div>
                </div>
            </div>
        </div>

        <h3 class="mt-5 mb-3">🚀 ทางลัดการดำเนินการ</h3>
        <div class="row">
            <div class="col-md-4">
                <a href="../request_form.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid var(--primary);">
                        <h5 class="mt-0 text-primary">📝 ยื่นคำขอใหม่</h5>
                        <p class="text-muted small mb-0">เริ่มกรอกแบบฟอร์มขออนุญาตติดตั้งป้าย</p>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="../my_request.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #10b981;">
                        <h5 class="mt-0 text-success">📄 ตรวจสอบสถานะ</h5>
                        <p class="text-muted small mb-0">ดูรายละเอียดและความคืบหน้าของคำขอทั้งหมด</p>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="../map.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #f59e0b;">
                        <h5 class="mt-0 text-warning">🗺️ ข้อมูลพื้นที่ (GIS)</h5>
                        <p class="text-muted small mb-0">ตรวจสอบขอบเขตและตำแหน่งถนนในเขต ทม.ศิลา</p>
                    </div>
                </a>
            </div>
        </div>

    </div>
    <?php include '../includes/scripts.php'; ?>
</body>

</html>