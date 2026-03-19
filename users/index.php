<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Fetch User Data
$stmtUser = $conn->prepare("SELECT title_name, first_name, last_name FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();
$fullName = ($userData['title_name'] ?? '') . ' ' . ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');

// 2. Fetch Stats
$stats = [
    'pending' => 0,
    'reviewing' => 0,
    'waiting_payment' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total' => 0
];

$sqlStats = "SELECT status, COUNT(*) as count FROM sign_requests WHERE user_id = ? GROUP BY status";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $user_id);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
while ($row = $resultStats->fetch_assoc()) {
    $stats['total'] += $row['count'];
    if (isset($stats[$row['status']])) {
        $stats[$row['status']] = $row['count'];
    }
}

// 3. Fetch Recent Requests (all for pagination)
$recentRequests = [];
$sqlRecent = "SELECT id, request_no, status, sign_type, width, height, created_at, road_name 
               FROM sign_requests 
               WHERE user_id = ? 
               ORDER BY created_at DESC";
$stmtRecent = $conn->prepare($sqlRecent);
$stmtRecent->bind_param("i", $user_id);
$stmtRecent->execute();
$resultRecent = $stmtRecent->get_result();
while ($row = $resultRecent->fetch_assoc()) {
    $recentRequests[] = $row;
}

// 4. ป้ายใกล้หมดอายุของผู้ใช้
$expiring_sql = "SELECT id, request_no, sign_type, permit_no, road_name,
    end_date as expire_date
    FROM sign_requests
    WHERE user_id = ? AND status = 'approved'
    AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY end_date ASC";
$stmtExp = $conn->prepare($expiring_sql);
$stmtExp->bind_param("i", $user_id);
$stmtExp->execute();
$resExp = $stmtExp->get_result();
$expiringRows = [];
while ($r = $resExp->fetch_assoc()) {
    $expiringRows[] = $r;
}

// 5. รอชำระเงิน
$wpRows = [];
$wp_sql = "SELECT id, request_no, sign_type, road_name, width, height, quantity,
    (quantity * 200) as fee, created_at
    FROM sign_requests
    WHERE user_id = ? AND status = 'waiting_payment'
    ORDER BY created_at DESC";
$stmtWP = $conn->prepare($wp_sql);
$stmtWP->bind_param("i", $user_id);
$stmtWP->execute();
$resWP = $stmtWP->get_result();
while ($r = $resWP->fetch_assoc()) {
    $wpRows[] = $r;
}

// 6. คำร้องที่หมดอายุแล้ว
$expired_sql = "SELECT id, request_no, sign_type, road_name,
    end_date as expire_date
    FROM sign_requests
    WHERE user_id = ? AND status = 'expired'
    ORDER BY end_date DESC";
$stmtExpired = $conn->prepare($expired_sql);
$stmtExpired->bind_param("i", $user_id);
$stmtExpired->execute();
$expiredPermits = $stmtExpired->get_result();
$expiredRows = [];
while ($r = $expiredPermits->fetch_assoc()) {
    $expiredRows[] = $r;
}

