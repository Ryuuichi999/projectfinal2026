<?php
require 'includes/db.php';

if (isset($_POST['login'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE citizen_id=?");
    $stmt->bind_param("s", $_POST['citizen_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin' || $user['role'] === 'employee') {
            $redirect_to = "admin/dashboard.php";
        } else {
            $redirect_to = "users/index.php";
        }
        $success = true;
    } else {
        $error = "ข้อมูลไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - Project2026</title>
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

    <div class="glass-card fade-in-up">
        <div class="text-center">
            <!-- Logo (Optional) -->
            <img src="image/logosila.jpg" alt="Logo" class="rounded-circle shadow-sm mb-3" width="80">
            <h5 class="mb-3">เว็บไซต์ขออนุญาตติดตั้งป้ายชั่วคราว</h5>
        </div>

        <?php if (isset($success) && $success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'เข้าสู่ระบบสำเร็จ',
                        text: 'กำลังพาท่านเข้าสู่ระบบ...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '<?= $redirect_to ?>';
                    });
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'ออกจากระบบสำเร็จ',
                        text: 'ไว้พบกันใหม่!',
                        timer: 1300,
                        showConfirmButton: false
                    });
                    window.history.replaceState(null, null, window.location.pathname);
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'เข้าสู่ระบบล้มเหลว',
                        text: '<?= $error ?>',
                        confirmButtonText: 'ลองใหม่อีกครั้ง'
                    });
                });
            </script>
        <?php endif; ?>

        <form method="post">
            <div class="mb-2">
                <input class="form-control" name="citizen_id" placeholder="เลขบัตรประชาชน" required>
            </div>
            <div class="mb-3">
                <input class="form-control" type="password" name="password" placeholder="รหัสผ่าน" required>
            </div>

            <button name="login" class="btn btn-primary-gradient w-100 mb-2 shadow-sm">
                เข้าสู่ระบบ
            </button>
        </form>

        <div class="position-relative mb-3">
            <hr class="text-muted">
            <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 text-muted small"
                style="background: rgba(255,255,255,0.6) !important; border-radius: 4px;">หรือ</span>
        </div>

        <!-- ปุ่ม LINE Login -->
        <?php
        $line_login_url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id=2008891589&redirect_uri=" . urlencode("http://localhost/Project2026/callback_line.php") . "&state=" . rand() . "&scope=profile%20openid";
        ?>
        <a href="<?= $line_login_url ?>" class="btn btn-line w-100 mb-2">
            <i class="bi bi-line fs-5 me-2"></i> เข้าสู่ระบบด้วย LINE
        </a>

        <a href="register.php" class="btn btn-outline-custom w-100">
            สมัครสมาชิกใหม่
        </a>
    </div>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>