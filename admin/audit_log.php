<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ─── ตัวกรอง ───
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date = $_GET['date'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// ─── Query ───
$where = "1=1";
$params = [];
$types = '';

if ($filter_action) {
    $where .= " AND a.action LIKE ?";
    $params[] = "%$filter_action%";
    $types .= 's';
}
if ($filter_user) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.citizen_id LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= 'sss';
}
if ($filter_date) {
    $where .= " AND DATE(a.created_at) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if ($types)
    $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Fetch data
$sql = "SELECT a.*, u.first_name, u.last_name, u.role 
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE $where 
        ORDER BY a.created_at DESC 
        LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Action labels
function getActionBadge($action)
{
    $badges = [
        'login' => ['bg-primary', '🔑'],
        'logout' => ['bg-secondary', '🚪'],
        'approve' => ['bg-success', '✅'],
        'reject' => ['bg-danger', '❌'],
        'waiting_payment' => ['bg-info', '💳'],
        'create_request' => ['bg-primary', '📝'],
        'delete_user' => ['bg-danger', '🗑️'],
        'change_role' => ['bg-warning', '👤'],
        'update_settings' => ['bg-info', '⚙️'],
        'issue_receipt' => ['bg-success', '🧾'],
        'upload_slip' => ['bg-info', '📤'],
        'reset_password' => ['bg-warning', '🔐'],
    ];
    $match = $badges[$action] ?? ['bg-secondary', '📋'];
    return "<span class='badge {$match[0]}'>{$match[1]} {$action}</span>";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Audit Log - ประวัติการใช้งาน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .audit-table td {
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-4">📋 ประวัติการใช้งานระบบ (Audit Log)</h2>

        <!-- ตัวกรอง -->
        <div class="filter-bar">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">ประเภทการกระทำ</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">ทั้งหมด</option>
                        <?php
                        $actions = ['login', 'logout', 'approve', 'reject', 'waiting_payment', 'create_request', 'delete_user', 'change_role', 'update_settings', 'issue_receipt', 'upload_slip', 'reset_password'];
                        foreach ($actions as $a):
                            ?>
                            <option value="<?= $a ?>" <?= $filter_action == $a ? 'selected' : '' ?>>
                                <?= $a ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">ค้นหาผู้ใช้</label>
                    <input type="text" name="user" class="form-control form-control-sm" placeholder="ชื่อ / เลขบัตร"
                        value="<?= htmlspecialchars($filter_user) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">วันที่</label>
                    <input type="date" name="date" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">🔍 กรอง</button>
                </div>
            </form>
        </div>

        <!-- ตาราง -->
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small">พบ
                    <?= number_format($total) ?> รายการ
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover audit-table">
                    <thead class="table-light">
                        <tr>
                            <th width="160">วันเวลา</th>
                            <th>ผู้ใช้</th>
                            <th>การกระทำ</th>
                            <th>เป้าหมาย</th>
                            <th>รายละเอียด</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">ยังไม่มีข้อมูล Audit Log</td>
                            </tr>
                        <?php endif; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($row['first_name']): ?>
                                        <span class="fw-bold">
                                            <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                        </span>
                                        <br><small class="text-muted">
                                            <?= $row['role'] ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">ระบบ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= getActionBadge($row['action']) ?>
                                </td>
                                <td>
                                    <?php if ($row['target_table']): ?>
                                        <small>
                                            <?= $row['target_table'] ?> #
                                            <?= $row['target_id'] ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?= htmlspecialchars($row['details'] ?? '-') ?>
                                    </small>
                                </td>
                                <td><small class="text-muted">
                                        <?= $row['ip_address'] ?>
                                    </small></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="?page=<?= $p ?>&action=<?= urlencode($filter_action) ?>&user=<?= urlencode($filter_user) ?>&date=<?= urlencode($filter_date) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>