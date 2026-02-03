<?php
session_start();
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ตรวจสอบการลบผู้ใช้
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // ป้องกันการลบตัวเอง
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

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT * FROM users ORDER BY role ASC, created_at ASC";
$result = $conn->query($sql);
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
                        text: '<?= $success_msg ?>',
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
                        text: '<?= $error_msg ?>',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'ตกลง'
                    });
                });
            </script>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เลขบัตรประชาชน</th>
                            <th>เบอร์โทร</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?= $row['id'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['title_name'] . ' ' . $row['first_name'] . ' ' . $row['last_name']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['citizen_id']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['phone']) ?>
                                    </td>
                                    <td>
                                        <?php if ($row['role'] == 'admin'): ?>
                                            <span class="badge bg-danger">ผู้ดูแลระบบ</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">ผู้ใช้งานทั่วไป</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>)"
                                                class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> ลบ
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">บัญชีของคุณ</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>

    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "การกระทำนี้ไม่สามารถเรียกคืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ลบผู้ใช้',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `users_list.php?delete_id=${id}`;
                }
            });
        }
    </script>
</body>

</html>