// Thai short month names
$thaiMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
function thaiDateShort($dateStr, $months)
{
    $ts = strtotime($dateStr);
    return date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - เทศบาลเมืองศิลา</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --sila-primary: #1a56db;
            --sila-bg: #f8fafc;
            --sila-card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--sila-bg);
            font-family: 'Sarabun', sans-serif;
        }

        /* Minimalist Header */
        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h2 {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .dashboard-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Minimalist Stat Cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }

        .mini-stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: 0.2s;
        }

        .mini-stat-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .stat-info h4 {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .stat-info h2 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: #0f172a;
        }

        /* Specific Stat Colors per Sample */
        .stat-info .count-total {
            color: #0f172a;
        }

        .stat-info .count-pending {
            color: #c2410c;
        }

        .stat-info .count-approved {
            color: #15803d;
        }

        .stat-info .count-rejected {
            color: #b91c1c;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            /* Slightly squared like sample */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Color themes for icons bg */
        .bg-blue-light {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .bg-orange-light {
            background: #fff7ed;
            color: #f59e0b;
        }

        .bg-green-light {
            background: #f0fdf4;
            color: #10b981;
        }

        .bg-red-light {
            background: #fef2f2;
            color: #ef4444;
        }

        /* Quick Menu Cards */
        .menu-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .quick-menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3rem;
        }

        .menu-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: left;
            text-decoration: none;
            color: inherit;
            transition: 0.2s;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            border-color: var(--sila-primary);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .menu-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            color: white;
        }

        .menu-card h5 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .menu-card p {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }

        /* Recent Requests Section */
        .recent-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h4 {
            font-weight: 700;
            margin: 0;
        }

        .view-all-link {
            font-size: 0.9rem;
            color: var(--sila-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .request-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 12px;
            transition: 0.2s;
            border-bottom: 1px solid #f1f5f9;
            text-decoration: none;
            color: inherit;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .request-item:hover {
            background: #f8fafc;
        }

        .request-item-icon {
            width: 48px;
            height: 48px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            color: #64748b;
            font-size: 1.25rem;
        }

        .request-item-content {
            flex: 1;
        }

        .request-item-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.25rem;
        }

        .request-item-id {
            font-weight: 700;
            color: #0f172a;
        }

        .request-item-info {
            font-size: 0.85rem;
            color: #64748b;
        }

        .request-item-meta {
            display: flex;
            gap: 15px;
            margin-top: 4px;
        }

        .meta-unit {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Help Alert */
        .help-alert {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0369a1;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h2>สวัสดี,คุณ <?= htmlspecialchars($userData['first_name'] ?? 'ผู้ใช้') ?>
                <?= htmlspecialchars($userData['last_name'] ?? '') ?>
            </h2>
            <p>ยินดีต้อนรับสู่ระบบยื่นคำร้องขอติดตั้งป้ายชั่วคราว</p>
        </div>

        <!-- Stat Grid -->
        <div class="stat-grid">
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>คำร้องทั้งหมด</h4>
                    <h2 class="count-total"><?= number_format($stats['total']) ?></h2>
                </div>
                <div class="stat-icon bg-blue-light">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>รอดำเนินการ</h4>
                    <h2 class="count-pending"><?= number_format($stats['pending']) ?></h2>
                </div>
                <div class="stat-icon bg-orange-light">
                    <i class="bi bi-clock"></i>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>กำลังรอพิจารณา</h4>
                    <h2 class="count-reviewing"><?= number_format($stats['reviewing']) ?></h2>
                </div>
                <div class="stat-icon" style="background: #f0f9ff; color: #0369a1;">
                    <i class="bi bi-search"></i>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>รอชำระเงิน</h4>
                    <h2 class="count-waiting-payment"><?= number_format($stats['waiting_payment']) ?></h2>
                </div>
                <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">
                    <i class="bi bi-credit-card"></i>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>อนุมัติแล้ว</h4>
                    <h2 class="count-approved"><?= number_format($stats['approved']) ?></h2>
                </div>
                <div class="stat-icon bg-green-light">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <div class="mini-stat-card">
                <div class="stat-info">
                    <h4>ปฏิเสธ</h4>
                    <h2 class="count-rejected"><?= number_format($stats['rejected']) ?></h2>
                </div>
                <div class="stat-icon bg-red-light">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
        </div>

        <!-- Recent Requests (paginated) -->
        <div class="recent-section">
            <div class="section-header">
                <h4><i class="bi bi-file-earmark-text me-2"></i>คำร้องล่าสุด</h4>
                <a href="my_request.php" class="view-all-link">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
            </div>

            <div id="recentCardsList"></div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="small text-muted mb-0">แสดง</label>
                    <select id="recentPageSize" class="form-select form-select-sm" style="width:70px;">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                    </select>
                    <label class="small text-muted mb-0">รายการ</label>
                    <div id="recentPageInfo" class="small text-muted"></div>
                </div>

                <div class="d-flex gap-2">
                    <button id="recentPrev" class="btn btn-outline-secondary btn-sm"><i
                            class="bi bi-chevron-left"></i></button>
                    <button id="recentNext" class="btn btn-outline-secondary btn-sm"><i
                            class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Expiring Permits (paginated) -->
        <?php if (!empty($expiringRows)): ?>
            <div class="recent-section" style="border-left: 4px solid #f59e0b;">
                <div class="section-header">
                    <h4><i class="bi bi-clock-history text-warning me-2"></i>ป้ายใกล้หมดอายุ</h4>
                    <span class="badge bg-warning text-dark"><?= count($expiringRows) ?> รายการ</span>
                </div>

                <div id="expCardsList"></div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="small text-muted mb-0">แสดง</label>
                        <select id="expPageSize" class="form-select form-select-sm" style="width:70px;">
                            <option value="5" selected>5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                        </select>
                        <label class="small text-muted mb-0">รายการ</label>
                        <div id="expPageInfo" class="small text-muted"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="expPrev" class="btn btn-outline-secondary btn-sm"><i
                                class="bi bi-chevron-left"></i></button>
                        <button id="expNext" class="btn btn-outline-secondary btn-sm"><i
                                class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Waiting Payment (paginated) -->
        <?php if (!empty($wpRows)): ?>
            <div class="recent-section" style="border-left: 4px solid #ffc107;">
                <div class="section-header">
                    <h4><i class="bi bi-credit-card text-warning me-2"></i>รอชำระเงิน</h4>
                    <span class="badge bg-warning text-dark"><?= count($wpRows) ?> รายการ</span>
                </div>

                <div id="wpCardsList"></div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="small text-muted mb-0">แสดง</label>
                        <select id="wpPageSize" class="form-select form-select-sm" style="width:70px;">
                            <option value="5" selected>5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                        </select>
                        <label class="small text-muted mb-0">รายการ</label>
                        <div id="wpPageInfo" class="small text-muted"></div>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="wpPrev" class="btn btn-outline-secondary btn-sm"><i
                                class="bi bi-chevron-left"></i></button>
                        <button id="wpNext" class="btn btn-outline-secondary btn-sm"><i
                                class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Expired Permits -->
        <?php if (!empty($expiredRows)): ?>
            <div class="recent-section" style="border-left: 4px solid #dc3545;">
                <div class="section-header">
                    <h4><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>ใบอนุญาตหมดอายุ</h4>
                    <span class="badge bg-danger"><?= count($expiredRows) ?> รายการ</span>
                </div>
                <div id="expiredList">
                    <?php foreach ($expiredRows as $idx => $exp):
                        $days_since = (int) ((time() - strtotime($exp['expire_date'])) / 86400);
                        $collect_remain = max(0, 7 - $days_since);
                        ?>
                        <a href="request_detail.php?id=<?= $exp['id'] ?>" class="request-item expired-item"
                            style="<?= $idx >= 5 ? 'display:none;' : '' ?>">
                            <div class="request-item-icon" style="background:#fef2f2; color:#dc3545;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <div class="request-item-content">
                                <div class="request-item-title">
                                    <span
                                        class="request-item-id"><?= htmlspecialchars($exp['request_no'] ?: '#' . $exp['id']) ?></span>
                                    <?php if ($collect_remain > 0): ?>
                                        <span class="badge bg-danger">เก็บป้ายภายใน <?= $collect_remain ?> วัน</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark">เกินกำหนดเก็บป้าย</span>
                                    <?php endif; ?>
                                </div>
                                <div class="request-item-info">
                                    <?= htmlspecialchars($exp['sign_type']) ?> — <?= htmlspecialchars($exp['road_name']) ?>
                                </div>
                                <div class="request-item-meta">
                                    <div class="meta-unit">
                                        <i class="bi bi-calendar-x"></i> หมดอายุ
                                        <?= thaiDateShort($exp['expire_date'], $thaiMonths) ?>
                                    </div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($expiredRows) > 5): ?>
                    <div class="text-center mt-3">
                        <button id="showMoreExpired" class="btn btn-outline-danger btn-sm" onclick="toggleExpiredItems()">
                            <i class="bi bi-chevron-down me-1"></i> ดูเพิ่มเติม (<span
                                id="hiddenCount"><?= count($expiredRows) - 5 ?></span> รายการ)
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Menu (bottom) -->
        <div class="menu-label">เมนูหลัก</div>
        <div class="quick-menu-grid">
            <a href="request_form.php" class="menu-card">
                <div class="menu-card-icon bg-primary">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <h5>ยื่นคำร้องใหม่</h5>
                <p>ขออนุญาตติดตั้งป้ายชั่วคราว</p>
            </a>
            <a href="my_request.php" class="menu-card">
                <div class="menu-card-icon bg-success">
                    <i class="bi bi-search"></i>
                </div>
                <h5>ติดตามสถานะ</h5>
                <p>ดูสถานะคำร้องของคุณ</p>
            </a>
            <a href="feedback.php" class="menu-card">
                <div class="menu-card-icon" style="background: #f59e0b;">
                    <i class="bi bi-star-fill"></i>
                </div>
                <h5>ประเมินความพึงพอใจ</h5>
                <p>ให้คะแนนการบริการ</p>
            </a>
        </div>

        <!-- Help Alert -->
        <div class="help-alert mb-5">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                <strong>คำแนะนำ:</strong> กรุณาตรวจสอบข้อมูลให้ครบถ้วนก่อนยื่นคำร้อง
                หากมีข้อสงสัยกรุณาดู <a href="../index.php#faq">คู่มือ & คำถามที่พบบ่อย</a>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Toast.fire({ icon: 'success', title: <?= json_encode($_SESSION['flash_success']) ?> });
            });
        </script>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // === Generic Paginator ===
            function createPaginator(config) {
                var data = config.data;
                var container = document.getElementById(config.containerId);
                var info = document.getElementById(config.infoId);
                var sizeEl = document.getElementById(config.sizeId);
                var prevBtn = document.getElementById(config.prevId);
                var nextBtn = document.getElementById(config.nextId);
                var renderItem = config.renderItem;
                var page = 1;

                function render() {
                    var size = parseInt(sizeEl.value, 10);
                    var total = data.length;
                    var totalPages = Math.max(1, Math.ceil(total / size));
                    if (page > totalPages) page = totalPages;
                    var start = (page - 1) * size;
                    var slice = data.slice(start, start + size);

                    if (total === 0) {
                        container.innerHTML = '<div class="text-center py-4 text-muted">ไม่พบข้อมูล</div>';
                    } else {
                        container.innerHTML = slice.map(renderItem).join('');
                    }
                                        prevBtn.disabled = page <= 1;
                    nextBtn.disabled = page >= totalPages;
                }

                sizeEl.addEventListener('change', function () { page = 1; render(); });
                prevBtn.addEventListener('click', function () { if (page > 1) { page--; render(); } });
                nextBtn.addEventListener('click', function () {
                    var totalPages = Math.max(1, Math.ceil(data.length / parseInt(sizeEl.value, 10)));
                    if (page < totalPages) { page++; render(); }
                });
                render();
            }

            // === Recent Requests Data ===
            var recentData = <?= json_encode(array_map(function ($r) use ($thaiMonths) {
                return [
                    'id' => $r['id'],
                    'request_no' => $r['request_no'] ?: '#' . $r['id'],
                    'status' => $r['status'],
                    'status_badge' => get_status_badge($r['status']),
                    'sign_type' => $r['sign_type'],
                    'size' => $r['width'] . 'x' . $r['height'] . ' ม.',
                    'date' => thaiDateShort($r['created_at'], $thaiMonths),
                    'road' => $r['road_name']
                ];
            }, $recentRequests)) ?>;

            createPaginator({
                data: recentData,
                containerId: 'recentCardsList',
                infoId: 'recentPageInfo',
                sizeId: 'recentPageSize',
                prevId: 'recentPrev',
                nextId: 'recentNext',
                renderItem: function (r) {
                    return '<a href="request_detail.php?id=' + r.id + '" class="request-item">'
                        + '<div class="request-item-icon"><i class="bi bi-file-earmark-text"></i></div>'
                        + '<div class="request-item-content">'
                        + '<div class="request-item-title">'
                        + '<span class="request-item-id">' + r.request_no + '</span>'
                        + r.status_badge
                        + '</div>'
                        + '<div class="request-item-info">' + r.sign_type + ' - ' + r.size + '</div>'
                        + '<div class="request-item-meta">'
                        + '<div class="meta-unit"><i class="bi bi-calendar3"></i> ' + r.date + '</div>'
                        + '<div class="meta-unit"><i class="bi bi-geo-alt"></i> ' + r.road + '</div>'
                        + '</div>'
                        + '</div>'
                        + '<i class="bi bi-chevron-right text-muted"></i>'
                        + '</a>';
                }
            });

            // === Expiring Permits Data ===
            <?php if (!empty($expiringRows)): ?>
                var expData = <?= json_encode(array_map(function ($r) use ($thaiMonths) {
                    $days_left = max(0, (int) ((strtotime($r['expire_date']) - time()) / 86400));
                    return [
                        'id' => $r['id'],
                        'request_no' => $r['request_no'] ?: '#' . $r['id'],
                        'sign_type' => $r['sign_type'],
                        'road' => $r['road_name'],
                        'days_left' => $days_left,
                        'expire_date' => thaiDateShort($r['expire_date'], $thaiMonths)
                    ];
                }, $expiringRows)) ?>;

                createPaginator({
                    data: expData,
                    containerId: 'expCardsList',
                    infoId: 'expPageInfo',
                    sizeId: 'expPageSize',
                    prevId: 'expPrev',
                    nextId: 'expNext',
                    renderItem: function (r) {
                        var badgeClass = r.days_left <= 7 ? 'bg-danger' : 'bg-warning text-dark';
                        return '<a href="request_detail.php?id=' + r.id + '" class="request-item">'
                            + '<div class="request-item-icon" style="background:#fff7ed; color:#f59e0b;">'
                            + '<i class="bi bi-exclamation-triangle"></i></div>'
                            + '<div class="request-item-content">'
                            + '<div class="request-item-title">'
                            + '<span class="request-item-id">' + r.request_no + '</span>'
                            + '<span class="badge ' + badgeClass + '">เหลือ ' + r.days_left + ' วัน</span>'
                            + '</div>'
                            + '<div class="request-item-info">' + r.sign_type + ' — ' + r.road + '</div>'
                            + '<div class="request-item-meta">'
                            + '<div class="meta-unit"><i class="bi bi-calendar-x"></i> หมดอายุ ' + r.expire_date + '</div>'
                            + '</div>'
                            + '</div>'
                            + '<i class="bi bi-chevron-right text-muted"></i>'
                            + '</a>';
                    }
                });
            <?php endif; ?>

            // === Waiting Payment Data ===
            <?php if (!empty($wpRows)): ?>
                var wpData = <?= json_encode(array_map(function ($r) use ($thaiMonths) {
                    return [
                        'id' => $r['id'],
                        'request_no' => $r['request_no'] ?: '#' . $r['id'],
                        'sign_type' => $r['sign_type'],
                        'road' => $r['road_name'],
                        'size' => $r['width'] . 'x' . $r['height'] . ' ม.',
                        'fee' => (int) $r['fee'],
                        'date' => thaiDateShort($r['created_at'], $thaiMonths)
                    ];
                }, $wpRows)) ?>;

                createPaginator({
                    data: wpData,
                    containerId: 'wpCardsList',
                    infoId: 'wpPageInfo',
                    sizeId: 'wpPageSize',
                    prevId: 'wpPrev',
                    nextId: 'wpNext',
                    renderItem: function (r) {
                        return '<a href="request_detail.php?id=' + r.id + '" class="request-item">'
                            + '<div class="request-item-icon" style="background:#fff7ed; color:#f59e0b;">'
                            + '<i class="bi bi-credit-card"></i></div>'
                            + '<div class="request-item-content">'
                            + '<div class="request-item-title">'
                            + '<span class="request-item-id">' + r.request_no + '</span>'
                            + '<span class="badge bg-warning text-dark">รอชำระ</span>'
                            + '</div>'
                            + '<div class="request-item-info">' + r.sign_type + ' — ' + r.road + ' (' + r.size + ')</div>'
                            + '<div class="request-item-meta">'
                            + '<div class="meta-unit"><i class="bi bi-cash-stack"></i> ค่าธรรมเนียม: ฿' + r.fee.toLocaleString() + '</div>'
                            + '<div class="meta-unit"><i class="bi bi-calendar3"></i> ' + r.date + '</div>'
                            + '</div>'
                            + '</div>'
                            + '<i class="bi bi-chevron-right text-muted"></i>'
                            + '</a>';
                    }
                });
            <?php endif; ?>

        });

        // === Expired toggle ===
        function toggleExpiredItems() {
            var items = document.querySelectorAll('.expired-item');
            var btn = document.getElementById('showMoreExpired');
            var expanded = btn.getAttribute('data-expanded') === 'true';

            items.forEach(function (item, idx) {
                if (idx >= 5) {
                    item.style.display = expanded ? 'none' : 'flex';
                }
            });
            btn.setAttribute('data-expanded', !expanded);
            btn.innerHTML = expanded
                ? '<i class="bi bi-chevron-down me-1"></i> ดูเพิ่มเติม (<span id="hiddenCount">' + (items.length - 5) + '</span> รายการ)'
                : '<i class="bi bi-chevron-up me-1"></i> ซ่อน';
        }
    </script>
</body>

</html>