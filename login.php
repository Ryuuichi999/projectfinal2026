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
    <title>เข้าสู่ระบบ</title>
    <?php include 'includes/header.php'; ?>
</head>

<body class="d-flex justify-content-center align-items-center" style="height:100vh">

    <div class="card p-4 shadow fade-in-up" style="width:380px">
        <h4 class="text-center mb-3">เข้าสู่ระบบ</h4>

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
            <input class="form-control mb-2" name="citizen_id" placeholder="เลขบัตรประชาชน" required>
            <input class="form-control mb-3" type="password" name="password" placeholder="รหัสผ่าน" required>

            <button name="login" class="btn btn-outline-secondary w-100">เข้าสู่ระบบ</button>
        </form>

        <hr>

        <!-- ปุ่ม LINE Login -->
        <?php
        $line_login_url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id=2008891589&redirect_uri=" . urlencode("http://localhost/Project2026/callback_line.php") . "&state=" . rand() . "&scope=profile%20openid";
        ?>
        <a href="<?= $line_login_url ?>" class="btn btn-success w-100 mb-2">
            <i class="bi bi-line"></i> เข้าสู่ระบบด้วย LINE
        </a>

        <a href="register.php" class="btn btn-outline-secondary w-100">สมัครสมาชิกทั่วไป</a>
    </div>
</body>
</div>
<?php include 'includes/scripts.php'; ?>
</body>

</html>