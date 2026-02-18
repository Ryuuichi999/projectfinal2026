<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

$displayName = '';
$initials = '';
if ($userId) {
    $stmt = $conn->prepare("SELECT title_name, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $first = $res['first_name'] ?? '';
        $last = $res['last_name'] ?? '';
        $displayName = trim(($res['title_name'] ?? '') . ' ' . $first . ' ' . $last);
        $fi = mb_substr($first, 0, 1, 'UTF-8');
        $li = mb_substr($last, 0, 1, 'UTF-8');
        $initials = strtoupper($fi . $li);
    }
}

// Notification Logic (same as user_navbar)
$notifItems = [];
$notifBadgeCount = 0;
if ($userId && $role === 'user') {
    $stmtN = $conn->prepare("SELECT id, status, created_at FROM sign_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmtN->bind_param("i", $userId);
    $stmtN->execute();
    $rs = $stmtN->get_result();
    while ($row = $rs->fetch_assoc()) {
        $status = $row['status'];
        $label = $status;
        if ($status === 'waiting_payment')
            $label = 'รอชำระเงิน';
        elseif ($status === 'approved')
            $label = 'อนุมัติแล้ว';
        elseif ($status === 'rejected')
            $label = 'ไม่อนุมัติ';
        elseif ($status === 'waiting_receipt')
            $label = 'รอออกใบเสร็จ';
        elseif ($status === 'need_documents')
            $label = 'ขอเอกสารเพิ่ม';
        elseif ($status === 'reviewing')
            $label = 'กำลังพิจารณา';
        $notifItems[] = ['id' => (int) $row['id'], 'label' => $label, 'date' => $row['created_at']];
    }
    $currentCount = count($notifItems);
    $lastView = $_SESSION['notif_last_view_user'] ?? 0;
    $notifBadgeCount = max(0, $currentCount - $lastView);
}
?>

<style>
    .navbar-main {
        backdrop-filter: blur(15px);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        padding: 12px 0;
        transition: 0.3s;
    }

    .navbar-main .nav-link {
        font-weight: 600;
        color: #334155;
        font-size: 1rem;
        padding: 8px 16px !important;
        border-radius: 8px;
    }

    .navbar-main .nav-link:hover {
        color: #1a56db;
        background: rgba(26, 86, 219, 0.05);
    }

    .user-profile-pill {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #f8fafc;
        padding: 6px 16px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        white-space: nowrap;
    }

    .avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1a56db, #3b82f6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .notif-btn-main {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        position: relative;
    }

    .notif-badge-main {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 50px;
        border: 2px solid #fff;
    }

    /* Fixed top adjustment */
    body {
        padding-top: 110px;
    }

    .btn-primary-custom {
        background: #1a56db;
        border: none;
        transition: 0.3s;
    }

    .btn-primary-custom:hover {
        background: #1e429f;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26, 86, 219, 0.2);
    }
</style>

<nav class="navbar navbar-expand-lg navbar-main fixed-top">
    <div class="container-fluid px-md-5">
        <a class="navbar-brand d-flex align-items-center" href="/Project2026/index.php">
            <img src="/Project2026/image/logosila.png" alt="Logo" style="height: 50px; width: auto;">
            <span class="ms-2 fw-bold text-dark">เทศบาลเมืองศิลา</span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNavbar">
            <i class="bi bi-list fs-2"></i>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
                <li class="nav-item"><a class="nav-link" href="/Project2026/index.php#steps">ขั้นตอนการยื่น</a></li>
                <li class="nav-item"><a class="nav-link" href="/Project2026/index.php#legal">เอกสารที่ใช้</a></li>
                <li class="nav-item"><a class="nav-link" href="/Project2026/index.php#faq">ช่วยเหลือ & ติดต่อ</a></li>
                 <li class="nav-item"><a class="nav-link" href="/Project2026/map_public.php">แผนที่จุดติดตั้ง</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if ($userId): ?>
                    <!-- Notifications -->
                    <div class="dropdown">
                        <button class="notif-btn-main text-muted shadow-none border-1" data-bs-toggle="dropdown"
                            id="mainNotifBtn" data-count="<?= count($notifItems) ?>">
                            <i class="bi bi-bell"></i>
                            <?php if ($notifBadgeCount > 0): ?>
                                <span class="notif-badge-main" id="mainNotifBadge"><?= $notifBadgeCount ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2 mt-2"
                            style="width: 280px; border-radius: 12px;">
                            <li class="px-2 py-1 mb-2 border-bottom"><span class="fw-bold">การแจ้งเตือน</span></li>
                            <?php if (empty($notifItems)): ?>
                                <li class="text-center p-3 text-muted small">ไม่มีการแจ้งเตือน</li>
                            <?php else: ?>
                                <?php foreach ($notifItems as $n): ?>
                                    <li>
                                        <a class="dropdown-item p-2 rounded-3"
                                            href="/Project2026/users/request_detail.php?id=<?= $n['id'] ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-primary small">#<?= $n['id'] ?></span>
                                                <small class="text-muted"
                                                    style="font-size: 0.7rem;"><?= date('d/m/Y', strtotime($n['date'])) ?></small>
                                            </div>
                                            <div class="text-dark small lh-sm"><?= htmlspecialchars($n['label']) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-center text-primary small"
                                    href="/Project2026/users/my_request.php">ดูทั้งหมด</a></li>
                        </ul>
                    </div>

                    <!-- Profile Pill -->
                    <div class="dropdown">
                        <div class="user-profile-pill" data-bs-toggle="dropdown">
                            <div class="avatar-circle"><?= $initials ?></div>
                            <div class="d-none d-md-block text-start">
                                <div class="fw-bold small lh-1"><?= htmlspecialchars($displayName) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">Dashboard</div>
                            </div>
                            <i class="bi bi-chevron-down small text-muted"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2 mt-2"
                            style="border-radius: 12px;">
                            <?php if ($role === 'user'): ?>
                                <li><a class="dropdown-item rounded-3" href="/Project2026/users/index.php"><i
                                            class="bi bi-grid-1x2 me-2"></i>หน้าจัดการ</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item rounded-3" href="/Project2026/employee/request_list.php"><i
                                            class="bi bi-grid-1x2 me-2"></i>หน้าเจ้าหน้าที่</a></li>
                            <?php endif; ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item rounded-3 text-danger" href="/Project2026/logout.php"><i
                                        class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="nav-link fw-bold">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn btn-primary-custom text-white px-4 rounded-pill">ลงทะเบียน</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var notifBtn = document.getElementById('mainNotifBtn');
        if (notifBtn) {
            notifBtn.addEventListener('show.bs.dropdown', function () {
                var count = notifBtn.getAttribute('data-count') || '0';
                fetch('/Project2026/includes/notif_seen.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ role: 'user', count: count }).toString()
                }).then(() => {
                    var badge = document.getElementById('mainNotifBadge');
                    if (badge) badge.remove();
                }).catch(() => { });
            });
        }
    });
</script>