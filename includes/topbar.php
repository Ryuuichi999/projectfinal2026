<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$role = $_SESSION['role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

$displayName = '';
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
$positionLabel = '';
if ($role === 'employee')
    $positionLabel = 'เจ้าหน้าที่';
elseif ($role === 'admin')
    $positionLabel = 'ผู้ดูแลระบบ';
elseif ($role === 'user')
    $positionLabel = 'ผู้ใช้งาน';

$notifItems = [];
$notifBadgeCount = 0;
if ($role === 'user' && $userId) {
    $stmtN = $conn->prepare("SELECT id, status, created_at FROM sign_requests WHERE user_id = ? ORDER BY id DESC LIMIT 50");
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
        if (in_array($status, ['reviewing', 'waiting_payment', 'waiting_receipt', 'approved', 'rejected', 'need_documents'])) {
            $notifItems[] = ['id' => (int) $row['id'], 'label' => $label, 'date' => $row['created_at']];
        }
    }
    $currentCount = count($notifItems);
    $lastView = $_SESSION['notif_last_view_user'] ?? 0;
    $notifBadgeCount = max(0, $currentCount - $lastView);
} else {
    $q = $conn->query("SELECT id, status, created_at, receipt_date FROM sign_requests ORDER BY id DESC LIMIT 50");
    while ($row = $q->fetch_assoc()) {
        $status = $row['status'];
        $label = $status;
        if ($status === 'pending')
            $label = 'คำขอใหม่';
        elseif ($status === 'waiting_receipt')
            $label = 'ชำระเงินแล้ว';
        elseif ($status === 'approved')
            $label = 'อนุมัติแล้ว';
        if (in_array($status, ['pending', 'waiting_receipt'])) {
            $notifItems[] = ['id' => (int) $row['id'], 'label' => $label, 'date' => $row['created_at']];
        }
    }
    $currentCount = count($notifItems);
    $lastView = $_SESSION['notif_last_view_emp'] ?? 0;
    $notifBadgeCount = max(0, $currentCount - $lastView);
}
?>
<style>
    /* Topbar notification button - matches user_navbar */
    .topbar .notif-btn {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #64748b;
        position: relative;
        transition: 0.2s;
        cursor: pointer;
        padding: 0;
        font-size: 1.15rem;
        line-height: 1;
        outline: none;
        box-shadow: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
    }

    .topbar .notif-btn:hover,
    .topbar .notif-btn:focus {
        background: #fff;
        color: #1a56db;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .topbar .notif-btn::after {
        display: none;
        /* hide Bootstrap dropdown caret */
    }

    /* Notification badge */
    .topbar .notif-badge-topbar {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 50px;
        border: 2px solid #fff;
        line-height: 1;
        font-weight: 600;
    }

    /* Profile pill - matches user_navbar */
    .topbar .user-profile-pill {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #f8fafc;
        padding: 6px 16px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: 0.2s;
        white-space: nowrap;
    }

    .topbar .user-profile-pill:hover {
        background: #fff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    /* Avatar circle */
    .topbar .avatar-circle {
        width: 32px;
        height: 32px;
        min-width: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1a56db, #3b82f6);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
</style>
<div class="topbar">
    <div class="topbar-left">
        <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" id="sidebarToggle"><i
                class="bi bi-list"></i></button>
    </div>
    <div class="topbar-right">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="notif-btn" type="button" data-bs-toggle="dropdown" id="notifBtn"
                data-role="<?= htmlspecialchars($role ?? '') ?>" data-count="<?= (int) $currentCount ?>">
                <i class="bi bi-bell"></i>
                <?php if ($notifBadgeCount > 0): ?>
                    <span class="notif-badge-topbar" id="notifBadge"><?= $notifBadgeCount ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2 mt-2"
                style="width: 280px; border-radius: 12px;">
                <li class="px-2 py-1 mb-2 border-bottom">
                    <span class="fw-bold fs-6">การแจ้งเตือน</span>
                </li>
                <?php if (empty($notifItems)): ?>
                    <li class="text-center p-3 text-muted small">ไม่มีการแจ้งเตือน</li>
                <?php else: ?>
                    <?php foreach ($notifItems as $n): ?>
                        <li class="mb-1">
                            <a class="dropdown-item p-2 rounded-3"
                                href="<?= ($role === 'user' ? '/Project2026/users/request_detail.php?id=' : '/Project2026/employee/request_detail.php?id=') . $n['id'] ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-primary small">#<?= $n['id'] ?></span>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?= date('d/m/Y', strtotime($n['date'])) ?>
                                    </small>
                                </div>
                                <div class="text-dark small lh-sm">
                                    <?= htmlspecialchars($n['label']) ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item text-center text-primary small py-1"
                        href="/Project2026/employee/request_list.php">ดูรายการทั้งหมด</a></li>
            </ul>
        </div>

        <!-- Account -->
        <div class="dropdown">
            <div class="user-profile-pill" data-bs-toggle="dropdown">
                <div class="avatar-circle">
                    <?= htmlspecialchars($initials ?? strtoupper(substr($displayName, 0, 2))) ?>
                </div>
                <div class="d-none d-md-block text-start">
                    <div class="fw-bold" style="max-width: 150px; font-size: 0.85rem;">
                        <?= htmlspecialchars($displayName) ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($positionLabel ?: '') ?>
                    </div>
                </div>
                <i class="bi bi-chevron-down small text-muted"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2 mt-2" style="border-radius: 12px;">
                <li><a class="dropdown-item rounded-3" href="/Project2026/admin/dashboard.php"><i
                            class="bi bi-speedometer2 me-2"></i>แดชบอร์ด</a></li>
                <?php if ($role === 'admin'): ?>
                    <li><a class="dropdown-item rounded-3" href="/Project2026/admin/users_list.php"><i
                                class="bi bi-people me-2"></i>จัดการผู้ใช้งาน</a></li>
                <?php endif; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item rounded-3 text-danger" href="/Project2026/logout.php"><i
                            class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('notifBtn');
        if (!btn) return;
        btn.addEventListener('show.bs.dropdown', function () {
            var role = btn.getAttribute('data-role') || '';
            var count = btn.getAttribute('data-count') || '0';
            fetch('/Project2026/includes/notif_seen.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ role: role, count: count }).toString()
            }).then(function () {
                var badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
            }).catch(function () { });
        });
    });
</script>