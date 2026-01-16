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
        echo "<script>alert('เกิดข้อผิดพลาด: " . $conn->error . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สมัครสมาชิก</title>
    <?php include 'includes/header.php'; ?>
</head>

<body class="d-flex justify-content-center align-items-center" style="height:100vh">

    <div class="card p-4 shadow fade-in-up" style="width:500px">
        <h4 class="text-center mb-3">สมัครสมาชิก</h4>

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
            <div class="row mb-2">
                <div class="col-4">
                    <select name="title_name" class="form-select">
                        <option>นาย</option>
                        <option>นาง</option>
                        <option>นางสาว</option>
                    </select>
                </div>
                <div class="col"><input class="form-control" name="first_name" placeholder="ชื่อ"></div>
                <div class="col"><input class="form-control" name="last_name" placeholder="นามสกุล"></div>
            </div>

            <input class="form-control mb-2" name="citizen_id" placeholder="เลขบัตรประชาชน">
            <input class="form-control mb-2" name="phone" placeholder="เบอร์โทร">
            <textarea class="form-control mb-2" name="address" placeholder="ที่อยู่"></textarea>
            <input class="form-control mb-3" type="password" name="password" placeholder="รหัสผ่าน">

            <button class="btn btn-outline-secondary w-100" name="submit">สมัครสมาชิก</button> <br><br>
            <a href="login.php" class="btn btn-outline-secondary w-100">กลับหน้า Login</a>
        </form>
    </div>
</body>
</div>
<?php include 'includes/scripts.php'; ?>
</body>

</html>