<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "ไม่พบข้อมูลผู้ใช้";
    exit;
}

// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // อัปเดตโปรไฟล์
    if (isset($_POST['update_profile'])) {
        $title = trim($_POST['title_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($first) || empty($last)) {
            $message = "กรุณากรอกชื่อและนามสกุล";
            $message_type = 'danger';
        } else {
            $sql_up = "UPDATE users SET title_name=?, first_name=?, last_name=?, phone=?, email=?, address=? WHERE id=?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("ssssssi", $title, $first, $last, $phone, $email, $address, $user_id);
            if ($stmt_up->execute()) {
                $message = "บันทึกข้อมูลเรียบร้อยแล้ว";
                $message_type = 'success';
                // Refresh data
                $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
            } else {
                $message = "เกิดข้อผิดพลาด: " . $conn->error;
                $message_type = 'danger';
            }
        }
    }

    // เปลี่ยนรหัสผ่าน
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new_pass) || empty($confirm)) {
            $message = "กรุณากรอกรหัสผ่านให้ครบทุกช่อง";
            $message_type = 'danger';
        } elseif ($new_pass !== $confirm) {
            $message = "รหัสผ่านใหม่ไม่ตรงกัน";
            $message_type = 'danger';
        } elseif (strlen($new_pass) < 6) {
            $message = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";
            $message_type = 'danger';
        } elseif (!password_verify($current, $user['password'])) {
            $message = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
            $message_type = 'danger';
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pw->bind_param("si", $hashed, $user_id);
            if ($stmt_pw->execute()) {
                $message = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
                $message_type = 'success';
            } else {
                $message = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ของฉัน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: bold;
            margin: 0 auto 15px;
        }

        .nav-tabs .nav-link.active {
            background: #f0f6ff;
            border-bottom-color: transparent;
            font-weight: 600;
            color: #0d6efd;
        }
    </style>
</head>

<body>
    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Header Card -->
                <div class="card shadow-sm mb-4 p-4 text-center">
                    <div class="profile-icon">
                        <?= mb_substr($user['first_name'], 0, 1, 'UTF-8') . mb_substr($user['last_name'], 0, 1, 'UTF-8') ?>
                    </div>
                    <h4 class="mb-1">
                        <?= htmlspecialchars($user['title_name'] . ' ' . $user['first_name'] . ' ' . $user['last_name']) ?>
                    </h4>
                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($user['citizen_id']) ?>
                    </p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                        <?= $message_type === 'success' ? '✅' : '⚠️' ?>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-0" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#profile-tab">ข้อมูลส่วนตัว</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#password-tab">เปลี่ยนรหัสผ่าน</a>
                    </li>
                </ul>

                <div class="tab-content card shadow-sm p-4" style="border-top-left-radius: 0;">

                    <!-- Tab: ข้อมูลส่วนตัว -->
                    <div class="tab-pane fade show active" id="profile-tab">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">คำนำหน้า</label>
                                    <select name="title_name" class="form-select">
                                        <?php
                                        $titles = ['นาย', 'นาง', 'นางสาว', 'คุณ'];
                                        foreach ($titles as $t) {
                                            $sel = ($user['title_name'] === $t) ? 'selected' : '';
                                            echo "<option $sel>$t</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control"
                                        value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control"
                                        value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" name="phone" class="form-control"
                                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">อีเมล</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ที่อยู่</label>
                                    <textarea name="address" class="form-control"
                                        rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted">เลขบัตรประชาชน</label>
                                    <input type="text" class="form-control" disabled
                                        value="<?= htmlspecialchars($user['citizen_id']) ?>">
                                    <small class="text-muted">ไม่สามารถเปลี่ยนได้</small>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-action-cancel">
                                    ยกเลิก
                                </a>
                                <button type="submit" name="update_profile" class="btn btn-action-confirm">
                                    บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: เปลี่ยนรหัสผ่าน -->
                    <div class="tab-pane fade" id="password-tab">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">รหัสผ่านปัจจุบัน</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">รหัสผ่านใหม่</label>
                                    <input type="password" name="new_password" class="form-control" minlength="6"
                                        required>
                                    <small class="text-muted">อย่างน้อย 6 ตัวอักษร</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-action-cancel">
                                    ยกเลิก
                                </a>
                                <button type="submit" name="change_password" class="btn btn-action-confirm">
                                    เปลี่ยนรหัสผ่าน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>