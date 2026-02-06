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
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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

        /* Steps Section Redesign (Matching Reference Image) */
        .steps-section {
            padding: 100px 0;
            background-color: #f8fafc;
        }

        .step-card {
            background: #fff;
            border-radius: 12px;
            padding: 40px 24px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            position: relative;
            border-top: 5px solid #d4af37;
            /* Gold/Yellow Border */
            transition: 0.3s;
            height: 100%;
        }

        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .step-number-badge {
            position: absolute;
            top: -15px;
            left: -15px;
            width: 34px;
            height: 34px;
            background: #d4af37;
            color: #1e293b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
            z-index: 5;
        }

        .step-icon-circle {
            width: 85px;
            height: 85px;
            background: #1e293b;
            /* Deep Dark Blue */
            color: #d4af37;
            /* Gold Icon Color */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 25px;
            box-shadow: 0 8px 15px rgba(30, 41, 59, 0.2);
        }

        .step-title {
            font-weight: 800;
            margin-bottom: 12px;
            color: #1e293b;
            font-size: 1.25rem;
        }

        .step-desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0;
        }

        .steps-heading h2 {
            color: #1e293b;
            font-weight: 800;
            margin-bottom: 10px;
        }

        /* Requirements & Regulations Styling */
        .info-section {
            padding-bottom: 100px;
            background-color: #f8fafc;
        }

        .info-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .info-title {
            font-weight: 800;
            font-size: 1.4rem;
            color: #1e293b;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .doc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .doc-list li {
            position: relative;
            padding-left: 35px;
            margin-bottom: 18px;
            color: #475569;
            font-size: 1.05rem;
            font-weight: 500;
        }

        .doc-list li i {
            position: absolute;
            left: 0;
            top: 2px;
            color: #10b981;
            font-size: 1.2rem;
        }

        .reg-box {
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 15px;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .reg-box-title {
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .reg-box-desc {
            font-size: 0.95rem;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .reg-yellow {
            background-color: #fffbeb;
            border-left: 5px solid #fbbf24;
        }

        .reg-yellow .reg-box-title {
            color: #92400e;
        }

        .reg-yellow .reg-box-desc {
            color: #b45309;
        }

        .reg-blue {
            background-color: #f0f7ff;
            border-left: 5px solid #3b82f6;
        }

        .reg-blue .reg-box-title {
            color: #1e40af;
        }

        .reg-blue .reg-box-desc {
            color: #2563eb;
        }

        .reg-green {
            background-color: #f0fdf4;
            border-left: 5px solid #22c55e;
        }

        .reg-green .reg-box-title {
            color: #166534;
        }

        .reg-green .reg-box-desc {
            color: #15803d;
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
                <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
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
                <div class="col-lg-6 text-center" data-aos="fade-left">
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
            <div class="text-center mb-5 pb-3 steps-heading" data-aos="fade-up">
                <h2 class="fs-1">ขั้นตอนการขออนุญาต</h2>
                <p class="text-muted fs-5"> ดำเนินการง่ายๆ เพียง 4 ขั้นตอน</p>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-xl-3 col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-card">
                        <div class="step-number-badge">1</div>
                        <div class="step-icon-circle">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </div>
                        <h4 class="step-title">ยื่นคำร้อง</h4>
                        <p class="step-desc">กรอกข้อมูลและแนบเอกสารประกอบการขออนุญาต</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="step-card">
                        <div class="step-number-badge">2</div>
                        <div class="step-icon-circle">
                            <i class="bi bi-clipboard2-check-fill"></i>
                        </div>
                        <h4 class="step-title">ตรวจสอบเอกสาร</h4>
                        <p class="step-desc">เจ้าหน้าที่ตรวจสอบความถูกต้องของเอกสาร</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-card">
                        <div class="step-number-badge">3</div>
                        <div class="step-icon-circle">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <h4 class="step-title">รอพิจารณา</h4>
                        <p class="step-desc">พิจารณาอนุมัติภายใน 7 วันทำการ</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="step-card">
                        <div class="step-number-badge">4</div>
                        <div class="step-icon-circle">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h4 class="step-title">รับใบอนุญาต</h4>
                        <p class="step-desc">ชำระค่าธรรมเนียมและรับใบอนุญาต</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Requirements & Regulations Section -->
    <section class="info-section">
        <div class="container">
            <div class="row g-4">
                <!-- Documents Box -->
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="info-card">
                        <div class="info-title">
                            <i class="bi bi-journal-bookmark text-warning"></i>
                            เอกสารที่ต้องเตรียม
                        </div>
                        <ul class="doc-list">
                            <li><i class="bi bi-check-circle-fill"></i> สำเนาบัตรประจำตัวประชาชน</li>
                            <li><i class="bi bi-check-circle-fill"></i> สำเนาทะเบียนบ้าน</li>
                            <li><i class="bi bi-check-circle-fill"></i> แบบป้ายหรือภาพตัวอย่างป้าย</li>
                            <li><i class="bi bi-check-circle-fill"></i> หนังสือยินยอมจากเจ้าของพื้นที่
                                (กรณีติดตั้งในที่ดินผู้อื่น)</li>
                        </ul>
                    </div>
                </div>

                <!-- Regulations Box -->
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="info-card">
                        <div class="info-title">
                            <i class="bi bi-exclamation-circle text-warning"></i>
                            ข้อกำหนดสำคัญ
                        </div>

                        <div class="reg-box reg-yellow">
                            <h5 class="reg-box-title">ขนาดป้าย</h5>
                            <p class="reg-box-desc">ป้ายชั่วคราวต้องมีขนาดไม่เกิน 2 x 3 เมตร หรือตามที่กำหนดในเทศบัญญัติ
                            </p>
                        </div>

                        <div class="reg-box reg-blue">
                            <h5 class="reg-box-title">ระยะเวลา</h5>
                            <p class="reg-box-desc">ติดตั้งได้ไม่เกิน 30 วัน และสามารถต่ออายุได้ตามระเบียบ</p>
                        </div>

                        <div class="reg-box reg-green">
                            <h5 class="reg-box-title">ค่าธรรมเนียม</h5>
                            <p class="reg-box-desc">ตามประเภทและขนาดป้าย เริ่มต้น 100-500 บาท</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5" id="services" style="background-color: #f8fafc;">
        <div class="container py-5">
            <div class="row text-center mb-5" data-aos="fade-up">
                <div class="col-lg-8 mx-auto">
                    <h2 class="fw-bold fs-2">บริการ</h2>
                    <p class="text-muted">ระบบที่ช่วยให้การขออนุญาตเป็นเรื่องง่ายสำหรับคุณ</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-file-earmark-richtext"></i></div>
                        <h4 class="fw-bold">ยื่นคำร้องออนไลน์</h4>
                        <p class="text-muted">กรอกข้อมูลและส่งเอกสารผ่านระบบได้ทันที ประหยัดเวลาไม่ต้องเดินทาง</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-search"></i></div>
                        <h4 class="fw-bold">ติดตามสถานะ</h4>
                        <p class="text-muted">ตรวจสอบความคืบหน้าของคำร้องได้แบบ Real-time ทุกขั้นตอน</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="300">
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
    <!-- AOS JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
    </script>
</body>

</html>