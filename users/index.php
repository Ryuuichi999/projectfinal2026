<?php
require '../includes/db.php';

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

function getStatusBadge($status)
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark px-2 py-1">รอดำเนินการ</span>';
        case 'approved':
            return '<span class="badge bg-success px-2 py-1">อนุมัติแล้ว</span>';
        case 'rejected':
            return '<span class="badge bg-danger px-2 py-1">ปฏิเสธ</span>';
        case 'need_documents':
            return '<span class="badge bg-info text-dark px-2 py-1">รอเอกสารเพิ่ม</span>';
        case 'reviewing':
            return '<span class="badge bg-primary px-2 py-1">กำลังตรวจสอบ</span>';
        default:
            return '<span class="badge bg-secondary px-2 py-1">' . htmlspecialchars($status) . '</span>';
    }
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
                <?= htmlspecialchars($userData['last_name'] ?? '') ?></h2>
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
            <a href="my_request.php" class="menu-card">
                <div class="menu-card-icon bg-secondary" style="background: #f7941d !important;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h5>ประวัติคำร้อง</h5>
                <p>ดูประวัติคำร้องทั้งหมด</p>
            </a>
            <a href="#" class="menu-card">
                <div class="menu-card-icon bg-info" style="background: #a855f7 !important;">
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
                                    <?= getStatusBadge($req['status']) ?>
                                </div>
                                <div class="request-item-info">
                                    <?= htmlspecialchars($req['sign_type']) ?> - <?= $req['width'] ?>x<?= $req['height'] ?> ม.
                                </div>
                                <div class="request-item-meta">
                                    <div class="meta-unit">
                                        <i class="bi bi-calendar3"></i> <?= date('j M Y', strtotime($req['created_at'])) ?>
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

        <!-- Help Alert -->
        <div class="help-alert mb-5">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                <strong>คำแนะนำ:</strong> กรุณาตรวจสอบข้อมูลให้ครบถ้วนก่อนยื่นคำร้อง
                หากมีข้อสงสัยกรุณาติดต่อเจ้าหน้าที่
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>