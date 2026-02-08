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
if ($role === 'employee') $positionLabel = 'เจ้าหน้าที่';
elseif ($role === 'admin') $positionLabel = 'ผู้ดูแลระบบ';
elseif ($role === 'user') $positionLabel = 'ผู้ใช้งาน';

$notifItems = [];
$notifCount = 0;
if ($role === 'user' && $userId) {
    $stmtN = $conn->prepare("SELECT id, status, created_at FROM sign_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $stmtN->bind_param("i", $userId);
    $stmtN->execute();
    $rs = $stmtN->get_result();
    while ($row = $rs->fetch_assoc()) {
        $status = $row['status'];
        $label = $status;
        if ($status === 'waiting_payment') $label = 'รอชำระเงิน';
        elseif ($status === 'approved') $label = 'อนุมัติแล้ว';
        elseif ($status === 'rejected') $label = 'ไม่อนุมัติ';
        elseif ($status === 'waiting_receipt') $label = 'รอออกใบเสร็จ';
        $notifItems[] = ['id' => (int)$row['id'], 'label' => $label, 'date' => $row['created_at']];
        if (in_array($status, ['waiting_payment', 'approved', 'rejected', 'waiting_receipt'])) $notifCount++;
    }
} else {
    $q = $conn->query("SELECT id, status, created_at FROM sign_requests ORDER BY id DESC LIMIT 5");
    while ($row = $q->fetch_assoc()) {
        $status = $row['status'];
        $label = $status;
        if ($status === 'pending') $label = 'คำขอใหม่';
        elseif ($status === 'waiting_payment') $label = 'รอชำระเงิน';
        elseif ($status === 'waiting_receipt') $label = 'รอออกใบเสร็จ';
        elseif ($status === 'approved') $label = 'อนุมัติแล้ว';
        $notifItems[] = ['id' => (int)$row['id'], 'label' => $label, 'date' => $row['created_at']];
        if (in_array($status, ['pending', 'waiting_payment', 'waiting_receipt'])) $notifCount++;
    }
}
?>
<div class="topbar">
    <div class="topbar-left">
        <button class="btn btn-outline-secondary btn-sm rounded-3" type="button" id="sidebarToggle"><i class="bi bi-list"></i></button>
    </div>
    <div class="topbar-right">
        <div class="dropdown">
            <button class="btn btn-light rounded-3 position-relative notif-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="notif-badge badge bg-danger rounded-pill"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 300px;">
                <?php if (count($notifItems) === 0): ?>
                    <li class="px-2 py-1 text-muted">ไม่มีการแจ้งเตือน</li>
                <?php else: ?>
                    <?php foreach ($notifItems as $n): ?>
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center" href="/Project2026/users/request_detail.php?id=<?= $n['id'] ?>">
                                <span>#<?= $n['id'] ?> • <?= htmlspecialchars($n['label']) ?></span>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($n['date'])) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="account-pill dropdown">
            <button class="account-btn-plain" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar-initials"><?= htmlspecialchars($initials ?? strtoupper(substr($displayName,0,2))) ?></span>
                <div class="account-text">
                    <div class="name"><?= htmlspecialchars($displayName ?: '') ?></div>
                    <div class="sub"><?= htmlspecialchars($positionLabel ?: '') ?></div>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($role === 'user'): ?>
                    <li><a class="dropdown-item" href="/Project2026/users/index.php">หน้าหลักผู้ใช้</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="/Project2026/admin/dashboard.php">แดชบอร์ด</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="/Project2026/logout.php">ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</div>
