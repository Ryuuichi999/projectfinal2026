<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เทศบาลเมืองศิลา - ระบบขออนุญาตติดตั้งป้าย</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            overflow-x: hidden;
        }

        /* Navbar Customization */
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 0;
        }

        .nav-link {
            font-weight: 500;
            color: #333;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #1a56db;
        }

        .btn-primary-custom {
            background-color: #1a56db;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(26, 86, 219, 0.3);
        }

        .btn-primary-custom:hover {
            background-color: #0d47a1;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26, 86, 219, 0.4);
            color: white;
        }

        /* Hero Section */
        .hero-section {
            padding: 160px 0 100px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 85vh;
            display: flex;
            align-items: center;
            position: relative;
        }

        .hero-title {
            font-weight: 700;
            color: #1a1a1a;
            font-size: 3.5rem;
            line-height: 1.4;
            margin-bottom: 30px;
        }

        .hero-title span {
            color: #1a56db;
            display: block;
            margin-top: 10px;
        }

        .hero-text {
            color: #64748b;
            font-size: 1.15rem;
            margin-bottom: 40px;
            line-height: 1.8;
            max-width: 600px;
        }

        /* Steps Section */
        .steps-section {
            padding: 100px 0;
            background: #fff;
        }

        .step-item {
            text-align: center;
            position: relative;
            padding: 20px;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: #eff6ff;
            color: #1a56db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 25px;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 20px rgba(26, 86, 219, 0.1);
        }

        .step-title {
            font-weight: 700;
            margin-bottom: 12px;
            color: #1e293b;
        }

        .step-desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Feature Styling */
        .feature-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: rgba(26, 86, 219, 0.08);
            color: #1a56db;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 30px;
        }

        /* Footer */
        footer {
            background: #0f172a;
            color: white;
            padding: 80px 0 40px;
        }

        .footer-link {
            color: #94a3b8;
            text-decoration: none;
            transition: 0.2s;
        }

        .footer-link:hover {
            color: white;
        }

        @media (max-width: 991px) {
            .hero-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="hero-title">
                        ระบบยื่นคำร้อง<br>
                        <span>ขออนุญาตติดตั้งป้าย</span>
                        ออนไลน์
                    </h1>
                    <p class="hero-text">
                        บริการยื่นคำร้องขออนุญาตติดตั้งป้ายชั่วคราว สะดวกรวดเร็ว ตรวจสอบสถานะง่ายๆ ได้ด้วยตนเองตลอด 24
                        ชั่วโมง ลดขั้นตอน ประหยัดเวลา
                    </p>
                    <div class="d-flex gap-3">
                        <?php
                        $btn_target = isset($_SESSION['user_id']) ? 'users/request_form.php' : 'login.php';
                        ?>
                        <a href="<?= $btn_target ?>"
                            class="btn btn-primary-custom text-white btn-lg px-5 py-3 shadow-lg fs-5">
                            <i class="bi bi-file-earmark-plus me-2"></i> ยื่นคำร้อง
                        </a>
                    </div>
                    <div class="mt-5 d-flex align-items-center gap-4 text-muted small">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check fs-3 text-primary"></i>
                            <span class="lh-sm">ระบบปลอดภัย<br>ได้มาตรฐาน</span>
                        </div>
                        <div class="vr"></div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history fs-3 text-primary"></i>
                            <span class="lh-sm">ใช้งานสะดวก<br>ตลอด 24 ชม.</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://img.freepik.com/free-vector/city-skyline-concept-illustration_114360-8923.jpg"
                        class="img-fluid rounded-4 shadow-lg" alt="Municipality Service"
                        style="max-height: 500px; border: 8px solid white;">
                </div>
            </div>
        </div>
    </section>

    <!-- Steps Section -->
    <section class="steps-section" id="steps">
        <div class="container">
            <div class="text-center mb-5 pb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill mb-3">สะดวก
                    รวดเร็ว</span>
                <h2 class="fw-bold fs-1">ขั้นตอนการยื่นคำร้อง</h2>
                <p class="text-muted">ยื่นคำร้องได้ง่ายๆ เพียง 5 ขั้นตอน</p>
            </div>

            <div class="row g-4">
                <div class="col-lg col-md-6">
                    <div class="step-item">
                        <div class="step-number">01</div>
                        <h5 class="step-title">ลงทะเบียน</h5>
                        <p class="step-desc">สร้างบัญชีผู้ใช้งานเพื่อเข้าสู่ระบบยื่นคำร้อง</p>
                    </div>
                </div>
                <div class="col-lg col-md-6">
                    <div class="step-item">
                        <div class="step-number">02</div>
                        <h5 class="step-title">กรอกข้อมูล</h5>
                        <p class="step-desc">ระบุรายละเอียดป้ายและพิกัดที่ต้องการติดตั้ง</p>
                    </div>
                </div>
                <div class="col-lg col-md-6">
                    <div class="step-item">
                        <div class="step-number">03</div>
                        <h5 class="step-title">อัปโหลดเอกสาร</h5>
                        <p class="step-desc">แนบภาพถ่ายป้ายและเอกสารประกอบ</p>
                    </div>
                </div>
                <div class="col-lg col-md-6">
                    <div class="step-item">
                        <div class="step-number">04</div>
                        <h5 class="step-title">รอการตรวจสอบ</h5>
                        <p class="step-desc">เจ้าหน้าที่ตรวจสอบความถูกต้องและอนุมัติ</p>
                    </div>
                </div>
                <div class="col-lg col-md-6">
                    <div class="step-item">
                        <div class="step-number">05</div>
                        <h5 class="step-title">รับใบอนุญาต</h5>
                        <p class="step-desc">รับใบอนุญาตออนไลน์ได้ทันทีเมื่ออนุมัติ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5" id="services" style="background-color: #f8fafc;">
        <div class="container py-5">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="fw-bold fs-2">บริการของเรา</h2>
                    <p class="text-muted">ระบบที่ช่วยให้การขออนุญาตเป็นเรื่องง่ายสำหรับคุณ</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-file-earmark-richtext"></i></div>
                        <h4 class="fw-bold">ยื่นคำร้องออนไลน์</h4>
                        <p class="text-muted">กรอกข้อมูลและส่งเอกสารผ่านระบบได้ทันที ประหยัดเวลาไม่ต้องเดินทาง</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-search"></i></div>
                        <h4 class="fw-bold">ติดตามสถานะ</h4>
                        <p class="text-muted">ตรวจสอบความคืบหน้าของคำร้องได้แบบ Real-time ทุกขั้นตอน</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-bell"></i></div>
                        <h4 class="fw-bold">แจ้งเตือนรวดเร็ว</h4>
                        <p class="text-muted">รับการแจ้งเตือนผลการอนุมัติผ่านระบบ Line แจ้งเตือนทันใจ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>