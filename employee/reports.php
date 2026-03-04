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
// Build params for prepared statements
$params_types = ($month > 0) ? "ii" : "i";
$params_values = ($month > 0) ? [$year, $month] : [$year];
$where_clause = ($month > 0)
    ? "YEAR(r.created_at) = ? AND MONTH(r.created_at) = ?"
    : "YEAR(r.created_at) = ?";

// 1. สถิติรวม
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN r.status = 'waiting_payment' THEN 1 ELSE 0 END) as waiting_payment,
    SUM(CASE WHEN r.status = 'waiting_permit' THEN 1 ELSE 0 END) as waiting_permit,
    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN r.status = 'approved' THEN r.fee ELSE 0 END) as total_fee,
    SUM(r.fee) as estimated_fee
FROM sign_requests r WHERE $where_clause";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param($params_types, ...$params_values);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// 2. สรุปรายเดือน (ถ้าเลือกทั้งปี)
$monthly_data = [];
if ($month == 0) {
    $monthly_sql = "SELECT 
        MONTH(r.created_at) as m,
        COUNT(*) as total,
        SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN r.status = 'approved' THEN r.fee ELSE 0 END) as fee_collected
    FROM sign_requests r WHERE YEAR(r.created_at) = ? GROUP BY MONTH(r.created_at) ORDER BY m";
    $monthly_stmt = $conn->prepare($monthly_sql);
    $monthly_stmt->bind_param("i", $year);
    $monthly_stmt->execute();
    $monthly_result = $monthly_stmt->get_result();
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_data[$row['m']] = $row;
    }
}

// 3. สรุปตามประเภทป้าย
$type_sql = "SELECT sign_type, COUNT(*) as cnt, SUM(CASE WHEN status='approved' THEN fee ELSE 0 END) as fee_total
    FROM sign_requests r WHERE $where_clause GROUP BY sign_type ORDER BY cnt DESC";
$type_stmt = $conn->prepare($type_sql);
$type_stmt->bind_param($params_types, ...$params_values);
$type_stmt->execute();
$type_result = $type_stmt->get_result();

// 4. ใบเสร็จที่ออกแล้ว
$receipt_sql = "SELECT r.*, u.title_name, u.first_name, u.last_name, u.citizen_id, u.address as user_address
    FROM sign_requests r JOIN users u ON r.user_id = u.id
    WHERE r.receipt_no IS NOT NULL AND r.receipt_no != '' AND $where_clause
    ORDER BY r.receipt_date ASC, r.id ASC";
$receipt_stmt = $conn->prepare($receipt_sql);
$receipt_stmt->bind_param($params_types, ...$params_values);
$receipt_stmt->execute();
$receipt_result = $receipt_stmt->get_result();

// 5. ใบอนุญาตที่ออกแล้ว
$permit_sql = "SELECT r.*, u.title_name, u.first_name, u.last_name,
    r.end_date as expire_date
    FROM sign_requests r JOIN users u ON r.user_id = u.id
    WHERE r.permit_no IS NOT NULL AND r.permit_no != '' AND $where_clause
    ORDER BY r.permit_date ASC, r.id ASC";
$permit_stmt = $conn->prepare($permit_sql);
$permit_stmt->bind_param($params_types, ...$params_values);
$permit_stmt->execute();
$permit_result = $permit_stmt->get_result();

// 6. คำร้องทั้งหมด
$all_sql = "SELECT r.*, u.title_name, u.first_name, u.last_name
    FROM sign_requests r JOIN users u ON r.user_id = u.id
    WHERE $where_clause ORDER BY r.id ASC";
$all_stmt = $conn->prepare($all_sql);
$all_stmt->bind_param($params_types, ...$params_values);
$all_stmt->execute();
$all_result = $all_stmt->get_result();

// Thai month names
$thai_months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thai_months_full = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

// Active tab
$tab = $_GET['tab'] ?? 'summary';

// ─── EXPORT CSV ───
$csv_safe = function ($val) {
    $val = (string) $val;
    if (isset($val[0]) && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
        $val = "'" . $val;
    }
    return $val;
};

