<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['line_login_data'])) {
    header("Location: login.php");
    exit;
}

$line_data = $_SESSION['line_login_data'];
$error = "";
$success = false;

// จัดการการส่งฟอร์ม (ทั้งเชื่อมบัญชีเดิม และ สมัครใหม่)
if (isset($_POST['action'])) {
    $citizen_id = $_POST['citizen_id'];
    $line_user_id = $line_data['userId'];

    if ($_POST['action'] === 'link_old') {
        // --- เชื่อมบัญชีเดิม ---
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE citizen_id = ?");
        $stmt->bind_param("s", $citizen_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // อัปเดต line_user_id
            $update = $conn->prepare("UPDATE users SET line_user_id = ? WHERE id = ?");
            $update->bind_param("si", $line_user_id, $user['id']);
            if ($update->execute()) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                unset($_SESSION['line_login_data']); // Clear session LINE
                $success = true;
                $redirect_to = ($user['role'] === 'admin' || $user['role'] === 'employee') ? 'admin/dashboard.php' : 'users/index.php';
            } else {
                $error = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล";
            }
        } else {
            $error = "เลขบัตรประชาชนหรือรหัสผ่านไม่ถูกต้อง";
        }

    } elseif ($_POST['action'] === 'register_new') {
        // --- สมัครสมาชิกใหม่ ---
        // เช็คว่า citizen_id ซ้ำไหม
        $check = $conn->prepare("SELECT id FROM users WHERE citizen_id = ?");
        $check->bind_param("s", $citizen_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "เลขบัตรประชาชนนี้มีในระบบแล้ว หากเป็นของคุณ กรุณาเลือก 'เชื่อมต่อบัญชีเดิม'";
        } else {
            // บันทึกข้อมูลใหม่
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $title_name = $_POST['title_name'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];

            $insert = $conn->prepare("INSERT INTO users (citizen_id, password, title_name, first_name, last_name, phone, address, line_user_id, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
            $insert->bind_param("ssssssss", $citizen_id, $password, $title_name, $first_name, $last_name, $phone, $address, $line_user_id);

            if ($insert->execute()) {
                $new_user_id = $insert->insert_id;
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['role'] = 'user';
                unset($_SESSION['line_login_data']);
                $success = true;
                $redirect_to = 'users/index.php';
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เชื่อมต่อบัญชี LINE</title>
    <?php include 'includes/header.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm border-0 fade-in-up">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <?php if (!empty($line_data['pictureUrl'])): ?>
                                <img src="<?= htmlspecialchars($line_data['pictureUrl']) ?>"
                                    class="rounded-circle shadow-sm mb-3" width="80" height="80">
                            <?php endif; ?>
                            <h5>สวัสดี,
                                <?= htmlspecialchars($line_data['displayName']) ?>
                            </h5>
                            <p class="text-muted small">กรุณาเลือกวิธีการเข้าใช้งาน</p>
                        </div>

                        <?php if ($success): ?>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'สำเร็จ!',
                                        text: 'เข้าสู่ระบบเรียบร้อยแล้ว',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.href = '<?= $redirect_to ?>';
                                    });
                                });
                            </script>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <ul class="nav nav-pills nav-fill mb-3" id="pills-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pills-link-tab" data-bs-toggle="pill"
                                    data-bs-target="#pills-link" type="button" role="tab">🔗 เชื่อมบัญชีเดิม</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-new-tab" data-bs-toggle="pill"
                                    data-bs-target="#pills-new" type="button" role="tab">🆕 สมัครสมาชิกใหม่</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="pills-tabContent">
                            <!-- Form เชื่อมบัญชีเดิม -->
                            <div class="tab-pane fade show active" id="pills-link" role="tabpanel">
                                <form method="post">
                                    <input type="hidden" name="action" value="link_old">
                                    <div class="mb-3">
                                        <label>เลขบัตรประชาชน</label>
                                        <input type="text" name="citizen_id" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>รหัสผ่านเดิม</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">ยืนยันการเชื่อมต่อ</button>
                                </form>
                            </div>

                            <!-- Form สมัครใหม่ -->
                            <div class="tab-pane fade" id="pills-new" role="tabpanel">
                                <form method="post">
                                    <input type="hidden" name="action" value="register_new">
                                    <div class="mb-2">
                                        <label>คำนำหน้า</label>
                                        <select name="title_name" class="form-select">
                                            <option value="นาย">นาย</option>
                                            <option value="นาง">นาง</option>
                                            <option value="นางสาว">นางสาว</option>
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col">
                                            <input type="text" name="first_name" class="form-control" placeholder="ชื่อ"
                                                required>
                                        </div>
                                        <div class="col">
                                            <input type="text" name="last_name" class="form-control"
                                                placeholder="นามสกุล" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="citizen_id" class="form-control"
                                            placeholder="เลขบัตรประชาชน" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="phone" class="form-control" placeholder="เบอร์โทรศัพท์"
                                            required>
                                    </div>
                                    <div class="mb-2">
                                        <textarea name="address" class="form-control" placeholder="ที่อยู่"
                                            rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <input type="password" name="password" class="form-control"
                                            placeholder="กำหนดรหัสผ่าน" required>
                                    </div>
                                    <button type="submit" class="btn btn-success w-100">สมัครสมาชิก</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
</body>

</html>