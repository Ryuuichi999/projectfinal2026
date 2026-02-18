<?php
require 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/SMTPMailer.php';

$step = $_GET['step'] ?? 'request'; // request, verify, reset
$msg = '';
$msg_type = '';

// =============== STEP 1: กรอกเลขบัตร + ส่ง OTP ===============
if (isset($_POST['send_otp'])) {
    $citizen_id = trim($_POST['citizen_id']);

    // ตรวจสอบว่ามีผู้ใช้นี้จริง
    $stmt = $conn->prepare("SELECT id, email, first_name FROM users WHERE citizen_id = ?");
    $stmt->bind_param("s", $citizen_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $msg = 'ไม่พบผู้ใช้งานด้วยเลขบัตรประชาชนนี้';
        $msg_type = 'danger';
    } elseif (empty($user['email'])) {
        $msg = 'บัญชีนี้ไม่มี Email ในระบบ กรุณาติดต่อเจ้าหน้าที่';
        $msg_type = 'warning';
    } else {
        // สร้าง OTP 6 หลัก
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // ลบ OTP เก่าที่ยังไม่ได้ใช้
        $conn->query("DELETE FROM password_resets WHERE citizen_id = '$citizen_id' AND used = 0");

        // บันทึก OTP ใหม่
        $stmt2 = $conn->prepare("INSERT INTO password_resets (citizen_id, otp, expires_at) VALUES (?, ?, ?)");
        $stmt2->bind_param("sss", $citizen_id, $otp, $expires_at);
        $stmt2->execute();

        // ส่ง Email
        $mailer = new SMTPMailer(SMTP_USER, SMTP_PASS);
        $subject = 'รหัส OTP สำหรับรีเซ็ตรหัสผ่าน - ระบบขออนุญาตติดตั้งป้าย';
        $body = "
            <div style='font-family:Sarabun,sans-serif; max-width:500px; margin:auto; padding:20px;'>
                <div style='text-align:center; padding:20px; background:#1a4fa0; color:white; border-radius:10px 10px 0 0;'>
                    <h2 style='margin:0;'>🔐 รีเซ็ตรหัสผ่าน</h2>
                    <p style='margin:5px 0 0; opacity:0.8;'>ระบบขออนุญาตติดตั้งป้ายชั่วคราว</p>
                </div>
                <div style='padding:20px; border:1px solid #ddd; border-top:none; border-radius:0 0 10px 10px;'>
                    <p>สวัสดีคุณ <b>{$user['first_name']}</b></p>
                    <p>รหัส OTP ของคุณคือ:</p>
                    <div style='text-align:center; padding:15px; background:#f0f4ff; border-radius:8px; margin:15px 0;'>
                        <span style='font-size:32px; letter-spacing:8px; font-weight:bold; color:#1a4fa0;'>{$otp}</span>
                    </div>
                    <p style='color:#666; font-size:14px;'>⏰ OTP นี้มีอายุ <b>15 นาที</b></p>
                    <p style='color:#999; font-size:12px;'>หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยต่ออีเมลนี้</p>
                </div>
            </div>
        ";
        $sent = $mailer->send($user['email'], $subject, $body, 'ระบบเทศบาลเมืองศิลา', true);

        if ($sent) {
            // Mask email
            $email_parts = explode('@', $user['email']);
            $masked = substr($email_parts[0], 0, 2) . '***@' . $email_parts[1];

            $_SESSION['reset_citizen_id'] = $citizen_id;
            $_SESSION['reset_email_masked'] = $masked;
            header("Location: forgot_password.php?step=verify");
            exit;
        } else {
            $msg = 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่อีกครั้ง';
            $msg_type = 'danger';
        }
    }
}

