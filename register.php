<?php
require 'includes/db.php';

if (isset($_POST['submit'])) {
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users(title_name,first_name,last_name,citizen_id,phone,address,password)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        "sssssss",
        $_POST['title_name'],
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['citizen_id'],
        $_POST['phone'],
        $_POST['address'],
        $pass
    );
    if ($stmt->execute()) {
        $success = true;
    } else {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: '" . addslashes($conn->error) . "',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'ตกลง'
                });
            });
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิก - Project2026</title>
    <?php include 'includes/header.php'; ?>
    <link rel="stylesheet" href="assets/css/dynamic-login.css">
</head>

<body>

    <!-- Floating Background Particles -->
    <ul class="circles">
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
    </ul>

    <div class="glass-card fade-in-up" style="max-width: 600px;">
        <div class="text-center">
            <h4 class="mb-4">สมัครสมาชิกใหม่</h4>
        </div>

        <?php if (isset($success) && $success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'สมัครสมาชิกสำเร็จ',
                        text: 'กรุณาเข้าสู่ระบบเพื่อใช้งาน',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                });
            </script>
        <?php endif; ?>

        <form method="post">
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="title_name" class="form-select h-100">
                        <option>นาย</option>
                        <option>นาง</option>
                        <option>นางสาว</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input class="form-control" name="first_name" placeholder="ชื่อ" required>
                </div>
                <div class="col-md-5">
                    <input class="form-control" name="last_name" placeholder="นามสกุล" required>
                </div>
            </div>

            <div class="mb-3">
                <input class="form-control" name="citizen_id" placeholder="เลขบัตรประชาชน (ใช้สำหรับเข้าสู่ระบบ)"
                    required>
            </div>

            <div class="mb-3">
                <input class="form-control" name="phone" placeholder="เบอร์โทรศัพท์" required>
            </div>

            <div class="mb-3">
                <textarea class="form-control" name="address" placeholder="ที่อยู่ปัจจุบัน" rows="2"></textarea>
            </div>

            <div class="mb-4">
                <input class="form-control" type="password" name="password" placeholder="กำหนดรหัสผ่าน" required>
            </div>

            <button class="btn btn-primary-gradient w-100 shadow-sm mb-3" name="submit">
                ยืนยันการสมัคร
            </button>

            <div class="text-center">
                <a href="login.php" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left"></i> กลับหน้าเข้าสู่ระบบ
                </a>
            </div>
        </form>
    </div>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>