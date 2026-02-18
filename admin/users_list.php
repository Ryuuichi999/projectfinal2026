<?php
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// === POST Actions (ปลอดภัยกว่า GET) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ลบผู้ใช้
    if (isset($_POST['delete_id'])) {
        $delete_id = (int) $_POST['delete_id'];
        if ($delete_id != $_SESSION['user_id']) {
            $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->bind_param("i", $delete_id);
            if ($stmt_del->execute()) {
                $success_msg = "ลบผู้ใช้เรียบร้อยแล้ว";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการลบ";
            }
        } else {
            $error_msg = "ไม่สามารถลบบัญชีของตนเองได้";
        }
    }

    // เปลี่ยน Role ผู้ใช้
    if (isset($_POST['change_role_id']) && isset($_POST['new_role'])) {
        $target_id = (int) $_POST['change_role_id'];
        $new_role = $_POST['new_role'];
        $allowed_roles = ['user', 'employee', 'admin'];

        if (in_array($new_role, $allowed_roles) && $target_id != $_SESSION['user_id']) {
            $stmt_role = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt_role->bind_param("si", $new_role, $target_id);
            if ($stmt_role->execute()) {
                $success_msg = "เปลี่ยนบทบาทเรียบร้อยแล้ว";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเปลี่ยนบทบาท";
            }
        } elseif ($target_id == $_SESSION['user_id']) {
            $error_msg = "ไม่สามารถเปลี่ยนบทบาทของตนเองได้";
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT * FROM users ORDER BY role ASC, created_at ASC";
$result = $conn->query($sql);

function get_role_badge($role)
{
    switch ($role) {
        case 'admin':
            return '<span class="badge bg-danger">ผู้ดูแลระบบ</span>';
        case 'employee':
            return '<span class="badge bg-primary">เจ้าหน้าที่</span>';
        default:
            return '<span class="badge bg-success">ผู้ใช้งาน</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">👥 จัดการผู้ใช้งาน</h2>
            <a href="add_user.php" class="btn btn-success">
                <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้
            </a>
        </div>

        <?php if (isset($success_msg)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: '<?= htmlspecialchars($success_msg) ?>',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'users_list.php';
                    });
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error_msg)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: '<?= htmlspecialchars($error_msg) ?>',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'ตกลง'
                    });
                });
            </script>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="usersTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เลขบัตรประชาชน</th>
                            <th>เบอร์โทร</th>
                            <th>อีเมล</th>
                            <th>บทบาท</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['title_name'] . ' ' . $row['first_name'] . ' ' . $row['last_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['citizen_id']) ?></td>
                                    <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                                    <td><?= get_role_badge($row['role']) ?></td>
                                    <td>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- ปุ่มเปลี่ยน Role -->
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown">
                                                        <i class="bi bi-shield-lock"></i> บทบาท
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <button
                                                                class="dropdown-item <?= $row['role'] === 'user' ? 'active' : '' ?>"
                                                                onclick="changeRole(<?= $row['id'] ?>, 'user')">
                                                                🟢 ผู้ใช้งาน
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button
                                                                class="dropdown-item <?= $row['role'] === 'employee' ? 'active' : '' ?>"
                                                                onclick="changeRole(<?= $row['id'] ?>, 'employee')">
                                                                🔵 เจ้าหน้าที่
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button
                                                                class="dropdown-item <?= $row['role'] === 'admin' ? 'active' : '' ?>"
                                                                onclick="changeRole(<?= $row['id'] ?>, 'admin')">
                                                                🔴 ผู้ดูแลระบบ
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <!-- ปุ่มลบ -->
                                                <button onclick="confirmDelete(<?= $row['id'] ?>)"
                                                    class="btn btn-sm btn-outline-danger ms-1">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">บัญชีของคุณ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden POST Forms -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="delete_id" id="deleteIdInput">
    </form>
    <form id="roleForm" method="POST" style="display:none;">
        <input type="hidden" name="change_role_id" id="roleIdInput">
        <input type="hidden" name="new_role" id="roleInput">
    </form>

    <?php include '../includes/scripts.php'; ?>

    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "การกระทำนี้ไม่สามารถเรียกคืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> ลบผู้ใช้',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteIdInput').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        function changeRole(id, role) {
            const roleNames = { 'user': 'ผู้ใช้งาน', 'employee': 'เจ้าหน้าที่', 'admin': 'ผู้ดูแลระบบ' };
            Swal.fire({
                title: 'ยืนยันเปลี่ยนบทบาท?',
                text: `เปลี่ยนเป็น "${roleNames[role]}"`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('roleIdInput').value = id;
                    document.getElementById('roleInput').value = role;
                    document.getElementById('roleForm').submit();
                }
            });
        }
    </script>
</body>

</html>