// =============== STEP 2: ตรวจสอบ OTP ===============
if (isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $citizen_id = $_SESSION['reset_citizen_id'] ?? '';

    if (empty($citizen_id)) {
        header("Location: forgot_password.php");
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "SELECT id FROM password_resets 
         WHERE citizen_id = ? AND otp = ? AND used = 0 AND expires_at > ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("sss", $citizen_id, $otp, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $reset_row = $result->fetch_assoc();
        $_SESSION['reset_verified_id'] = $reset_row['id'];
        header("Location: forgot_password.php?step=reset");
        exit;
    } else {
        $msg = 'รหัส OTP ไม่ถูกต้องหรือหมดอายุแล้ว';
        $msg_type = 'danger';
        $step = 'verify';
    }
}

// =============== STEP 3: ตั้งรหัสผ่านใหม่ ===============
if (isset($_POST['reset_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $citizen_id = $_SESSION['reset_citizen_id'] ?? '';
    $reset_id = $_SESSION['reset_verified_id'] ?? '';

    if (empty($citizen_id) || empty($reset_id)) {
        header("Location: forgot_password.php");
        exit;
    }

    if (strlen($new_pass) < 4) {
        $msg = 'รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร';
        $msg_type = 'danger';
        $step = 'reset';
    } elseif ($new_pass !== $confirm_pass) {
        $msg = 'รหัสผ่านไม่ตรงกัน';
        $msg_type = 'danger';
        $step = 'reset';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE citizen_id = ?");
        $stmt->bind_param("ss", $hashed, $citizen_id);

        if ($stmt->execute()) {
            // Mark OTP as used
            $conn->query("UPDATE password_resets SET used = 1 WHERE id = $reset_id");

            // Clear session
            unset($_SESSION['reset_citizen_id'], $_SESSION['reset_email_masked'], $_SESSION['reset_verified_id']);

            $step = 'success';
        } else {
            $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            $msg_type = 'danger';
            $step = 'reset';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - เทศบาลเมืองศิลา</title>
    <?php include 'includes/header.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            font-family: 'Sarabun', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8ecf8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reset-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 460px;
            width: 100%;
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .reset-header {
            background: linear-gradient(135deg, #1a4fa0, #2c6fce);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .reset-header h3 {
            margin: 0;
            font-size: 1.4rem;
        }

        .reset-header p {
            margin: 5px 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .reset-body {
            padding: 30px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dee2e6;
            transition: 0.3s;
        }

        .step-dot.active {
            background: #1a4fa0;
            transform: scale(1.3);
        }

        .step-dot.done {
            background: #28a745;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
        }

        .btn-primary-custom {
            background: #1a4fa0;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: 0.2s;
        }

        .btn-primary-custom:hover {
            background: #0d47a1;
            color: white;
        }

        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }

        .otp-inputs input {
            width: 48px;
            height: 56px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            transition: 0.2s;
        }

        .otp-inputs input:focus {
            border-color: #1a4fa0;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 79, 160, 0.15);
        }

        .success-icon {
            font-size: 60px;
            color: #28a745;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="reset-card">
        <div class="reset-header">
            <h3>🔐 ลืมรหัสผ่าน</h3>
            <p>ระบบขออนุญาตติดตั้งป้ายชั่วคราว</p>
        </div>
        <div class="reset-body">
            <!-- Step Indicators -->
            <div class="step-indicator">
                <div class="step-dot <?= $step == 'request' ? 'active' : ($step != 'request' ? 'done' : '') ?>"></div>
                <div
                    class="step-dot <?= $step == 'verify' ? 'active' : ($step == 'reset' || $step == 'success' ? 'done' : '') ?>">
                </div>
                <div class="step-dot <?= $step == 'reset' ? 'active' : ($step == 'success' ? 'done' : '') ?>"></div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                    <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>
                <!-- STEP 1: กรอกเลขบัตร -->
                <h5 class="text-center mb-3">กรอกเลขบัตรประชาชน</h5>
                <p class="text-muted text-center" style="font-size:0.9rem;">
                    ระบบจะส่งรหัส OTP ไปยัง Email ที่คุณลงทะเบียนไว้
                </p>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">เลขบัตรประชาชน</label>
                        <input type="text" name="citizen_id" class="form-control" placeholder="กรอกเลขบัตร 13 หลัก"
                            maxlength="13" pattern="\d{13}" required>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary-custom">
                        <i class="bi bi-envelope-fill"></i> ส่ง OTP
                    </button>
                </form>

            <?php elseif ($step === 'verify'): ?>
                <!-- STEP 2: ใส่ OTP -->
                <h5 class="text-center mb-3">ใส่รหัส OTP</h5>
                <p class="text-muted text-center" style="font-size:0.9rem;">
                    ส่งรหัสไปที่ <strong>
                        <?= $_SESSION['reset_email_masked'] ?? '***' ?>
                    </strong> แล้ว
                </p>
                <form method="POST">
                    <div class="otp-inputs">
                        <input type="text" maxlength="1" class="otp-input" autofocus>
                        <input type="text" maxlength="1" class="otp-input">
                        <input type="text" maxlength="1" class="otp-input">
                        <input type="text" maxlength="1" class="otp-input">
                        <input type="text" maxlength="1" class="otp-input">
                        <input type="text" maxlength="1" class="otp-input">
                    </div>
                    <input type="hidden" name="otp" id="otp_hidden">
                    <button type="submit" name="verify_otp" class="btn btn-primary-custom">
                        ✅ ยืนยัน OTP
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-muted" style="font-size:0.85rem;">
                        ← ส่ง OTP ใหม่
                    </a>
                </div>

            <?php elseif ($step === 'reset'): ?>
                <!-- STEP 3: ตั้งรหัสผ่านใหม่ -->
                <h5 class="text-center mb-3">ตั้งรหัสผ่านใหม่</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" placeholder="อย่างน้อย 4 ตัวอักษร"
                            minlength="4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ยืนยันรหัสผ่าน</label>
                        <input type="password" name="confirm_password" class="form-control"
                            placeholder="กรอกรหัสผ่านอีกครั้ง" minlength="4" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary-custom">
                        🔑 เปลี่ยนรหัสผ่าน
                    </button>
                </form>

            <?php elseif ($step === 'success'): ?>
                <!-- SUCCESS -->
                <div class="text-center">
                    <div class="success-icon">✅</div>
                    <h4 class="mt-3 text-success">เปลี่ยนรหัสผ่านสำเร็จ!</h4>
                    <p class="text-muted">คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้แล้ว</p>
                    <a href="login.php" class="btn btn-primary-custom mt-3">
                        <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($step !== 'success'): ?>
                <div class="text-center mt-4">
                    <a href="login.php" class="text-muted" style="font-size:0.85rem;">
                        <i class="bi bi-arrow-left"></i> กลับหน้าเข้าสู่ระบบ
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        // OTP Input Auto-Focus Logic
        document.addEventListener('DOMContentLoaded', function () {
            const otpInputs = document.querySelectorAll('.otp-input');
            const hiddenOtp = document.getElementById('otp_hidden');

            if (otpInputs.length === 0) return;

            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    // Combine all values into hidden field
                    let otp = '';
                    otpInputs.forEach(i => otp += i.value);
                    hiddenOtp.value = otp;
                });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });

                // Handle paste
                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pasted = e.clipboardData.getData('text').replace(/[^0-9]/g, '');
                    for (let i = 0; i < Math.min(pasted.length, otpInputs.length); i++) {
                        otpInputs[i].value = pasted[i];
                    }
                    let otp = '';
                    otpInputs.forEach(i => otp += i.value);
                    hiddenOtp.value = otp;
                    if (pasted.length >= otpInputs.length) {
                        otpInputs[otpInputs.length - 1].focus();
                    }
                });
            });
        });
    </script>
</body>

</html>