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
    'approved' => 0,
    'rejected' => 0,
    'waiting_payment' => 0,
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

// 3. Fetch Recent Requests (Max 5)
$recentRequests = [];
$sqlRecent = "SELECT id, status, sign_type, width, height, created_at, road_name 
               FROM sign_requests 
               WHERE user_id = ? 
               ORDER BY created_at DESC LIMIT 5";
$stmtRecent = $conn->prepare($sqlRecent);
$stmtRecent->bind_param("i", $user_id);
$stmtRecent->execute();
$resultRecent = $stmtRecent->get_result();
while ($row = $resultRecent->fetch_assoc()) {
    $recentRequests[] = $row;
}

// 4. ป้ายใกล้หมดอายุของผู้ใช้
$expiring_sql = "SELECT id, sign_type, permit_no, road_name,
    DATE_ADD(COALESCE(permit_date, created_at), INTERVAL duration_days DAY) as expire_date
    FROM sign_requests
    WHERE user_id = ? AND status = 'approved'
    AND DATE_ADD(COALESCE(permit_date, created_at), INTERVAL duration_days DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY expire_date ASC LIMIT 5";
$stmtExp = $conn->prepare($expiring_sql);
$stmtExp->bind_param("i", $user_id);
$stmtExp->execute();
$expiringPermits = $stmtExp->get_result();

// 5. คำร้องที่หมดอายุแล้ว
$expired_sql = "SELECT id, sign_type, road_name,
    DATE_ADD(COALESCE(permit_date, created_at), INTERVAL duration_days DAY) as expire_date
    FROM sign_requests
    WHERE user_id = ? AND status = 'expired'
    ORDER BY expire_date DESC";
$stmtExpired = $conn->prepare($expired_sql);
$stmtExpired->bind_param("i", $user_id);
$stmtExpired->execute();
$expiredPermits = $stmtExpired->get_result();
$expiredRows = [];
while ($r = $expiredPermits->fetch_assoc()) { $expiredRows[] = $r; }

// Thai short month names
$thaiMonths = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
function thaiDateShort($dateStr, $months) {
    $ts = strtotime($dateStr);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
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

        <!-- Quick Menu -->
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
            <a href="profile.php" class="menu-card">
                <div class="menu-card-icon" style="background: #a855f7;">
                    <i class="bi bi-person"></i>
                </div>
                <h5>โปรไฟล์</h5>
                <p>จัดการข้อมูลส่วนตัว</p>
            </a>
        </div>

        <!-- Recent Requests List -->
        <div class="recent-section">
            <div class="section-header">
                <h4>คำร้องล่าสุด</h4>
                <a href="my_request.php" class="view-all-link">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="request-list">
                <?php if (empty($recentRequests)): ?>
                    <div class="text-center py-4 text-muted">ไม่พบข้อมูลคำร้อง</div>
                <?php else: ?>
                    <?php foreach ($recentRequests as $req): ?>
                        <a href="request_detail.php?id=<?= $req['id'] ?>" class="request-item">
                            <div class="request-item-icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="request-item-content">
                                <div class="request-item-title">
                                    <span class="request-item-id">#<?= $req['id'] ?></span>
                                    <?= get_status_badge($req['status']) ?>
                                </div>
                                <div class="request-item-info">
                                    <?= htmlspecialchars($req['sign_type']) ?> - <?= $req['width'] ?>x<?= $req['height'] ?> ม.
                                </div>
                                <div class="request-item-meta">
                                    <div class="meta-unit">
                                        <i class="bi bi-calendar3"></i> <?= thaiDateShort($req['created_at'], $thaiMonths) ?>
                                    </div>
                                    <div class="meta-unit">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($req['road_name']) ?>
                                    </div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expiring Permits Alert -->
        <?php if ($expiringPermits->num_rows > 0): ?>
            <div class="recent-section" style="border-left: 4px solid #f59e0b;">
                <div class="section-header">
                    <h4>⏰ ป้ายใกล้หมดอายุ</h4>
                </div>
                <?php while ($exp = $expiringPermits->fetch_assoc()):
                    $days_left = max(0, (int) ((strtotime($exp['expire_date']) - time()) / 86400));
                    ?>
                    <a href="request_detail.php?id=<?= $exp['id'] ?>" class="request-item">
                        <div class="request-item-icon" style="background:#fff7ed; color:#f59e0b;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="request-item-content">
                            <div class="request-item-title">
                                <span class="request-item-id">#<?= $exp['id'] ?></span>
                                <span class="badge <?= $days_left <= 7 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                    เหลือ <?= $days_left ?> วัน
                                </span>
                            </div>
                            <div class="request-item-info">
                                <?= htmlspecialchars($exp['sign_type']) ?> — <?= htmlspecialchars($exp['road_name']) ?>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- Expired Permits Section -->
        <?php if (!empty($expiredRows)): ?>
            <div class="recent-section" style="border-left: 4px solid #dc3545;">
                <div class="section-header">
                    <h4><i class="bi bi-exclamation-triangle-fill text-danger"></i> ใบอนุญาตหมดอายุ</h4>
                    <span class="badge bg-danger"><?= count($expiredRows) ?> รายการ</span>
                </div>
                <div id="expiredList">
                    <?php foreach ($expiredRows as $idx => $exp):
                        $days_since = (int)((time() - strtotime($exp['expire_date'])) / 86400);
                        $collect_remain = max(0, 7 - $days_since);
                    ?>
                        <a href="request_detail.php?id=<?= $exp['id'] ?>" class="request-item expired-item" style="<?= $idx >= 5 ? 'display:none;' : '' ?>">
                            <div class="request-item-icon" style="background:#fef2f2; color:#dc3545;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <div class="request-item-content">
                                <div class="request-item-title">
                                    <span class="request-item-id">#<?= $exp['id'] ?></span>
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
                                        <i class="bi bi-calendar-x"></i> หมดอายุ <?= thaiDateShort($exp['expire_date'], $thaiMonths) ?>
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
                            <i class="bi bi-chevron-down me-1"></i> ดูเพิ่มเติม (<span id="hiddenCount"><?= count($expiredRows) - 5 ?></span> รายการ)
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
    <script>
        function toggleExpiredItems() {
            var items = document.querySelectorAll('.expired-item');
            var btn = document.getElementById('showMoreExpired');
            var expanded = btn.getAttribute('data-expanded') === 'true';
            items.forEach(function(item, idx) {
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