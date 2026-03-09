<?php
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// ==== สถิติผู้ใช้งาน ====
$total_all = $conn->query("SELECT COUNT(*) as t FROM users")->fetch_assoc()['t'];
$total_users = $conn->query("SELECT COUNT(*) as t FROM users WHERE role = 'user'")->fetch_assoc()['t'];
$total_employees = $conn->query("SELECT COUNT(*) as t FROM users WHERE role = 'employee'")->fetch_assoc()['t'];
$total_admins = $conn->query("SELECT COUNT(*) as t FROM users WHERE role = 'admin'")->fetch_assoc()['t'];

// ผู้ใช้ล่าสุด 10 รายการ
$sql_recent = "SELECT id, title_name, first_name, last_name, citizen_id, phone, role, created_at 
               FROM users ORDER BY id DESC LIMIT 10";
$recent_result = $conn->query($sql_recent);

function get_role_badge_admin($role)
{
    switch ($role) {
        case 'admin':
            return '<span class="badge bg-danger">ผู้ดูแลระบบ</span>';
        case 'employee':
            return '<span class="badge bg-primary">เจ้าหน้าที่</span>';
        default:
            return '<span class="badge bg-success">ผู้ใช้งาน</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - ผู้ดูแลระบบ</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-2">ผู้ดูแลระบบ</h2>
        <p class="text-muted mb-4 fs-5">
            สวัสดีคุณ <span class="fw-bold text-primary">
                <?= htmlspecialchars($admin_name) ?>
            </span>
        </p>

        <!-- ===== สถิติผู้ใช้งาน ===== -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card dashboard-card bg-light-info hover-lift h-100">
                    <h6 class="text-nowrap">👥 ทั้งหมด</h6>
                    <div class="count text-info"><?= $total_all ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card dashboard-card bg-light-success hover-lift h-100">
                    <h6 class="text-nowrap">🟢 ผู้ใช้งาน</h6>
                    <div class="count text-success"><?= $total_users ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card dashboard-card bg-light-primary hover-lift h-100">
                    <h6 class="text-nowrap">🟣 เจ้าหน้าที่</h6>
                    <div class="count text-primary"><?= $total_employees ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card dashboard-card hover-lift h-100" style="background: #fef2f2;">
                    <h6 class="text-nowrap">🔴 ผู้ดูแลระบบ</h6>
                    <div class="count text-danger"><?= $total_admins ?></div>
                </div>
            </div>
        </div>

        <!-- ===== ผู้ใช้ล่าสุด ===== -->
        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">👥 ผู้ใช้ล่าสุด</h5>
                <div class="d-flex gap-2">
                    <a href="add_user.php" class="btn btn-sm btn-success">
                        <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้
                    </a>
                    <a href="users_list.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด →</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เลขบัตรประชาชน</th>
                            <th>เบอร์โทร</th>
                            <th>บทบาท</th>
                            <th>วันที่สมัคร</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = $recent_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['title_name'] . ' ' . $r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= htmlspecialchars($r['citizen_id']) ?></td>
                                <td><?= htmlspecialchars($r['phone'] ?? '-') ?></td>
                                <td><?= get_role_badge_admin($r['role']) ?></td>
                                <td><?= date('d/m/Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($recent_result->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">ยังไม่มีผู้ใช้</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Toast.fire({ icon: 'success', title: <?= json_encode($_SESSION['flash_success']) ?> });
            });
        </script>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
</body>

</html>