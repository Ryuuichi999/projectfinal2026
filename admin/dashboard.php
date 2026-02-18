<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';

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

// ==== สถิติภาพรวม ====
$total_users = $conn->query("SELECT COUNT(*) as t FROM users WHERE role != 'admin'")->fetch_assoc()['t'];
$pending_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'pending'")->fetch_assoc()['t'];
$total_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests")->fetch_assoc()['t'];
$approved_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'approved'")->fetch_assoc()['t'];
$rejected_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'rejected'")->fetch_assoc()['t'];
$waiting_payment = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'waiting_payment'")->fetch_assoc()['t'];

// สถิติรายเดือน (6 เดือนล่าสุด)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));

    $sql_m = "SELECT COUNT(*) as c FROM sign_requests WHERE created_at BETWEEN ? AND ?";
    $stmt_m = $conn->prepare($sql_m);
    $end_full = $month_end . ' 23:59:59';
    $stmt_m->bind_param("ss", $month_start, $end_full);
    $stmt_m->execute();
    $count_m = $stmt_m->get_result()->fetch_assoc()['c'];
    $monthly_data[] = ['label' => $month_label, 'count' => (int) $count_m];
}

// สถิติตามสถานะ (สำหรับ Doughnut Chart)
$status_counts = [];
$status_query = $conn->query("SELECT status, COUNT(*) as c FROM sign_requests GROUP BY status");
while ($s = $status_query->fetch_assoc()) {
    $status_counts[$s['status']] = (int) $s['c'];
}

// คำร้องล่าสุด 5 รายการ
$sql_recent = "SELECT r.id, r.sign_type, r.status, r.created_at, u.first_name, u.last_name 
               FROM sign_requests r JOIN users u ON r.user_id = u.id 
               ORDER BY r.id DESC LIMIT 5";
$recent_result = $conn->query($sql_recent);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - ผู้ดูแลระบบ</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-2">แผงควบคุมผู้ดูแลระบบ</h2>
        <p class="text-muted mb-4 fs-5">
            สวัสดีคุณ <span class="fw-bold text-primary">
                <?= htmlspecialchars($admin_name) ?>
            </span>
        </p>

        <!-- ===== สถิติการ์ด 6 ช่อง ===== -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card bg-light-info hover-lift h-100">
                    <h6 class="text-nowrap">👥 ผู้ใช้งานทั้งหมด</h6>
                    <div class="count text-info"><?= $total_users ?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card bg-light-warning hover-lift h-100">
                    <h6 class="text-nowrap">⏳ รอดำเนินการ</h6>
                    <div class="count text-warning"><?= $pending_requests ?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card bg-light-primary hover-lift h-100">
                    <h6 class="text-nowrap">📄 คำขอทั้งหมด</h6>
                    <div class="count text-primary"><?= $total_requests ?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card bg-light-success hover-lift h-100">
                    <h6 class="text-nowrap">✅ อนุมัติแล้ว</h6>
                    <div class="count text-success"><?= $approved_requests ?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card hover-lift h-100" style="background: #fef2f2;">
                    <h6 class="text-nowrap">❌ ไม่อนุมัติ</h6>
                    <div class="count text-danger"><?= $rejected_requests ?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card hover-lift h-100" style="background: #fff7ed;">
                    <h6 class="text-nowrap">💰 รอชำระเงิน</h6>
                    <div class="count" style="color: #ea580c;"><?= $waiting_payment ?></div>
                </div>
            </div>
        </div>

        <!-- ===== กราฟ ===== -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm p-4 h-100">
                    <h5 class="mb-3">📈 คำร้องรายเดือน (6 เดือนล่าสุด)</h5>
                    <canvas id="monthlyChart" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm p-4 h-100">
                    <h5 class="mb-3">📊 สัดส่วนสถานะ</h5>
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- ===== คำร้องล่าสุด ===== -->
        <div class="card shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">🕐 คำร้องล่าสุด</h5>
                <a href="request_list.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>ผู้ยื่น</th>
                            <th>ประเภทป้าย</th>
                            <th>สถานะ</th>
                            <th>วันที่ยื่น</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = $recent_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $r['id'] ?></strong></td>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= htmlspecialchars($r['sign_type']) ?></td>
                                <td><?= get_status_badge($r['status']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <a href="../employee/request_detail.php?id=<?= $r['id'] ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($recent_result->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">ยังไม่มีคำร้อง</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== ลิงก์จัดการ ===== -->
        <h4 class="mt-4 mb-3">⚙️ จัดการระบบ</h4>
        <div class="row g-3">
            <div class="col-md-4">
                <a href="request_list.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift"
                        style="border-top: 4px solid var(--primary);">
                        <h5 class="mt-0 text-primary">📝 จัดการคำขอ</h5>
                        <p class="text-muted small mb-0">ตรวจสอบและอนุมัติคำขอติดตั้งป้าย</p>
                    </div>
                </a>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="col-md-4">
                    <a href="users_list.php" class="text-decoration-none">
                        <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #0dcaf0;">
                            <h5 class="mt-0 text-info">👥 จัดการผู้ใช้งาน</h5>
                            <p class="text-muted small mb-0">ดูรายชื่อและจัดการสมาชิกในระบบ</p>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
            <div class="col-md-4">
                <a href="../map.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift" style="border-top: 4px solid #f59e0b;">
                        <h5 class="mt-0 text-warning">🗺️ แผนที่ภาพรวม</h5>
                        <p class="text-muted small mb-0">ดูตำแหน่งป้ายทั้งหมดบนแผนที่</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>

    <script>
        // Bar Chart — คำร้องรายเดือน
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'label')) ?>,
                datasets: [{
                    label: 'จำนวนคำร้อง',
                    data: <?= json_encode(array_column($monthly_data, 'count')) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // Doughnut Chart — สัดส่วนสถานะ
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?= json_encode($status_counts) ?>;
        const statusLabels = {
            'pending': 'รอพิจารณา',
            'reviewing': 'กำลังพิจารณา',
            'need_documents': 'ขอเอกสารเพิ่ม',
            'waiting_payment': 'รอชำระเงิน',
            'waiting_receipt': 'รอออกใบเสร็จ',
            'approved': 'อนุมัติ',
            'rejected': 'ไม่อนุมัติ'
        };
        const statusColors = {
            'pending': '#f59e0b',
            'reviewing': '#3b82f6',
            'need_documents': '#06b6d4',
            'waiting_payment': '#ef4444',
            'waiting_receipt': '#8b5cf6',
            'approved': '#22c55e',
            'rejected': '#6b7280'
        };

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(statusData).map(k => statusLabels[k] || k),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: Object.keys(statusData).map(k => statusColors[k] || '#999'),
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 8, font: { size: 11 } }
                    }
                }
            }
        });
    </script>
</body>

</html>