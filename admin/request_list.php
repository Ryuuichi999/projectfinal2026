<?php
session_start();
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
// ตรวจสอบสิทธิ์ Admin หรือ Employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// ตรวจสอบการอัปเดตสถานะ (Quick Action)
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $status = '';

    if ($action === 'approve') {
        $status = 'approved';
        $msg = 'อนุมัติคำขอเรียบร้อยแล้ว';
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $msg = 'ปฏิเสธคำขอเรียบร้อยแล้ว';
    } elseif ($action === 'wait_payment') {
        $status = 'waiting_payment';
        $msg = 'ส่งแจ้งเตือนให้ชำระเงินเรียบร้อยแล้ว';
    }

    if ($status) {
        $stmt_update = $conn->prepare("UPDATE sign_requests SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $status, $request_id);
        if ($stmt_update->execute()) {
            $success = $msg;
        } else {
            $error = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    }
}

// ดึงข้อมูลคำขอทั้งหมด
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY FIELD(r.status, 'pending', 'waiting_payment', 'approved', 'rejected'), r.created_at DESC";
$result = $conn->query($sql);

function get_status_badge($status)
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">⏳ รอพิจารณา</span>';
        case 'waiting_payment':
            return '<span class="badge bg-danger">💰 รอชำระเงิน</span>';
        case 'approved':
            return '<span class="badge bg-success">✅ อนุมัติ</span>';
        case 'rejected':
            return '<span class="badge bg-secondary">❌ ไม่ผ่าน</span>';
        default:
            return '<span class="badge bg-light text-dark">' . $status . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการคำขอ | Admin</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- DataTables for better table management -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-4">📝 รายการคำขออนุญาตติดตั้งป้าย</h2>

        <?php if (isset($success)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire('สำเร็จ', '<?= $success ?>', 'success');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire('ผิดพลาด', '<?= $error ?>', 'error');
                });
            </script>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="table-responsive">
                <table id="requestsTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ผู้ยื่นคำขอ</th>
                            <th>ประเภทป้าย</th>
                            <th>วันที่ยื่น</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($row['title_name'] . $row['first_name'] . ' ' . $row['last_name']) ?>
                                    </div>
                                    <!-- <small class="text-muted">ID: <?= $row['user_id'] ?></small> -->
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['sign_type']) ?>
                                    <div class="small text-muted"><?= $row['width'] ?>x<?= $row['height'] ?> ม.</div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td><?= get_status_badge($row['status']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="../users/request_detail.php?id=<?= $row['id'] ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            🔍 รายละเอียด
                                        </a>
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                            data-bs-toggle="dropdown"></button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <form method="post" onsubmit="return confirm('ยืนยันอนุมัติคำขอนี้?');">
                                                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="dropdown-item text-success"><i
                                                            class="bi bi-check-circle"></i> อนุมัติทันที</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" onsubmit="return confirm('ยืนยันแจ้งชำระเงิน?');">
                                                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="action" value="wait_payment">
                                                    <button class="dropdown-item text-warning"><i
                                                            class="bi bi-currency-dollar"></i> แจ้งชำระเงิน</button>
                                                </form>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <form method="post" onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้?');">
                                                    <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button class="dropdown-item text-danger"><i class="bi bi-x-circle"></i>
                                                        ปฏิเสธคำขอ</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <!-- jQuery and DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#requestsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
                },
                "order": [] // ปิด default sort ให้ใช้ตาม SQL
            });
        });
    </script>
</body>

</html>