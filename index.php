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

        @keyframes floatImage {
            0% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-15px) rotate(1deg);
            }

            100% {
                transform: translateY(0px) rotate(0deg);
            }
        }

        .floating-hero-img {
            animation: floatImage 6s ease-in-out infinite;
        }

        /* Hero Section Refined */
        .hero-section {
            padding: 40px 0 60px;
            /* Reduced top padding to move closer to navbar */
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            background-image: radial-gradient(#1a56db15 1px, transparent 1px);
            background-size: 30px 30px;
            min-height: 75vh;
            /* Slightly reduced height */
            display: flex;
            align-items: center;
            position: relative;
        }

        .hero-title {
            font-weight: 800;
            color: #0f172a;
            font-size: 2.8rem;
            /* Scaled down from 3.5rem */
            line-height: 1.5;
            /* Increased safety for Thai characters */
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            padding-top: 10px;
            /* Top safety padding */
        }

        .hero-title span {
            color: #1a56db;
            display: inline-block;
            /* Changed to inline-block for better control */
            margin-top: 5px;
            padding: 5px 0;
            /* Vertical safety padding */
            background: linear-gradient(90deg, #1a56db, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-text {
            color: #475569;
            font-size: 1.05rem;
            /* Scaled down from 1.15rem */
            margin-bottom: 35px;
            line-height: 1.8;
            max-width: 520px;
            /* Slightly narrower */
        }

        /* Steps Section Reverted to Gold Border Design */
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
            /* Gold Border */
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

        /* Requirements Section Expanded */
        .info-card {
            background: #fff;
            border-radius: 24px;
            padding: 45px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.05);
            border: 1px solid #f1f5f9;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .info-card::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, #1a56db08 0%, transparent 70%);
            border-radius: 50%;
        }

        .info-title {
            font-weight: 800;
            font-size: 1.6rem;
            color: #0f172a;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doc-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 20px;
        }

        .doc-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .doc-item:hover {
            background: #fff;
            border-color: #1a56db;
            box-shadow: 0 10px 20px rgba(26, 86, 219, 0.05);
        }

        .doc-icon-box {
            width: 45px;
            height: 45px;
            background: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1a56db;
            font-size: 1.4rem;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.03);
            flex-shrink: 0;
        }

        .doc-content h6 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1rem;
            color: #1e293b;
        }

        .doc-content p {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0;
            line-height: 1.5;
        }

        .doc-footer-note {
            margin-top: 30px;
            padding: 15px 20px;
            background: #f0f7ff;
            border-radius: 12px;
            color: #0369a1;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* feature cards are kept as polished from Phase 17 */
        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            background: #fff;
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1a56db, #3b82f6);
            color: #fff;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(26, 86, 219, 0.2);
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

        /* FAQ & Contact Styles */
        .accordion-button:not(.collapsed) {
            background-color: #f0f4ff;
            color: #1a56db;
            font-weight: 600;
        }

        .accordion-button:focus {
            box-shadow: none;
        }

        .contact-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            height: 100%;
            transition: 0.3s;
            border: 1px solid #f1f5f9;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border-color: #1a56db;
        }

        .contact-card i {
            font-size: 2.5rem;
            color: #1a56db;
            margin-bottom: 15px;
            display: inline-block;
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
                        <span>ขออนุญาตติดตั้งป้าย</span> <br>
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
                        class="img-fluid rounded-4 shadow-lg floating-hero-img" alt="Municipality Service"
                        style="max-height: 420px; border: 8px solid white;"> <!-- Reduced max-height -->
                </div>
            </div>
        </div>
    </section>

    <!-- Steps Section -->
    <section class="steps-section" id="steps">
        <div class="container">
            <div class="text-center mb-5 pb-3 steps-heading" data-aos="fade-up">
                <h2 class="fs-2">ขั้นตอนการขออนุญาต</h2>
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

    <!-- Services Section (Moved Up) -->
    <section class="py-5" id="services" style="background-color: #f0f4f8;">
        <div class="container py-5">
            <div class="row text-center mb-5" data-aos="fade-up">
                <div class="col-lg-8 mx-auto">
                    <h2 class="fw-bold fs-2">บริการของเรา</h2>
                    <p class="text-muted fs-5">ระบบที่ช่วยให้การขออนุญาตเป็นเรื่องง่าย</p>
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
                        <p class="text-muted">รับการแจ้งเตือนผลการอนุมัติผ่านระบบ แจ้งเตือนทันใจ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Documents & Legal Section -->
    <section class="legal-section" id="legal">
        <div class="container">
            <div class="row g-5">
                <!-- Left Column: Required Documents -->
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="info-card">
                        <div class="info-title">
                            <i class="bi bi-journal-bookmark-fill text-primary"></i>
                            เอกสารที่ต้องเตรียม
                        </div>
                        <div class="doc-grid">
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>บัตรประจำตัวประชาชน</h6>
                                    <p>สำเนาบัตรของผู้ยื่นคำร้อง พร้อมลงนามรับรองสำเนาถูกต้อง</p>
                                </div>
                            </div>
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-image"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>แบบป้ายหรือภาพถ่าย</h6>
                                    <p>ภาพจำลองป้ายที่จะติดตั้ง </p>
                                </div>
                            </div>
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-pencil-square"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>หนังสือยินยอมเจ้าของพื้นที่</h6>
                                    <p>กรณีติดตั้งในที่ดินผู้อื่น ต้องมีเอกสารอนุญาตจากเจ้าของที่ดิน</p>
                                </div>
                            </div>
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-folder-plus"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>เอกสารอื่นๆ</h6>
                                    <p>เช่น หนังสือรับรองนิติบุคคล (กรณีบริษัท) หรือเอกสารที่เกี่ยวข้อง</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Legal Criteria (Harmonized) -->
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="info-card">
                        <div class="info-title">
                            <i class="bi bi-shield-check text-primary"></i>
                            เกณฑ์การพิจารณา
                        </div>
                        <div class="doc-grid">
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>ระยะเวลาอนุญาต</h6>
                                    <p>ป้ายการค้าอนุญาตไม่เกิน 60 วัน และป้ายประชาสัมพันธ์ไม่เกิน 30 วัน</p>
                                </div>
                            </div>
                            <div class="doc-item">
                                <div class="doc-icon-box">
                                    <i class="bi bi-fullscreen"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>ขนาดป้ายมาตรฐาน</h6>
                                    <p>ป้ายรูปแบบ Banner ขนาดไม่เกิน 1.20 x 2.40 เมตร ตามที่กำหนด</p>
                                </div>
                            </div>
                            <div class="doc-item">
                                <div class="doc-icon-box text-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="doc-content">
                                    <h6>ข้อห้ามการติดตั้ง</h6>
                                    <p>ห้ามบดบังทัศนียภาพ การจราจร หรือติดตั้งในบริเวณที่เทศบาลสั่งห้าม</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- FAQ Section -->
    <section class="py-5" id="faq">
        <div class="container py-5">
            <div class="row justify-content-center mb-5" data-aos="fade-up">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold fs-2 mb-3">คำถามที่พบบ่อย</h2>
                    <p class="text-muted fs-5">รวมคำถาม-ตอบที่ผู้ใช้งานสงสัยบ่อยที่สุด</p>
                </div>
            </div>

            <div class="row justify-content-center" data-aos="fade-up" data-aos-delay="100">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <?php
                        $faqs = [
                            ['ค่าธรรมเนียมคำนวณอย่างไร?', 'คิดตามจำนวนป้าย (ไม่ขึ้นกับขนาด):<br>• ค่าธรรมเนียม <b>200 บาท</b> ต่อป้าย<br>เช่น จำนวน 2 ป้าย = 400 บาท'],
                            ['ใช้เวลากี่วันในการอนุมัติ?', 'โดยปกติเจ้าหน้าที่จะตรวจสอบภายใน <b>7 วันทำการ</b> หลังจากยื่นคำร้องเรียบร้อย หากเอกสารครบถ้วน'],
                            ['ชำระเงินอย่างไร?', 'สแกน QR Code PromptPay ในหน้าชำระเงิน แล้วอัปโหลดสลิป ระบบจะตรวจสอบสลิปอัตโนมัติและออกใบเสร็จทันที'],
                            ['ถ้าเอกสารไม่ครบจะทำอย่างไร?', 'เจ้าหน้าที่จะส่งกลับให้แก้ไข คุณจะได้รับแจ้งเตือนทาง Email และสามารถยื่นเอกสารเพิ่มเติมได้ในหน้ารายละเอียดคำร้อง'],
                            ['สามารถติดตั้งป้ายได้ที่ไหนบ้าง?', 'สามารถติดตั้งได้เฉพาะในเขตเทศบาลเมืองศิลาเท่านั้น ระบบจะตรวจสอบพิกัดจากแผนที่โดยอัตโนมัติ'],
                            ['ต่ออายุใบอนุญาตได้อย่างไร?', 'หลังจากใบอนุญาตหมดอายุ ให้ยื่นคำร้องใหม่โดยกรอกข้อมูลเหมือนเดิม หรือกดปุ่ม "ต่ออายุ" ที่อยู่ในหน้าสถานะคำขอ'],
                            ['QR Code บนหนังสืออนุญาตใช้ทำอะไร?', 'เจ้าหน้าที่สามารถสแกน QR Code เพื่อตรวจสอบว่าใบอนุญาตเป็นของจริงและยังไม่หมดอายุ'],
                            ['ลืมรหัสผ่านทำอย่างไร?', 'กดปุ่ม <b>"ลืมรหัสผ่าน?"</b> ที่หน้าเข้าสู่ระบบ แล้วกรอกเลขบัตรประชาชน ระบบจะส่ง OTP ไปทาง Email ที่ลงทะเบียนไว้'],
                        ];
                        foreach ($faqs as $i => $faq):
                            ?>
                            <div class="accordion-item mb-3 border rounded overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#faq<?= $i ?>">
                                        <?= $faq[0] ?>
                                    </button>
                                </h2>
                                <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-muted">
                                        <?= $faq[1] ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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