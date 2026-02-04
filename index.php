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

        /* Navbar */
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
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

        /* Hero Section */
        .hero-section {
            padding: 100px 0 60px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 80vh;
            display: flex;
            align-items: center;
        }

        .hero-title {
            font-weight: 700;
            color: #1a1a1a;
            font-size: 3rem;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-title span {
            color: #1a56db;
        }

        .hero-text {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn-primary-custom {
            background-color: #1a56db;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(26, 86, 219, 0.3);
        }

        .btn-primary-custom:hover {
            background-color: #0d47a1;
            transform: translateY(-2px);
        }

        .btn-outline-custom {
            border: 2px solid #1a56db;
            color: #1a56db;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
            background: white;
        }

        .btn-outline-custom:hover {
            background: #1a56db;
            color: white;
            transform: translateY(-2px);
        }

        /* Features */
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background: rgba(26, 86, 219, 0.1);
            color: #1a56db;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        /* Footer */
        footer {
            background: #1a1a1a;
            color: white;
            padding: 50px 0 20px;
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: 0.2s;
        }

        .footer-link:hover {
            color: white;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <span class="bg-primary text-white p-2 rounded-circle d-flex align-items-center justify-content-center"
                    style="width: 40px; height: 40px; font-weight: bold;">ศ</span>
                <span class="fw-bold fs-5 text-dark">เทศบาลเมืองศิลา</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link active" href="#">หน้าหลัก</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">บริการ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">ติดต่อเรา</a></li>
                    <li class="nav-item ms-lg-2">
                        <a href="login.php" class="btn btn-primary-custom text-white px-4">เข้าสู่ระบบ</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="hero-title">
                        ระบบยื่นคำร้อง<br>
                        <span>ขออนุญาตติดตั้งป้าย</span><br>
                        ออนไลน์
                    </h1>
                    <p class="hero-text">
                        บริการยื่นคำร้องขออนุญาตติดตั้งป้ายชั่วคราว สะดวกรวดเร็ว ตรวจสอบสถานะง่ายๆ ได้ด้วยตนเองตลอด 24
                        ชั่วโมง ลดขั้นตอน ประหยัดเวลา
                    </p>
                    <div class="d-flex gap-3">
                        <a href="register.php" class="btn btn-primary-custom text-white text-decoration-none">
                            ลงทะเบียนใช้งาน
                        </a>
                        <a href="login.php" class="btn btn-outline-custom text-decoration-none">
                            เข้าสู่ระบบ
                        </a>
                    </div>
                    <div class="mt-4 d-flex align-items-center gap-3 text-muted small">
                        <i class="bi bi-shield-check fs-4 text-primary"></i>
                        <span>ระบบปลอดภัย<br>ได้มาตรฐาน</span>
                        <div class="vr mx-2"></div>
                        <i class="bi bi-clock-history fs-4 text-primary"></i>
                        <span>ใช้งานได้<br>ตลอด 24 ชม.</span>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://img.freepik.com/free-vector/city-skyline-concept-illustration_114360-8923.jpg"
                        class="img-fluid rounded-4 shadow-lg" alt="Municipality Service" style="max-height: 450px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-5" id="services">
        <div class="container py-5">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <small class="text-primary fw-bold text-uppercase ls-1">FEATURES</small>
                    <h2 class="fw-bold mt-2">บริการของเรา</h2>
                    <p class="text-muted">ระบบที่ช่วยอำนวยความสะดวกให้ประชาชน</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-file-earmark-richtext"></i></div>
                        <h4>ยื่นคำร้องออนไลน์</h4>
                        <p class="text-muted">กรอกข้อมูลและส่งเอกสารผ่านระบบได้ทันที ไม่ต้องยื่นกระดาษ</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-search"></i></div>
                        <h4>ติดตามสถานะ</h4>
                        <p class="text-muted">ตรวจสอบความคืบหน้าของคำร้องได้แบบ Real-time</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box"><i class="bi bi-chat-dots"></i></div>
                        <h4>แจ้งผลการพิจารณา</h4>
                        <p class="text-muted">รับการแจ้งเตือนผลการอนุมัติผ่าน SMS หรือระบบ Line</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-3">เทศบาลเมืองศิลา</h5>
                    <p class="opacity-75 small">ระบบบริหารจัดการคำร้องออนไลน์ เพื่อยกระดับการให้บริการประชาชน</p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i
                                class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-line"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <h6 class="fw-bold mb-3">เมนู</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="#" class="footer-link">หน้าหลัก</a></li>
                        <li><a href="#" class="footer-link">บริการ</a></li>
                        <li><a href="login.php" class="footer-link">เข้าสู่ระบบ</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-6">
                    <h6 class="fw-bold mb-3">ติดต่อสอบถาม</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small opacity-75">
                        <li><i class="bi bi-telephone me-2"></i> 043-xxx-xxx</li>
                        <li><i class="bi bi-envelope me-2"></i> contact@sila.go.th</li>
                        <li><i class="bi bi-clock me-2"></i> จันทร์-ศุกร์ 08:30 - 16:30 น.</li>
                    </ul>
                </div>
            </div>
            <div class="border-top border-secondary mt-5 pt-4 text-center small opacity-50">
                &copy; 2026 เทศบาลเมืองศิลา. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>