<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// ─── ตัวกรอง ───
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : 0; // 0 = ทั้งปี
$report_type = $_GET['type'] ?? 'summary'; // summary, fee, status, expiring

// ดึงปีที่มีข้อมูล
$years_result = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM sign_requests ORDER BY y DESC");
$available_years = [];
while ($yr = $years_result->fetch_assoc()) {
    $available_years[] = $yr['y'];
}
if (empty($available_years))
    $available_years[] = date('Y');

// ─── ข้อมูลสรุป ───
$where_date = "YEAR(r.created_at) = $year";
if ($month > 0)
    $where_date .= " AND MONTH(r.created_at) = $month";

// 1. สถิติรวม
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN r.status = 'waiting_payment' THEN 1 ELSE 0 END) as waiting_payment,
    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN r.status = 'approved' THEN r.fee ELSE 0 END) as total_fee,
    SUM(r.fee) as estimated_fee
FROM sign_requests r WHERE $where_date";
$stats = $conn->query($stats_sql)->fetch_assoc();

// 2. สรุปรายเดือน (ถ้าเลือกทั้งปี)
$monthly_data = [];
if ($month == 0) {
    $monthly_sql = "SELECT 
        MONTH(r.created_at) as m,
        COUNT(*) as total,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN r.status = 'approved' THEN r.fee ELSE 0 END) as fee_collected
    FROM sign_requests r WHERE YEAR(r.created_at) = $year GROUP BY MONTH(r.created_at) ORDER BY m";
    $monthly_result = $conn->query($monthly_sql);
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_data[$row['m']] = $row;
    }
}

// 3. สรุปตามประเภทป้าย
$type_sql = "SELECT sign_type, COUNT(*) as cnt, SUM(CASE WHEN status='approved' THEN fee ELSE 0 END) as fee_total
    FROM sign_requests r WHERE $where_date GROUP BY sign_type ORDER BY cnt DESC";
$type_result = $conn->query($type_sql);