if (isset($_GET['export'])) {
    $export_tab = $_GET['export'];
    $suffix = $year . ($month ? '_' . $month : '');
    header('Content-Type: text/csv; charset=utf-8');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    if ($export_tab === 'revenue') {
        header('Content-Disposition: attachment; filename="revenue_' . $suffix . '.csv"');
        fputcsv($out, ['เดือน', 'คำร้องทั้งหมด', 'อนุมัติ', 'ค่าธรรมเนียม (บาท)']);
        $year_total_fee = 0;
        for ($mi = 1; $mi <= 12; $mi++) {
            $d = $monthly_data[$mi] ?? ['total' => 0, 'approved_count' => 0, 'fee_collected' => 0];
            $year_total_fee += $d['fee_collected'];
            fputcsv($out, [$thai_months_full[$mi], $d['total'], $d['approved_count'], number_format($d['fee_collected'])]);
        }
        fputcsv($out, ['รวมทั้งปี', '', '', number_format($year_total_fee)]);

    } elseif ($export_tab === 'receipts') {
        header('Content-Disposition: attachment; filename="receipts_' . $suffix . '.csv"');
        fputcsv($out, ['#', 'เลขที่ใบเสร็จ', 'วันที่ออก', 'ชื่อ-นามสกุล', 'ประเภทป้าย', 'ค่าธรรมเนียม (บาท)']);
        $ex_sql = "SELECT r.*, u.title_name, u.first_name, u.last_name FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE r.receipt_no IS NOT NULL AND r.receipt_no != '' AND $where_clause ORDER BY r.id";
        $ex_stmt = $conn->prepare($ex_sql);
        $ex_stmt->bind_param($params_types, ...$params_values);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        $n = 1;
        while ($row = $ex_res->fetch_assoc()) {
            fputcsv($out, [$n++, $csv_safe($row['receipt_no']), $csv_safe($row['receipt_date'] ? date('d/m/Y', strtotime($row['receipt_date'])) : '-'), $csv_safe($row['title_name'] . $row['first_name'] . ' ' . $row['last_name']), $csv_safe($row['sign_type']), number_format($row['fee'])]);
        }

    } elseif ($export_tab === 'permits') {
        header('Content-Disposition: attachment; filename="permits_' . $suffix . '.csv"');
        fputcsv($out, ['#', 'เลขที่ใบอนุญาต', 'วันที่ออก', 'ชื่อ-นามสกุล', 'ประเภทป้าย', 'ขนาด', 'ค่าธรรมเนียม', 'วันหมดอายุ']);
        $ex_sql = "SELECT r.*, u.title_name, u.first_name, u.last_name, r.end_date as expire_date FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE r.permit_no IS NOT NULL AND r.permit_no != '' AND $where_clause ORDER BY r.id";
        $ex_stmt = $conn->prepare($ex_sql);
        $ex_stmt->bind_param($params_types, ...$params_values);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        $n = 1;
        while ($row = $ex_res->fetch_assoc()) {
            fputcsv($out, [$n++, $csv_safe($row['permit_no']), $csv_safe($row['permit_date'] ? date('d/m/Y', strtotime($row['permit_date'])) : '-'), $csv_safe($row['title_name'] . $row['first_name'] . ' ' . $row['last_name']), $csv_safe($row['sign_type']), $csv_safe($row['width'] . 'x' . $row['height'] . ' ม.'), number_format($row['fee']), $csv_safe($row['expire_date'] ? date('d/m/Y', strtotime($row['expire_date'])) : '-')]);
        }

    } else {
        header('Content-Disposition: attachment; filename="requests_' . $suffix . '.csv"');
        fputcsv($out, ['#', 'วันที่ยื่น', 'ผู้ยื่น', 'ประเภทป้าย', 'ขนาด', 'ค่าธรรมเนียม', 'สถานะ', 'เลขที่ใบอนุญาต']);
        $ex_sql = "SELECT r.*, u.first_name, u.last_name FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE $where_clause ORDER BY r.id";
        $ex_stmt = $conn->prepare($ex_sql);
        $ex_stmt->bind_param($params_types, ...$params_values);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        $n = 1;
        while ($row = $ex_res->fetch_assoc()) {
            fputcsv($out, [$n++, $csv_safe(date('d/m/Y', strtotime($row['created_at']))), $csv_safe($row['first_name'] . ' ' . $row['last_name']), $csv_safe($row['sign_type']), $csv_safe($row['width'] . 'x' . $row['height'] . ' ม.'), number_format($row['fee']), $csv_safe($row['status']), $csv_safe($row['permit_no'] ?? '-')]);
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงาน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <style>
        .stat-card { background:white; border-radius:12px; padding:16px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,.06); transition:.3s; }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,.12); }
        .stat-number { font-size:1.8rem; font-weight:700; }
        .stat-label { font-size:.82rem; color:#6c757d; }
        .filter-bar { background:white; border-radius:12px; padding:15px 20px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px; }
        .chart-container { position:relative; height:300px; }
        .nav-tabs .nav-link { font-weight:600; color:#6c757d; }
        .nav-tabs .nav-link.active { color:#1a56db; border-bottom:3px solid #1a56db; }
        .tab-export-btn { font-size:.8rem; }
        .revenue-table th, .revenue-table td { text-align:right; }
        .revenue-table th:first-child, .revenue-table td:first-child { text-align:left; }
        .revenue-total { background:#f0fdf4; font-weight:700; }
        .print-header { display:none; }
        .pg-controls { display:flex; justify-content:start; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
        .pg-controls select { width:auto; display:inline-block; font-size:.82rem; }
        .pg-nav { display:flex; gap:4px; }
        .pg-nav button { border:1px solid #dee2e6; background:#fff; border-radius:6px; padding:4px 10px; font-size:.82rem; cursor:pointer; color:#333; }
        .pg-nav button:hover:not(:disabled) { background:#e9ecef; }
        .pg-nav button:disabled { opacity:.4; cursor:default; }
        .pg-nav button.active { background:#1a56db; color:#fff; border-color:#1a56db; }
        @media print {
            .no-print, .sidebar, .topbar, .nav-tabs, .stat-card, .row.g-3.mb-4, h2.mb-4,
            .filter-bar, .sidebar-overlay, .pg-controls, .pg-nav-bottom { display:none !important; }
            body { background:#fff !important; margin:0; padding:0; font-size:11pt; }
            .content { margin:0 !important; padding:0 !important; width:100% !important; }
            .card { border:none !important; box-shadow:none !important; padding:0 !important; }
            .print-header { display:block !important; text-align:center; margin-bottom:10px; padding-top:10px; }
            .print-header img { width:70px; height:70px; margin-bottom:5px; }
            .print-header h3 { font-size:16pt; margin:0; font-weight:700; }
            .print-header h4 { font-size:13pt; margin:4px 0 10px; font-weight:400; }
            .print-btn-area { display:none !important; }
            table tbody tr { display:table-row !important; }
            table { font-size:10pt !important; border-collapse:collapse !important; width:100% !important; }
            table th, table td { border:1px solid #333 !important; padding:4px 6px !important; }
            table thead th { background:#e9ecef !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .table-success { background:#d4edda !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            @page { size:landscape; margin:10mm; }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-4">📊 รายงาน</h2>

        <!-- ─── ตัวกรอง + แท็บ ─── -->
        <div class="filter-bar no-print">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <form method="GET" class="d-flex align-items-center gap-2 mb-0">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <label class="form-label small fw-bold mb-0">ปี</label>
                    <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <?php foreach ($available_years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small fw-bold mb-0">เดือน</label>
                    <select name="month" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="0" <?= $month == 0 ? 'selected' : '' ?>>ทั้งปี</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $thai_months_full[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <ul class="nav nav-tabs border-0 mb-0" role="tablist" style="gap:5px;">
                    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'summary' ? 'active' : '' ?>" href="?year=<?= $year ?>&month=<?= $month ?>&tab=summary"><i class="bi bi-graph-up me-1"></i>สรุปภาพรวม</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'revenue' ? 'active' : '' ?>" href="?year=<?= $year ?>&month=<?= $month ?>&tab=revenue"><i class="bi bi-cash-stack me-1"></i>รายได้</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'receipts' ? 'active' : '' ?>" href="?year=<?= $year ?>&month=<?= $month ?>&tab=receipts"><i class="bi bi-receipt me-1"></i>ใบเสร็จ</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'permits' ? 'active' : '' ?>" href="?year=<?= $year ?>&month=<?= $month ?>&tab=permits"><i class="bi bi-file-earmark-check me-1"></i>ใบอนุญาต</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'requests' ? 'active' : '' ?>" href="?year=<?= $year ?>&month=<?= $month ?>&tab=requests"><i class="bi bi-list-ul me-1"></i>คำร้องทั้งหมด</a></li>
                </ul>
            </div>
        </div>

        <!-- ─── สถิติสรุป ─── -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #2848a7ff;"><div class="stat-number text-primary"><?= number_format($stats['total']) ?></div><div class="stat-label">คำร้องทั้งหมด</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #ffb805ff;"><div class="stat-number text-warning"><?= number_format($stats['pending']) ?></div><div class="stat-label">รอดำเนินการ</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #17c7f3ff;"><div class="stat-number text-info"><?= number_format($stats['waiting_payment']) ?></div><div class="stat-label">รอชำระเงิน</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #29853fff;"><div class="stat-number text-success"><?= number_format($stats['approved']) ?></div><div class="stat-label">อนุมัติแล้ว</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #ff0303ff;"><div class="stat-number text-danger"><?= number_format($stats['rejected']) ?></div><div class="stat-label">ปฏิเสธ</div></div></div>
            <div class="col-md-2 col-6"><div class="stat-card" style="border-top:3px solid #28a745;"><div class="stat-number text-success"><?= number_format($stats['total_fee']) ?></div><div class="stat-label">ค่าธรรมเนียมรวม</div></div></div>
        </div>

        <!-- ═══════ TAB: สรุปภาพรวม ═══════ -->
        <?php if ($tab === 'summary'): ?>
        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
                <div class="card p-2 text-center">
                    <div class="text-muted small">ใบเสร็จที่ออก</div>
                    <div class="fw-bold fs-5 text-info"><?= number_format($receipt_result->num_rows) ?> <span class="fw-normal text-muted" style="font-size:.75rem">ฉบับ</span></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card p-2 text-center">
                    <div class="text-muted small">ใบอนุญาตที่ออก</div>
                    <div class="fw-bold fs-5 text-success"><?= number_format($permit_result->num_rows) ?> <span class="fw-normal text-muted" style="font-size:.75rem">ฉบับ</span></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <?php if ($month == 0): ?>
                <div class="col-md-8">
                    <div class="card p-4"><h5 class="mb-3">📈 คำร้องรายเดือน (ปี <?= $year + 543 ?>)</h5><div class="chart-container"><canvas id="monthlyChart"></canvas></div></div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4"><h5 class="mb-3">📋 ตามประเภทป้าย</h5><div class="chart-container"><canvas id="typeChart"></canvas></div></div>
                </div>
            <?php else: ?>
                <div class="col-md-6">
                    <div class="card p-4"><h5 class="mb-3">📋 ตามประเภทป้าย</h5><div class="chart-container"><canvas id="typeChart"></canvas></div></div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4"><h5 class="mb-3">📊 สัดส่วนสถานะ</h5><div class="chart-container"><canvas id="statusChart"></canvas></div></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ตารางใต้กราฟ -->
        <?php $type_result->data_seek(0); ?>
        <div class="row g-4 mt-2">
            <?php if ($month == 0): ?>
            <div class="col-md-12">
                <div class="card p-4">
                    <h5 class="mb-3">📅 คำร้องรายเดือน ปี <?= $year + 543 ?></h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0 pg-table">
                            <thead class="table-light">
                                <tr><th>เดือน</th><th class="text-center">คำร้อง</th><th class="text-center">อนุมัติ</th><th class="text-end">ค่าธรรมเนียม (บาท)</th></tr>
                            </thead>
                            <tbody>
                                <?php $sum_total = 0; $sum_approved = 0; $sum_fee = 0;
                                for ($mi = 1; $mi <= 12; $mi++):
                                    $d = $monthly_data[$mi] ?? ['total' => 0, 'approved_count' => 0, 'fee_collected' => 0];
                                    $sum_total += $d['total']; $sum_approved += $d['approved_count']; $sum_fee += $d['fee_collected'];
                                ?>
                                <tr>
                                    <td><?= $thai_months_full[$mi] ?></td>
                                    <td class="text-center"><?= $d['total'] ?></td>
                                    <td class="text-center"><?= $d['approved_count'] ?></td>
                                    <td class="text-end"><?= number_format($d['fee_collected'], 2) ?></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary fw-bold">
                                    <td>รวม</td>
                                    <td class="text-center"><?= number_format($sum_total) ?></td>
                                    <td class="text-center"><?= number_format($sum_approved) ?></td>
                                    <td class="text-end"><?= number_format($sum_fee, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-12">
                <div class="card p-4">
                    <h5 class="mb-3">📋 สรุปตามประเภทป้าย</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0 pg-table">
                            <thead class="table-light">
                                <tr><th>ประเภทป้าย</th><th class="text-center">จำนวน (รายการ)</th><th class="text-end">ค่าธรรมเนียมรวม (บาท)</th></tr>
                            </thead>
                            <tbody>
                                <?php while ($tp = $type_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tp['sign_type']) ?></td>
                                    <td class="text-center"><?= number_format($tp['cnt']) ?></td>
                                    <td class="text-end"><?= number_format($tp['fee_total'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ TAB: รายได้ ═══════ -->
        <?php elseif ($tab === 'revenue'): ?>
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-cash-stack text-success me-2"></i>รายงานรายได้ค่าธรรมเนียม ปี <?= $year + 543 ?><?= $month > 0 ? ' ' . $thai_months_full[$month] : '' ?></h5>
                <a href="?year=<?= $year ?>&month=<?= $month ?>&export=revenue" class="btn btn-outline-success btn-sm tab-export-btn no-print" target="_blank"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
            </div>
            <?php if ($month == 0): ?>
            <h6 class="fw-bold mb-3">📅 สรุปรายได้รายเดือน ปี <?= $year + 543 ?></h6>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle revenue-table">
                    <thead class="table-light">
                        <tr><th>เดือน</th><th>คำร้องทั้งหมด</th><th>อนุมัติ</th><th>ค่าธรรมเนียม (บาท)</th></tr>
                    </thead>
                    <tbody>
                        <?php $year_total = 0; for ($mi = 1; $mi <= 12; $mi++):
                            $d = $monthly_data[$mi] ?? ['total' => 0, 'approved_count' => 0, 'fee_collected' => 0];
                            $year_total += $d['fee_collected'];
                        ?>
                        <tr>
                            <td><?= $thai_months_full[$mi] ?></td>
                            <td><?= number_format($d['total']) ?></td>
                            <td><?= number_format($d['approved_count']) ?></td>
                            <td><?= number_format($d['fee_collected'], 2) ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr class="revenue-total"><td>รวมทั้งปี <?= $year + 543 ?></td><td></td><td></td><td><?= number_format($year_total, 2) ?></td></tr>
                    </tfoot>
                </table>
            </div>
            <h6 class="fw-bold mt-4 mb-3">📈 กราฟรายได้รายเดือน ปี <?= $year + 543 ?></h6>
            <div style="width:100%;"><canvas id="revenueChart"></canvas></div>
            <?php else: ?>
            <div class="text-center py-4">
                <div class="display-4 text-success fw-bold"><?= number_format($stats['total_fee'], 2) ?> บาท</div>
                <p class="text-muted mt-2">ค่าธรรมเนียมรวมเดือน<?= $thai_months_full[$month] ?> <?= $year + 543 ?></p>
                <p>จากคำร้องที่อนุมัติ <?= number_format($stats['approved']) ?> รายการ</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- ═══════ TAB: ใบเสร็จ ═══════ -->
        <?php elseif ($tab === 'receipts'): ?>
        <div class="card p-4">
            <div class="print-header">
                <img src="../image/logosila.png" alt="ตราเทศบาล">
                <h3>เทศบาลเมืองศิลา</h3>
                <h4>รายงานการออกใบเสร็จ <?= $month > 0 ? $thai_months_full[$month] : '' ?> <?= $year + 543 ?> <?= $month == 0 ? 'ทั้งหมด' : '' ?></h4>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h5 class="mb-0"><i class="bi bi-receipt text-primary me-2"></i>รายงานการออกใบเสร็จ <?= $month > 0 ? $thai_months_full[$month] : '' ?> <?= $year + 543 ?></h5>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm tab-export-btn"><i class="bi bi-printer me-1"></i>พิมพ์รายงาน</button>
                    <a href="?year=<?= $year ?>&month=<?= $month ?>&export=receipts" class="btn btn-outline-success btn-sm tab-export-btn" target="_blank"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover align-middle pg-table">
                    <thead class="table-light">
                        <tr><th>ลำดับ</th><th>เลขที่ใบเสร็จ</th><th>วันที่ออก</th><th>ชื่อ-นามสกุล</th><th>ประเภทป้าย</th><th>ขนาด</th><th class="text-end">ค่าธรรมเนียม (บาท)</th></tr>
                    </thead>
                    <tbody>
                        <?php $rn = 1; $receipt_fee_total = 0; while ($rc = $receipt_result->fetch_assoc()): $receipt_fee_total += $rc['fee']; ?>
                        <tr>
                            <td><?= $rn++ ?></td>
                            <td><strong><?= htmlspecialchars($rc['receipt_no']) ?></strong></td>
                            <td><?= $rc['receipt_date'] ? date('d/m/', strtotime($rc['receipt_date'])) . (date('Y', strtotime($rc['receipt_date']))+543) : '-' ?></td>
                            <td><?= htmlspecialchars($rc['title_name'] . $rc['first_name'] . ' ' . $rc['last_name']) ?></td>
                            <td><?= htmlspecialchars($rc['sign_type']) ?></td>
                            <td><?= $rc['width'] ?>x<?= $rc['height'] ?> ม.</td>
                            <td class="text-end"><?= number_format($rc['fee'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($rn === 1): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">ไม่มีข้อมูลใบเสร็จในช่วงเวลาที่เลือก</td></tr>
                        <?php else: ?>
                        <tr class="table-success fw-bold"><td colspan="6" class="text-end">รวม</td><td class="text-end"><?= number_format($receipt_fee_total, 2) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════ TAB: ใบอนุญาต ═══════ -->
        <?php elseif ($tab === 'permits'): ?>
        <div class="card p-4">
            <div class="print-header">
                <img src="../image/logosila.png" alt="ตราเทศบาล">
                <h3>เทศบาลเมืองศิลา</h3>
                <h4>รายงานใบอนุญาตที่ออก <?= $month > 0 ? $thai_months_full[$month] : '' ?> <?= $year + 543 ?> <?= $month == 0 ? 'ทั้งหมด' : '' ?></h4>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check text-success me-2"></i>รายงานใบอนุญาตที่ออก <?= $month > 0 ? $thai_months_full[$month] : '' ?> <?= $year + 543 ?></h5>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm tab-export-btn"><i class="bi bi-printer me-1"></i>พิมพ์รายงาน</button>
                    <a href="?year=<?= $year ?>&month=<?= $month ?>&export=permits" class="btn btn-outline-success btn-sm tab-export-btn" target="_blank"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover align-middle pg-table">
                    <thead class="table-light">
                        <tr><th>ลำดับ</th><th>เลขที่ใบอนุญาต</th><th>วันที่ออก</th><th>ผู้ได้รับอนุญาต</th><th>ประเภทป้าย</th><th>ขนาด</th><th class="text-end">ค่าธรรมเนียม</th><th>วันหมดอายุ</th></tr>
                    </thead>
                    <tbody>
                        <?php $pn = 1; while ($pm = $permit_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $pn++ ?></td>
                            <td><strong><?= htmlspecialchars($pm['permit_no']) ?></strong></td>
                            <td><?= $pm['permit_date'] ? date('d/m/', strtotime($pm['permit_date'])) . (date('Y', strtotime($pm['permit_date']))+543) : '-' ?></td>
                            <td><?= htmlspecialchars($pm['title_name'] . $pm['first_name'] . ' ' . $pm['last_name']) ?></td>
                            <td><?= htmlspecialchars($pm['sign_type']) ?></td>
                            <td><?= $pm['width'] ?>x<?= $pm['height'] ?> ม.</td>
                            <td class="text-end"><?= number_format($pm['fee'], 2) ?></td>
                            <td><?php $pe_ts = strtotime($pm['expire_date']); echo date('d/m/', $pe_ts) . (date('Y', $pe_ts)+543); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($pn === 1): ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">ไม่มีข้อมูลใบอนุญาตในช่วงเวลาที่เลือก</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════ TAB: คำร้องทั้งหมด ═══════ -->
        <?php elseif ($tab === 'requests'): ?>
        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-list-ul text-primary me-2"></i>คำร้องทั้งหมด <?= $month > 0 ? $thai_months_full[$month] : '' ?> <?= $year + 543 ?></h5>
                <a href="?year=<?= $year ?>&month=<?= $month ?>&export=requests" class="btn btn-outline-success btn-sm tab-export-btn no-print" target="_blank"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm table-hover align-middle pg-table" style="table-layout:fixed;width:100%;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:5%;">ลำดับ</th>
                            <th style="width:12%;">เลขคำร้อง</th>
                            <th style="width:13%;">วันที่ยื่น</th>
                            <th style="width:17%;">ผู้ยื่น</th>
                            <th style="width:10%;">ประเภทป้าย</th>
                            <th style="width:8%;">ขนาด</th>
                            <th style="width:10%;" class="text-end">ค่าธรรมเนียม</th>
                            <th style="width:14%;">สถานะ</th>
                            <th style="width:12%;">เลขที่ใบอนุญาต</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $an = 1; while ($ar = $all_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $an++ ?></td>
                            <td><?= htmlspecialchars($ar['request_no'] ?: '#'.$ar['id']) ?></td>
                            <td><?= date('d/m/', strtotime($ar['created_at'])) . (date('Y', strtotime($ar['created_at']))+543) ?></td>
                            <td><?= htmlspecialchars($ar['title_name'] . $ar['first_name'] . ' ' . $ar['last_name']) ?></td>
                            <td><?= htmlspecialchars($ar['sign_type']) ?></td>
                            <td><?= $ar['width'] ?>x<?= $ar['height'] ?> ม.</td>
                            <td class="text-end"><?= number_format($ar['fee'], 2) ?></td>
                            <td><?= get_status_badge($ar['status']) ?></td>
                            <td><?= htmlspecialchars($ar['permit_no'] ?? '-') ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($an === 1): ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">ไม่มีข้อมูลคำร้องในช่วงเวลาที่เลือก</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
    <?php
    // Prepare type chart data
    $type_labels = [];
    $type_counts = [];
    $type_result->data_seek(0);
    while ($t = $type_result->fetch_assoc()) {
        $type_labels[] = $t['sign_type'];
        $type_counts[] = $t['cnt'];
    }
    ?>

    <?php if ($tab === 'summary'): ?>
        <?php if ($month == 0): ?>
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: [<?php for ($m = 1; $m <= 12; $m++) echo "'" . $thai_months[$m] . "',"; ?>],
                datasets: [{
                    label: 'คำร้องทั้งหมด',
                    data: [<?php for ($m = 1; $m <= 12; $m++) echo ($monthly_data[$m]['total'] ?? 0) . ','; ?>],
                    backgroundColor: 'rgba(54,162,235,0.7)', borderRadius: 6
                },{
                    label: 'อนุมัติ',
                    data: [<?php for ($m = 1; $m <= 12; $m++) echo ($monthly_data[$m]['approved_count'] ?? 0) . ','; ?>],
                    backgroundColor: 'rgba(75,192,192,0.7)', borderRadius: 6
                }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true, ticks:{stepSize:1}}} }
        });
        <?php endif; ?>

        new Chart(document.getElementById('typeChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($type_labels) ?>,
                datasets: [{ data: <?= json_encode($type_counts) ?>, backgroundColor: ['#4dc9f6','#f67019','#f53794','#537bc4','#acc236','#166a8f','#00a950','#58595b','#8549ba'] }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom', labels:{font:{size:11}}}} }
        });

        <?php if ($month > 0): ?>
        new Chart(document.getElementById('statusChart'), {
            type: 'pie',
            data: {
                labels: ['รอดำเนินการ','รอชำระเงิน','อนุมัติ','ปฏิเสธ'],
                datasets: [{ data: [<?= $stats['pending'] ?>,<?= $stats['waiting_payment'] ?>,<?= $stats['approved'] ?>,<?= $stats['rejected'] ?>], backgroundColor: ['#ffc107','#17a2b8','#28a745','#dc3545'] }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
        });
        <?php endif; ?>

    <?php elseif ($tab === 'revenue' && $month == 0): ?>
        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: [<?php for ($m = 1; $m <= 12; $m++) echo "'" . $thai_months[$m] . "',"; ?>],
                datasets: [{
                    label: 'ค่าธรรมเนียม (บาท)',
                    data: [<?php for ($m = 1; $m <= 12; $m++) echo ($monthly_data[$m]['fee_collected'] ?? 0) . ','; ?>],
                    backgroundColor: 'rgba(40,167,69,0.7)', borderRadius: 6
                }]
            },
            options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
        });
    <?php endif; ?>
    // ─── Pagination ───
   document.querySelectorAll('.pg-table').forEach(function(table) {

    var tbody = table.querySelector('tbody');
    if (!tbody) return;

    var allRows = Array.from(tbody.querySelectorAll('tr'));

    // แยกแถวข้อมูลออกจากแถวรวม/ไม่มีข้อมูล
    var dataRows = allRows.filter(function(r) {
        return !r.classList.contains('table-success') &&
               !r.querySelector('.text-muted.py-3');
    });

    var totalRow = allRows.find(function(r) {
        return r.classList.contains('table-success');
    });

    if (dataRows.length <= 10) return;

    var perPage = 10;
    var currentPage = 1;
    var totalPages = Math.ceil(dataRows.length / perPage);

    // สร้าง nav ด้านล่าง (ไม่มี pg-status แล้ว)
    var navBottom = document.createElement('div');
    navBottom.className = 'pg-nav-bottom d-flex justify-content-between align-items-center mt-2 no-print';

    navBottom.innerHTML =
        '<div class="d-flex align-items-center gap-2">' +
            '<span style="font-size:.85rem;">แสดง</span>' +
            '<select class="form-select form-select-sm pg-per-page" style="width:80px;">' +
                '<option value="10">10</option>' +
                '<option value="25">25</option>' +
                '<option value="50">50</option>' +
                '<option value="0">ทั้งหมด</option>' +
            '</select>' +
            '<span style="font-size:.85rem;">รายการ</span>' +
        '</div>' +
        '<div class="pg-nav"></div>';

    table.closest('.table-responsive').after(navBottom);

    var navDiv = navBottom.querySelector('.pg-nav');

    function render() {

        totalPages = perPage === 0 ? 1 : Math.ceil(dataRows.length / perPage);

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var start = perPage === 0 ? 0 : (currentPage - 1) * perPage;
        var end   = perPage === 0 ? dataRows.length : start + perPage;

        dataRows.forEach(function(r, i) {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        if (totalRow) totalRow.style.display = '';

        // สร้างปุ่มเปลี่ยนหน้า
        var html = '<button class="pg-prev">&laquo;</button>';

        var maxBtn = 5;
        var half = Math.floor(maxBtn / 2);

        var s = Math.max(1, currentPage - half);
        var e = Math.min(totalPages, s + maxBtn - 1);

        if (e - s < maxBtn - 1) {
            s = Math.max(1, e - maxBtn + 1);
        }

        for (var p = s; p <= e; p++) {
            html += '<button class="pg-page' + (p === currentPage ? ' active' : '') +
                    '" data-p="' + p + '">' + p + '</button>';
        }

        html += '<button class="pg-next">&raquo;</button>';

        navDiv.innerHTML = html;

        navDiv.querySelector('.pg-prev').disabled = currentPage <= 1;
        navDiv.querySelector('.pg-next').disabled = currentPage >= totalPages;

        if (totalPages <= 1) {
            navBottom.style.display = 'none';
        } else {
            navBottom.style.display = '';
        }
    }

    // เปลี่ยนจำนวนต่อหน้า
    navBottom.querySelector('.pg-per-page').addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        render();
    });

    // กดปุ่มเปลี่ยนหน้า
    navDiv.addEventListener('click', function(ev) {

        var btn = ev.target.closest('button');
        if (!btn || btn.disabled) return;

        if (btn.classList.contains('pg-prev')) {
            currentPage--;
        } else if (btn.classList.contains('pg-next')) {
            currentPage++;
        } else if (btn.dataset.p) {
            currentPage = parseInt(btn.dataset.p);
        }

        render();
    });

    render();
});
    </script>
</body>

</html>