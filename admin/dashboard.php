<?php
session_start();
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
// ตรวจสอบสิทธิ์ Admin หรือ Employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// ดึงข้อมูลผู้ดูแลระบบ
$user_id = $_SESSION['user_id'];
$admin_name = "ผู้ดูแลระบบ";
$sql_user = "SELECT title_name, first_name, last_name FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows === 1) {
    $user_data = $result_user->fetch_assoc();
    $admin_name = $user_data['title_name'] . ' ' . $user_data['first_name'] . " " . $user_data['last_name'];
}

// SQL Queries สำหรับสถิติภาพรวม
// 1. จำนวนผู้ใช้งานทั้งหมด (ไม่รวม admin)
$sql_users_count = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
$result_users = $conn->query($sql_users_count);
$total_users = $result_users->fetch_assoc()['total'];

// 2. คำขอรอตรวจสอบ (Pending)
$sql_pending = "SELECT COUNT(*) as total FROM sign_requests WHERE status = 'pending'";
$result_pending = $conn->query($sql_pending);
$pending_requests = $result_pending->fetch_assoc()['total'];

// 3. คำขอทั้งหมด
$sql_total_req = "SELECT COUNT(*) as total FROM sign_requests";
$result_total_req = $conn->query($sql_total_req);
$total_requests = $result_total_req->fetch_assoc()['total'];

// 4. คำขออนุมัติแล้ว
$sql_approved = "SELECT COUNT(*) as total FROM sign_requests WHERE status = 'approved'";
$result_approved = $conn->query($sql_approved);
$approved_requests = $result_approved->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - ผู้ดูแลระบบ</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-2">แผงควบคุมผู้ดูแลระบบ</h2>
        <p class="text-muted mb-4 fs-5">
            สวัสดีคุณ <span class="fw-bold text-primary">
                <?= htmlspecialchars($admin_name) ?>
            </span>
        </p>

        <h3 class="mt-4 mb-3">📊 ภาพรวมระบบ</h3>
        <div class="row">
            <div class="col-md-3">
                <div class="card dashboard-card bg-light-info hover-lift h-100">
                    <h6 class="text-nowrap">👥 ผู้ใช้งานทั้งหมด</h6>
                    <div class="count text-info">
                        <?= $total_users ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-warning hover-lift h-100">
                    <h6 class="text-nowrap">⏳ คำขอรอดำเนินการ</h6>
                    <div class="count text-warning">
                        <?= $pending_requests ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-primary hover-lift h-100">
                    <h6 class="text-nowrap">📄 คำขอทั้งหมด</h6>
                    <div class="count text-primary">
                        <?= $total_requests ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card dashboard-card bg-light-success hover-lift h-100">
                    <h6 class="text-nowrap">✅ อนุมัติแล้ว</h6>
                    <div class="count text-success">
                        <?= $approved_requests ?>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="mt-5 mb-3">⚙️ จัดการระบบ</h3>
        <div class="row">
            <?php if ($_SESSION['role'] === 'employee'): ?>
                <div class="col-md-4">
                    <a href="request_list.php" class="text-decoration-none">
                        <div class="card p-3 text-center shadow-sm h-100 hover-lift"
                            style="border-top: 4px solid var(--primary);">
                            <h5 class="mt-0 text-primary">📝 จัดการคำขอ</h5>
                            <p class="text-muted small mb-0">ตรวจสอบและอนุมัติคำขอติดตั้งป้าย</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

            <div class="col-md-4">
                <a href="users_list.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #0dcaf0;">
                        <h5 class="mt-0 text-info">👥 จัดการผู้ใช้งาน</h5>
                        <p class="text-muted small mb-0">ดูรายชื่อและจัดการสมาชิกในระบบ</p>
                    </div>
                </a>
            </div>

            <?php if ($_SESSION['role'] === 'employee'): ?>
                <div class="col-md-4">
                    <a href="../map.php" class="text-decoration-none">
                        <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #f59e0b;">
                            <h5 class="mt-0 text-warning">🗺️ แผนที่ภาพรวม</h5>
                            <p class="text-muted small mb-0">ดูตำแหน่งป้ายทั้งหมดบนแผนที่</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <?php include '../includes/scripts.php'; ?>
</body>

</html>