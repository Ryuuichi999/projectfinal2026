<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';

// ตรวจสอบสิทธิ์ Employee (หรือ Admin เผื่อไว้)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'employee' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit;
}

// ดึงข้อมูลเจ้าหน้าที่
$user_id = $_SESSION['user_id'];
$emp_name = "เจ้าหน้าที่";
$sql_user = "SELECT title_name, first_name, last_name FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows === 1) {
    $user_data = $result_user->fetch_assoc();
    $emp_name = $user_data['title_name'] . ' ' . $user_data['first_name'] . " " . $user_data['last_name'];
}

// ==== สถิติภาพรวม (ตัดส่วน Users ออก) ====
$pending_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'pending'")->fetch_assoc()['t'];
$reviewing_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'reviewing'")->fetch_assoc()['t'];
$waiting_docs_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'need_documents'")->fetch_assoc()['t'];
$total_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests")->fetch_assoc()['t'];
$approved_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'approved'")->fetch_assoc()['t'];
$rejected_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'rejected'")->fetch_assoc()['t'];
$waiting_payment = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'waiting_payment'")->fetch_assoc()['t'];
$waiting_permit = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'waiting_permit'")->fetch_assoc()['t'];
$expired_requests = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE status = 'expired'")->fetch_assoc()['t'];

// สถิติใบเสร็จและใบอนุญาตที่ออกแล้ว
$receipts_issued = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE receipt_no IS NOT NULL AND receipt_no != ''")->fetch_assoc()['t'];
$permits_issued = $conn->query("SELECT COUNT(*) as t FROM sign_requests WHERE permit_no IS NOT NULL AND permit_no != ''")->fetch_assoc()['t'];

// สถิติรายเดือน (6 เดือนล่าสุด)
$thaiMonthsShort = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $month_label = $thaiMonthsShort[(int)date('n', strtotime("-$i months"))] . ' ' . (date('Y', strtotime("-$i months")) + 543);

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

// ป้ายใกล้หมดอายุ (7 วัน)
$thai_months_short = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

