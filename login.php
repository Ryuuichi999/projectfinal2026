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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - เทศบาลเมืองศิลา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
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

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f2f5;
            /* Light Gray Background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            /* Rounded Corners */
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            /* Clip inner content */
            width: 100%;
            max-width: 950px;
            /* Limit width for card look */
            min-height: 600px;
            display: flex;
        }

        /* Left Side - Form */
        .login-form-side {
            background: #fff;
            padding: 50px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            /* Changed from center to start */
            position: relative;
        }

        .back-link {
            text-decoration: none;
            color: #6c757d;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            margin-bottom: 40px;
            /* Fixed margin instead of auto */
            transition: 0.2s;
            align-self: flex-start;
        }

        .back-link:hover {
            color: #0d6efd;
            transform: translateX(-5px);
        }

        .login-title {
            font-weight: 700;
            font-size: 2rem;
            color: #212529;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        .login-subtitle {
            color: #6c757d;
            margin-bottom: 30px;
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            border-color: #0d6efd;
        }

        .btn-primary-custom {
            background-color: #1a56db;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: 0.2s;
        }

        .btn-primary-custom:hover {
            background-color: #0d47a1;
        }

        /* Right Side - Info */
        .login-info-side {
            background: #1a4fa0;
            /* Deep Blue from reference */
            color: white;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 50px;
            position: relative;
        }

        /* Specific gradient overlay closer to reference image */
        .login-info-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05) 0%, rgba(0, 0, 0, 0.1) 100%);
            pointer-events: none;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            position: z-index 2;
        }

        .feature-icon-box {
            background: rgba(255, 255, 255, 0.15);
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            font-size: 1.2rem;
        }

        .feature-text h5 {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 1.1rem;
        }

        .feature-text p {
            font-weight: 300;
            opacity: 0.85;
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .municipality-badge {
            background: white;
            color: #1a56db;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .municipality-header {
            margin-bottom: 40px;
        }

        .info-footer {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 500px;
                /* Slimmer on mobile */
            }

            .login-info-side {
                display: none;
                /* Hide info on small screens or stack it */
            }
        }
    </style>
</head>

<body>

    <div class="login-card fade-in-up">

        <!-- Left Side: Form -->
        <div class="login-form-side">
            <a href="index.php" class="back-link">
                <i class="bi bi-arrow-left me-2"></i> กลับหน้าแรก
            </a>

            <div>
                <h2 class="login-title">เข้าสู่ระบบ</h2>
                <p class="login-subtitle">ยินดีต้อนรับกลับมา กรุณาเข้าสู่ระบบเพื่อดำเนินการต่อ</p>

                <?php if (isset($success) && $success): ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'เข้าสู่ระบบสำเร็จ',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = '<?= $redirect_to ?>';
                        });
                    </script>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label text-muted small">เลขประจำตัวประชาชน</label>
                        <input class="form-control" name="citizen_id" placeholder="เลขบัตรประชาชน" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small">รหัสผ่าน</label>
                        <div class="input-group">
                            <input class="form-control border-end-0 m-0" type="password" name="password"
                                placeholder="••••••••" required>
                            <button class="btn border border-start-0 bg-white" type="button" id="togglePassword">
                                <i class="bi bi-eye text-muted"></i>
                            </button>
                        </div>
                    </div>

                    <button name="login" class="btn btn-primary btn-primary-custom mb-3">
                        เข้าสู่ระบบ
                    </button>

                    <div class="text-center">
                        <span class="text-muted small">ยังไม่มีบัญชี?</span>
                        <a href="register.php" class="text-decoration-none fw-bold ms-1">ลงทะเบียนใหม่</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side: Info -->
        <div class="login-info-side">
            <div class="municipality-header">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div>
                        <h5 class="mb-0 fw-bold">เทศบาลเมืองศิลา</h5>
                        <small class="opacity-75">ระบบขออนุญาตติดตั้งป้าย</small>
                    </div>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-box"><i class="bi bi-file-earmark-text"></i></div>
                <div class="feature-text">
                    <h5>ยื่นคำร้องออนไลน์</h5>
                    <p>ยื่นคำร้องได้ง่ายๆ ผ่านระบบออนไลน์ ไม่ต้องเดินทางมาที่สำนักงาน</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-box"><i class="bi bi-bell-fill"></i></div>
                <div class="feature-text">
                    <h5>แจ้งเตือนอัตโนมัติ</h5>
                    <p>รับการแจ้งเตือนผ่าน Line เมื่อมีการอัปเดตสถานะ</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon-box"><i class="bi bi-lightning-charge-fill"></i></div>
                <div class="feature-text">
                    <h5>อนุมัติรวดเร็ว</h5>
                    <p>ระบบตรวจสอบที่มีประสิทธิภาพ ช่วยให้การอนุมัติคำร้องรวดเร็ว</p>
                </div>
            </div>

            <div class="info-footer">
                <div class="d-flex gap-3 align-items-center">
                    <div class="fs-1 opacity-50"><i class="bi bi-building"></i></div>
                    <div>
                        <strong class="d-block">เทศบาลเมืองศิลา</strong>
                        <small class="opacity-75">ตำบลศิลา จังหวัดขอนแก่น</small>
                        <div class="mt-1 opacity-75 small">ให้บริการตลอด 24 ชั่วโมง สอบถามเพิ่มเติม โทร. 043-246-505-6
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const passwordInput = document.querySelector('input[name="password"]');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>

</html>