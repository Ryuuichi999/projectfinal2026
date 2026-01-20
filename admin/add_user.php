<?php
session_start();
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = '';

if (isset($_POST['submit'])) {
    $citizen_id = trim($_POST['citizen_id']);
    $password = $_POST['password'];
    $title_name = trim($_POST['title_name']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $role = $_POST['role'];

    // ตรวจสอบว่ามีผู้ใช้นี้อยู่แล้วหรือไม่
    $check_sql = "SELECT id FROM users WHERE citizen_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("s", $citizen_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $message = "รหัสบัตรประชาชนนี้มีอยู่ในระบบแล้ว";
        $message_type = "danger";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (citizen_id, password, title_name, first_name, last_name, phone, address, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $citizen_id, $hashed_password, $title_name, $first_name, $last_name, $phone, $address, $role);

        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        window.location.href = 'users_list.php';
                    });
                });
            </script>";
        } else {
            $message = "เกิดข้อผิดพลาด: " . $conn->error;
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มผู้ใช้งานใหม่</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content fade-in-up">
        <div class="container-fluid" style="max-width: 800px;">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">➕ เพิ่มผู้ใช้งานใหม่</h2>
                    <a href="users_list.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">เลขบัตรประชาชน (Username) *</label>
                            <input type="text" name="citizen_id" class="form-control" required minlength="13"
                                maxlength="13" pattern="\d{13}" title="กรุณากรอกเลข 13 หลัก">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">รหัสผ่าน *</label>
                            <input type="password" name="password" class="form-control" required minlength="4">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">คำนำหน้า *</label>
                            <select name="title_name" class="form-select" required>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ชื่อจริง *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">นามสกุล *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทรศัพท์ *</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">บทบาท (Role) *</label>
                            <select name="role" class="form-select" required>
                                <option value="user">User (ผู้ใช้งานทั่วไป)</option>
                                <option value="employee">Employee (เจ้าหน้าที่)</option>
                                <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">ที่อยู่</label>
                            <textarea name="address" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-12 mt-4 text-center">
                            <button type="submit" name="submit" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-save"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>