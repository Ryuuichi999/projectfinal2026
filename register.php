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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียน - เทศบาลเมืองศิลา</title>
    <!-- Standard Includes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 650px;
            /* narrowed as requested */
            padding: 40px;
            position: relative;
        }

        .back-nav {
            position: absolute;
            top: 25px;
            left: 25px;
            text-decoration: none;
            color: #6c757d;
            font-size: 0.9rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .back-nav:hover {
            color: #0d6efd;
            transform: translateX(-3px);
        }

        .register-title {
            text-align: center;
            font-weight: 700;
            color: #212529;
            margin-bottom: 5px;
            margin-top: 15px;
            font-size: 1.5rem;
        }

        .register-subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .form-control,
        .form-select {
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            border-color: #0d6efd;
        }

        .btn-register {
            background-color: #1a56db;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            width: 100%;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-register:hover {
            background-color: #0d47a1;
        }

        .btn-cancel {
            background-color: transparent;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 10px;
            border-radius: 6px;
            width: 100%;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-cancel:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>

    <div class="register-card fade-in-up">
        <a href="index.php" class="back-nav"><i class="bi bi-arrow-left me-1"></i> กลับหน้าแรก</a>

        <h3 class="register-title">ลงทะเบียน</h3>
        <p class="register-subtitle">กรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งาน</p>


        <?php if (isset($success) && $success): ?>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        <form method="post" id="registerForm" onsubmit="return validateForm()">
            <div class="row g-2 mb-2">
                <div class="col-md-3">
                    <label class="form-label">คำนำหน้า</label>
                    <select name="title_name" class="form-select">
                        <option>นาย</option>
                        <option>นาง</option>
                        <option>นางสาว</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ชื่อ</label>
                    <input class="form-control" name="first_name" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">นามสกุล</label>
                    <input class="form-control" name="last_name" required>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">เลขบัตรประชาชน</label>
                <input class="form-control" name="citizen_id" id="citizen_id" placeholder="เลขบัตร 13 หลัก"
                    maxlength="13" required>
                <div class="form-text text-danger d-none small" id="id-error">กรุณากรอกเลขบัตร 13 หลักให้ถูกต้อง</div>
            </div>

            <div class="mb-2">
                <label class="form-label">เบอร์โทรศัพท์</label>
                <input class="form-control" name="phone" id="phone" placeholder="0xx-xxx-xxxx" required>
            </div>

            <div class="mb-2">
                <label class="form-label">ที่อยู่</label>
                <textarea class="form-control" name="address" rows="2" required></textarea>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label">รหัสผ่าน</label>
                    <div class="input-group">
                        <input class="form-control border-end-0" type="password" name="password" id="password" required>
                        <button class="btn border border-start-0 bg-white" type="button"
                            onclick="togglePass('password', this)">
                            <i class="bi bi-eye text-muted"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <div class="input-group">
                        <input class="form-control border-end-0" type="password" id="confirm_password" required>
                        <button class="btn border border-start-0 bg-white" type="button"
                            onclick="togglePass('confirm_password', this)">
                            <i class="bi bi-eye text-muted"></i>
                        </button>
                    </div>
                    <div class="form-text text-danger d-none small" id="pass-error">รหัสผ่านไม่ตรงกัน</div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="termsCheck" required>
                    <label class="form-check-label text-muted small" for="termsCheck">
                        ฉันยอมรับ <a href="#" class="text-decoration-none">เงื่อนไขการใช้งาน</a> และ <a href="#"
                            class="text-decoration-none">นโยบายความเป็นส่วนตัว</a>
                    </label>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <button type="submit" name="submit" class="btn btn-register">
                        ลงทะเบียน
                    </button>
                </div>
                <div class="col-6">
                    <a href="login.php" class="btn btn-cancel text-center text-decoration-none">
                        ย้อนกลับ
                    </a>
                </div>
            </div>

        </form>
    </div>

    <!-- Validation Script -->
    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        function validateForm() {
            let isValid = true;

            const idInput = document.getElementById('citizen_id');
            const idError = document.getElementById('id-error');
            const idVal = idInput.value.replace(/\D/g, '');

            if (idVal.length !== 13) {
                idInput.classList.add('is-invalid');
                idError.classList.remove('d-none');
                isValid = false;
            } else {
                idInput.classList.remove('is-invalid');
                idError.classList.add('d-none');
            }

            const pass = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            const passError = document.getElementById('pass-error');

            if (pass.value !== confirm.value) {
                confirm.classList.add('is-invalid');
                passError.classList.remove('d-none');
                isValid = false;
            } else {
                confirm.classList.remove('is-invalid');
                passError.classList.add('d-none');
            }

            return isValid;
        }
    </script>
    <?php include 'includes/scripts.php'; ?>
</body>

</html>