// 4. ป้ายใกล้หมดอายุ (30 วัน)
$expiring_sql = "SELECT r.*, u.first_name, u.last_name,
    DATE_ADD(r.permit_date, INTERVAL r.duration_days DAY) as expire_date
    FROM sign_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'approved' AND r.permit_date IS NOT NULL
    AND DATE_ADD(r.permit_date, INTERVAL r.duration_days DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY expire_date ASC";
$expiring_result = $conn->query($expiring_sql);

// Thai month names
$thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thai_months_full = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

// ─── EXPORT ───
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $year . ($month ? '_' . $month : '') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM for UTF-8

    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'วันที่ยื่น', 'ผู้ยื่น', 'ประเภทป้าย', 'ขนาด', 'ค่าธรรมเนียม', 'สถานะ', 'เลขที่ใบอนุญาต']);

    $export_sql = "SELECT r.*, u.first_name, u.last_name FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE $where_date ORDER BY r.id";
    $export_result = $conn->query($export_sql);
    $n = 1;
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($out, [
            $n++,
            date('d/m/Y', strtotime($row['created_at'])),
            $row['first_name'] . ' ' . $row['last_name'],
            $row['sign_type'],
            $row['width'] . 'x' . $row['height'] . ' ม.',
            number_format($row['fee']),
            $row['status'],
            $row['permit_no'] ?? '-'
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงาน -
        <?= $year + 543 ?>
    </title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .expiring-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .expiring-danger {
            background: #ffe5e5;
            color: #dc3545;
        }

        .expiring-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">📊 รายงานสรุป</h2>
            <a href="?year=<?= $year ?>&month=<?= $month ?>&export=csv" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
        </div>

        <!-- ─── ตัวกรอง ─── -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-bold">ปี</label>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($available_years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                <?= $y + 543 ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold">เดือน</label>
                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0" <?= $month == 0 ? 'selected' : '' ?>>ทั้งปี</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                <?= $thai_months_full[$m] ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- ─── สถิติสรุป ─── -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-number text-primary">
                        <?= number_format($stats['total']) ?>
                    </div>
                    <div class="stat-label">คำร้องทั้งหมด</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-number text-warning">
                        <?= number_format($stats['pending']) ?>
                    </div>
                    <div class="stat-label">รอดำเนินการ</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-number text-info">
                        <?= number_format($stats['waiting_payment']) ?>
                    </div>
                    <div class="stat-label">รอชำระเงิน</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-number text-success">
                        <?= number_format($stats['approved']) ?>
                    </div>
                    <div class="stat-label">อนุมัติแล้ว</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-number text-danger">
                        <?= number_format($stats['rejected']) ?>
                    </div>
                    <div class="stat-label">ปฏิเสธ</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card" style="border-top: 3px solid #28a745;">
                    <div class="stat-number text-success">
                        <?= number_format($stats['total_fee']) ?>
                    </div>
                    <div class="stat-label">ค่าธรรมเนียมรวม (บาท)</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- ─── กราฟรายเดือน ─── -->
            <?php if ($month == 0): ?>
                <div class="col-md-8">
                    <div class="card p-4">
                        <h5 class="mb-3">📈 คำร้องรายเดือน (ปี
                            <?= $year + 543 ?>)
                        </h5>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ─── ตามประเภทป้าย ─── -->
            <div class="col-md-<?= $month == 0 ? '4' : '6' ?>">
                <div class="card p-4">
                    <h5 class="mb-3">📋 ตามประเภทป้าย</h5>
                    <div class="chart-container">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>

            <?php if ($month > 0): ?>
                <div class="col-md-6">
                    <div class="card p-4">
                        <h5 class="mb-3">📊 สัดส่วนสถานะ</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ─── ป้ายใกล้หมดอายุ ─── -->
        <?php if ($expiring_result->num_rows > 0): ?>
            <div class="card p-4 mt-4">
                <h5 class="mb-3">⏰ ป้ายใกล้หมดอายุ (30 วันข้างหน้า)
                    <span class="badge bg-danger">
                        <?= $expiring_result->num_rows ?>
                    </span>
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>ผู้ขอ</th>
                                <th>ประเภท</th>
                                <th>เลขที่ใบอนุญาต</th>
                                <th>วันหมดอายุ</th>
                                <th>เหลือ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exp = $expiring_result->fetch_assoc()):
                                $days_left = ceil((strtotime($exp['expire_date']) - time()) / 86400);
                                $badge_class = $days_left <= 7 ? 'expiring-danger' : 'expiring-warning';
                                ?>
                                <tr>
                                    <td>#
                                        <?= $exp['id'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($exp['first_name'] . ' ' . $exp['last_name']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($exp['sign_type']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($exp['permit_no']) ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($exp['expire_date'])) ?>
                                    </td>
                                    <td><span class="expiring-badge <?= $badge_class ?>">
                                            <?= $days_left ?> วัน
                                        </span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
        // Monthly Chart
        <?php if ($month == 0): ?>
            const monthlyLabels = [<?php for ($m = 1; $m <= 12; $m++)
                echo "'" . $thai_months[$m] . "',"; ?>];
            const monthlyTotal = [<?php for ($m = 1; $m <= 12; $m++)
                echo ($monthly_data[$m]['total'] ?? 0) . ','; ?>];
            const monthlyApproved = [<?php for ($m = 1; $m <= 12; $m++)
                echo ($monthly_data[$m]['approved_count'] ?? 0) . ','; ?>];
            const monthlyFee = [<?php for ($m = 1; $m <= 12; $m++)
                echo ($monthly_data[$m]['fee_collected'] ?? 0) . ','; ?>];

            new Chart(document.getElementById('monthlyChart'), {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'คำร้องทั้งหมด',
                        data: monthlyTotal,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderRadius: 6
                    },
                    {
                        label: 'อนุมัติ',
                        data: monthlyApproved,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderRadius: 6
                    }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Type Pie Chart
        <?php
        $type_labels = [];
        $type_counts = [];
        $type_result->data_seek(0);
        while ($t = $type_result->fetch_assoc()) {
            $type_labels[] = $t['sign_type'];
            $type_counts[] = $t['cnt'];
        }
        ?>
        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($type_labels) ?>,
                datasets: [{
                    data: <?= json_encode($type_counts) ?>,
                    backgroundColor: [
                        '#4dc9f6', '#f67019', '#f53794', '#537bc4', '#acc236',
                        '#166a8f', '#00a950', '#58595b', '#8549ba'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Status Pie (if month view)
        <?php if ($month > 0): ?>
            new Chart(document.getElementById('statusChart'), {
                type: 'pie',
                data: {
                    labels: ['รอดำเนินการ', 'รอชำระเงิน', 'อนุมัติ', 'ปฏิเสธ'],
                    datasets: [{
                        data: [
                            <?= $stats['pending'] ?>,
                            <?= $stats['waiting_payment'] ?>,
                            <?= $stats['approved'] ?>,
                            <?= $stats['rejected'] ?>
                        ],
                        backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>