$expiring_sql = "SELECT r.*, u.first_name, u.last_name,
    DATE_ADD(COALESCE(r.permit_date, r.created_at), INTERVAL r.duration_days DAY) as expire_date
    FROM sign_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    AND DATE_ADD(COALESCE(r.permit_date, r.created_at), INTERVAL r.duration_days DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY expire_date ASC";
$expiring_result = $conn->query($expiring_sql);

$expired_sql = "SELECT r.*, u.first_name, u.last_name,
    DATE_ADD(COALESCE(r.permit_date, r.created_at), INTERVAL r.duration_days DAY) as expire_date
    FROM sign_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved'
    AND DATE_ADD(COALESCE(r.permit_date, r.created_at), INTERVAL r.duration_days DAY) < CURDATE()
    ORDER BY expire_date DESC
    LIMIT 20";
$expired_result = $conn->query($expired_sql);

// คำร้องล่าสุด 5 รายการ
$sql_recent = "SELECT r.id, r.request_no, r.sign_type, r.status, r.created_at, u.first_name, u.last_name 
               FROM sign_requests r JOIN users u ON r.user_id = u.id 
               ORDER BY r.id DESC LIMIT 5";
$recent_result = $conn->query($sql_recent);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - เจ้าหน้าที่</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-card { background:white; border-radius:12px; padding:16px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,.06); transition:.3s; }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
        .stat-number { font-size:1.8rem; font-weight:700; }
        .stat-label { font-size:.82rem; color:#6c757d; }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-2">แผงควบคุมเจ้าหน้าที่</h2>
        <p class="text-muted mb-4 fs-5">
            สวัสดีคุณ <span class="fw-bold text-primary">
                <?= htmlspecialchars($emp_name) ?>
            </span>
        </p>

        <!-- ─── สถิติสรุป ─── -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #2848a7ff;"><div class="stat-number text-primary"><?= number_format($total_requests) ?></div><div class="stat-label">คำร้องทั้งหมด</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #ffb805ff;"><div class="stat-number text-warning"><?= number_format($pending_requests + $reviewing_requests) ?></div><div class="stat-label">รอดำเนินการ</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #17c7f3ff;"><div class="stat-number text-info"><?= number_format($waiting_payment) ?></div><div class="stat-label">รอชำระเงิน</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #29853fff;"><div class="stat-number text-success"><?= number_format($approved_requests) ?></div><div class="stat-label">อนุมัติแล้ว</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #ff0303ff;"><div class="stat-number text-danger"><?= number_format($rejected_requests) ?></div><div class="stat-label">ปฏิเสธ</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #28a745;"><div class="stat-number text-success"><?= number_format($receipts_issued) ?></div><div class="stat-label">ใบเสร็จที่ออก</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #17a2b8;"><div class="stat-number text-info"><?= number_format($permits_issued) ?></div><div class="stat-label">ใบอนุญาตที่ออก</div></div></div>
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
                            <th>เลขที่คำร้อง</th>
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
                                <td><strong><?= htmlspecialchars($r['request_no']) ?></strong></td>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= htmlspecialchars($r['sign_type']) ?></td>
                                <td><?= get_status_badge($r['status']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                <td>
                                    <a href="request_detail.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
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

        <!-- ===== ป้ายใกล้หมดอายุ ===== -->
        <?php if ($expiring_result && $expiring_result->num_rows > 0): ?>
            <div class="card shadow-sm p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-clock-fill text-warning me-2"></i>ป้ายใกล้หมดอายุ (7 วันข้างหน้า)
                    <span class="badge bg-warning text-dark"><?= $expiring_result->num_rows ?></span>
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>ผู้ขอ</th>
                                <th>ประเภท</th>
                                <th>เลขที่ใบอนุญาต</th>
                                <th>วันหมดอายุ</th>
                                <th>เหลือ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exp = $expiring_result->fetch_assoc()):
                                $days_left = ceil((strtotime($exp['expire_date']) - time()) / 86400);
                                $badge_class = $days_left <= 7 ? 'bg-danger' : 'bg-warning text-dark';
                                $exp_ts = strtotime($exp['expire_date']);
                            ?>
                                <tr>
                                    <td>#<?= $exp['id'] ?></td>
                                    <td><?= htmlspecialchars($exp['first_name'] . ' ' . $exp['last_name']) ?></td>
                                    <td><?= htmlspecialchars($exp['sign_type']) ?></td>
                                    <td><?= htmlspecialchars($exp['permit_no'] ?? '-') ?></td>
                                    <td><?= date('j', $exp_ts) . ' ' . $thai_months_short[(int)date('n', $exp_ts)] . ' ' . (date('Y', $exp_ts)+543) ?></td>
                                    <td><span class="badge <?= $badge_class ?>" style="font-size:0.75rem;"><?= $days_left ?> วัน</span></td>
                                    <td><a href="request_detail.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== ป้ายหมดอายุแล้ว ===== -->
        <?php if ($expired_result && $expired_result->num_rows > 0): ?>
            <div class="card shadow-sm p-4 mb-4" style="border-left: 4px solid #dc3545;">
                <h5 class="mb-3"><i class="bi bi-x-circle-fill text-danger me-2"></i>ป้ายหมดอายุแล้ว
                    <span class="badge bg-danger"><?= $expired_result->num_rows ?></span>
                </h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>ผู้ขอ</th>
                                <th>ประเภท</th>
                                <th>เลขที่ใบอนุญาต</th>
                                <th>วันหมดอายุ</th>
                                <th>หมดมาแล้ว</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exd = $expired_result->fetch_assoc()):
                                $days_over = abs(ceil((strtotime($exd['expire_date']) - time()) / 86400));
                                $exd_ts = strtotime($exd['expire_date']);
                            ?>
                                <tr>
                                    <td>#<?= $exd['id'] ?></td>
                                    <td><?= htmlspecialchars($exd['first_name'] . ' ' . $exd['last_name']) ?></td>
                                    <td><?= htmlspecialchars($exd['sign_type']) ?></td>
                                    <td><?= htmlspecialchars($exd['permit_no'] ?? '-') ?></td>
                                    <td><?= date('j', $exd_ts) . ' ' . $thai_months_short[(int)date('n', $exd_ts)] . ' ' . (date('Y', $exd_ts)+543) ?></td>
                                    <td><span class="badge bg-danger" style="font-size:0.75rem;"><?= $days_over ?> วัน</span></td>
                                    <td><a href="request_detail.php?id=<?= $exd['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-eye"></i></a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ===== ลิงก์จัดการ ===== -->
        <h4 class="mt-4 mb-3">⚙️ จัดการระบบ</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <a href="request_list.php" class="text-decoration-none">
                    <div class="card p-3 text-center shadow-sm h-100 hover-lift"
                        style="border-top: 4px solid var(--primary);">
                        <h5 class="mt-0 text-primary">📝 จัดการคำขอ</h5>
                        <p class="text-muted small mb-0">ตรวจสอบและอนุมัติคำขอติดตั้งป้าย</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
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
            'waiting_payment': 'รอชำระเงิน',
            'waiting_permit': 'รอออกใบอนุญาต',
            'approved': 'อนุมัติ',
            'rejected': 'ไม่อนุมัติ'
        };
        const statusColors = {
            'pending': '#f59e0b',
            'reviewing': '#3b82f6',
            'waiting_payment': '#ef4444',
            'waiting_permit': '#db2777',
            'approved': '#22c55e',
            'rejected': '#6b7280'
        };

        // กรองเฉพาะสถานะที่ต้องการแสดง
        const allowedStatuses = ['pending', 'reviewing', 'waiting_payment', 'waiting_permit', 'approved', 'rejected'];
        const filteredData = {};
        Object.keys(statusData).forEach(key => {
            if (allowedStatuses.includes(key)) {
                filteredData[key] = statusData[key];
            }
        });

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(filteredData).map(k => statusLabels[k] || k),
                datasets: [{
                    data: Object.values(filteredData),
                    backgroundColor: Object.keys(filteredData).map(k => statusColors[k] || '#999'),
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