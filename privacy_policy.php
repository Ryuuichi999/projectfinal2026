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
    <title>นโยบายความเป็นส่วนตัว - เทศบาลเมืองศิลา</title>
    <?php include './includes/header.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .policy-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: white;
            border-radius: 16px;
            padding: 36px 40px;
            margin-bottom: 32px;
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
        .policy-header h1 { font-weight: 700; margin-bottom: 6px; }
        .policy-header p { opacity: 0.85; margin-bottom: 0; }

        .policy-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            padding: 36px 40px;
            margin-bottom: 24px;
        }
        .policy-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .policy-card h2 i { color: #2563eb; }
        .policy-card p, .policy-card li {
            color: #475569;
            line-height: 1.8;
            font-size: 0.95rem;
        }
        .policy-card ul { padding-left: 20px; }
        .policy-card ul li { margin-bottom: 6px; }

        .highlight-box {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            border-radius: 0 10px 10px 0;
            padding: 16px 20px;
            margin: 16px 0;
        }
        .highlight-box p { margin-bottom: 0; color: #1e40af; font-weight: 500; }

        .contact-pdpa {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 20px 24px;
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
    </style>
</head>

<body>
    <?php include './includes/navbar.php'; ?>

    <div class="container px-md-4 px-lg-5 fade-in-up mt-4 mb-5">
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill mb-3">
            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
        </a>
        <div class="policy-header">
            <h1><i class="bi bi-shield-check me-2"></i>นโยบายความเป็นส่วนตัว</h1>
            <p>พระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA)</p>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-info-circle-fill"></i> 1. บทนำ</h2>
            <p>
                เทศบาลเมืองศิลา ("หน่วยงาน") ให้ความสำคัญกับการคุ้มครองข้อมูลส่วนบุคคลของผู้ใช้บริการ
                ตามพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 ("พ.ร.บ. PDPA")
                นโยบายฉบับนี้อธิบายวิธีการเก็บรวบรวม ใช้ เปิดเผย และคุ้มครองข้อมูลส่วนบุคคลของท่าน
                ในการใช้บริการระบบยื่นคำร้องขออนุญาตติดตั้งป้ายชั่วคราวออนไลน์
            </p>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-collection-fill"></i> 2. ข้อมูลส่วนบุคคลที่เก็บรวบรวม</h2>
            <p>หน่วยงานเก็บรวบรวมข้อมูลส่วนบุคคลดังต่อไปนี้:</p>
            <ul>
                <li><b>ข้อมูลระบุตัวตน:</b> คำนำหน้า ชื่อ นามสกุล เลขบัตรประชาชน</li>
                <li><b>ข้อมูลการติดต่อ:</b> หมายเลขโทรศัพท์ อีเมล ที่อยู่</li>
                <li><b>ข้อมูลการใช้บริการ:</b> รายละเอียดคำร้อง ประเภทป้าย ตำแหน่งติดตั้ง (พิกัด GPS) เอกสารประกอบ</li>
                <li><b>ข้อมูลการชำระเงิน:</b> หลักฐานการชำระเงิน (สลิป)</li>
                <li><b>ข้อมูลทางเทคนิค:</b> IP Address, Cookie, ข้อมูล Session สำหรับการเข้าสู่ระบบ</li>
            </ul>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-bullseye"></i> 3. วัตถุประสงค์ในการเก็บรวบรวมและใช้ข้อมูล</h2>
            <p>หน่วยงานใช้ข้อมูลส่วนบุคคลของท่านเพื่อ:</p>
            <ul>
                <li>ดำเนินการรับคำร้องขออนุญาตติดตั้งป้ายชั่วคราว</li>
                <li>ยืนยันตัวตนและตรวจสอบสิทธิ์ในการใช้ระบบ</li>
                <li>ติดต่อสื่อสารเกี่ยวกับสถานะคำร้อง แจ้งผลการพิจารณา และเรื่องที่เกี่ยวข้อง</li>
                <li>ออกใบอนุญาต ใบเสร็จรับเงิน และเอกสารที่เกี่ยวข้อง</li>
                <li>แสดงตำแหน่งป้ายที่ได้รับอนุญาตบนแผนที่สาธารณะ (ไม่แสดงข้อมูลส่วนบุคคล)</li>
                <li>จัดทำสถิติและรายงานภายในหน่วยงาน</li>
                <li>ปฏิบัติตามกฎหมายที่เกี่ยวข้อง</li>
            </ul>
            <div class="highlight-box">
                <p><i class="bi bi-lightbulb me-2"></i>หน่วยงานจะไม่ใช้ข้อมูลของท่านเพื่อวัตถุประสงค์อื่นนอกเหนือจากที่ระบุไว้ข้างต้น เว้นแต่ได้รับความยินยอมจากท่านก่อน</p>
            </div>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-arrow-left-right"></i> 4. การเปิดเผยข้อมูลส่วนบุคคล</h2>
            <p>หน่วยงานอาจเปิดเผยข้อมูลส่วนบุคคลแก่:</p>
            <ul>
                <li><b>เจ้าหน้าที่ภายในหน่วยงาน:</b> เพื่อดำเนินการพิจารณาคำร้อง</li>
                <li><b>หน่วยงานราชการที่เกี่ยวข้อง:</b> ตามที่กฎหมายกำหนด</li>
                <li><b>แผนที่สาธารณะ:</b> แสดงเฉพาะข้อมูลป้ายที่อนุมัติแล้ว (ประเภท ขนาด ถนน) <b>โดยไม่แสดงข้อมูลส่วนบุคคลใดๆ</b></li>
            </ul>
            <div class="highlight-box">
                <p><i class="bi bi-shield-lock me-2"></i>หน่วยงานจะไม่จำหน่าย แลกเปลี่ยน หรือถ่ายโอนข้อมูลส่วนบุคคลของท่านให้แก่บุคคลภายนอกเพื่อวัตถุประสงค์ทางการค้า</p>
            </div>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-clock-history"></i> 5. ระยะเวลาการเก็บรักษาข้อมูล</h2>
            <ul>
                <li>ข้อมูลคำร้องและใบอนุญาต: เก็บรักษาตลอดอายุของใบอนุญาต และอีก <b>5 ปี</b> หลังหมดอายุ</li>
                <li>ข้อมูลบัญชีผู้ใช้: เก็บรักษาตลอดที่ยังใช้บริการ หากไม่มีการเข้าใช้เกิน <b>3 ปี</b> จะแจ้งเตือนและลบข้อมูล</li>
                <li>ข้อมูลการชำระเงิน: เก็บรักษาตามกฎหมายบัญชีไม่น้อยกว่า <b>5 ปี</b></li>
                <li>ข้อมูล Log การใช้งาน: เก็บรักษาไม่เกิน <b>1 ปี</b></li>
            </ul>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-person-check-fill"></i> 6. สิทธิ์ของเจ้าของข้อมูล</h2>
            <p>ท่านมีสิทธิ์ตาม พ.ร.บ. PDPA ดังนี้:</p>
            <ul>
                <li><b>สิทธิ์ในการเข้าถึง:</b> ขอดูข้อมูลส่วนบุคคลของตนเองที่หน่วยงานเก็บรักษา</li>
                <li><b>สิทธิ์ในการแก้ไข:</b> ขอแก้ไขข้อมูลที่ไม่ถูกต้องหรือไม่สมบูรณ์</li>
                <li><b>สิทธิ์ในการลบ:</b> ขอให้ลบข้อมูลส่วนบุคคลเมื่อหมดความจำเป็น</li>
                <li><b>สิทธิ์ในการโอนย้าย:</b> ขอรับสำเนาข้อมูลในรูปแบบที่อ่านได้</li>
                <li><b>สิทธิ์ในการคัดค้าน:</b> คัดค้านการเก็บรวบรวมหรือใช้ข้อมูลบางประการ</li>
                <li><b>สิทธิ์ในการถอนความยินยอม:</b> ถอนความยินยอมที่เคยให้ไว้ได้ทุกเมื่อ</li>
            </ul>
            <div class="highlight-box">
                <p><i class="bi bi-hand-index me-2"></i>ท่านสามารถใช้สิทธิ์ได้โดยเข้าไปที่หน้า <a href="/Project2026/users/profile.php" class="fw-bold">โปรไฟล์ของฉัน</a> หรือติดต่อเจ้าหน้าที่ตามช่องทางด้านล่าง</p>
            </div>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-lock-fill"></i> 7. มาตรการรักษาความปลอดภัย</h2>
            <ul>
                <li>รหัสผ่านถูกเข้ารหัสด้วย bcrypt ก่อนจัดเก็บ</li>
                <li>ใช้ Prepared Statements เพื่อป้องกัน SQL Injection</li>
                <li>ข้อมูลนำเข้าถูกกรองด้วย htmlspecialchars เพื่อป้องกัน XSS</li>
                <li>ระบบบันทึก Audit Log ทุกการดำเนินการสำคัญ</li>
                <li>จำกัดสิทธิ์การเข้าถึงข้อมูลตามบทบาท (Role-Based Access Control)</li>
                <li>ใช้ Session ที่ปลอดภัยสำหรับการยืนยันตัวตน</li>
            </ul>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-cookie"></i> 8. คุกกี้ (Cookies)</h2>
            <p>ระบบใช้คุกกี้ที่จำเป็นสำหรับ:</p>
            <ul>
                <li><b>Session Cookie:</b> จัดการการเข้าสู่ระบบ (จำเป็น — ไม่สามารถปิดได้)</li>
            </ul>
            <p>ระบบ <b>ไม่ใช้</b> คุกกี้เพื่อการติดตามพฤติกรรมหรือการโฆษณา</p>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-pencil-square"></i> 9. การแก้ไขนโยบาย</h2>
            <p>
                หน่วยงานอาจปรับปรุงนโยบายฉบับนี้เป็นครั้งคราว
                หากมีการเปลี่ยนแปลงที่สำคัญ จะแจ้งให้ท่านทราบผ่านทางระบบหรือช่องทางที่เหมาะสม
            </p>
            <p class="text-muted small mb-0">ปรับปรุดล่าสุด: 2 มีนาคม 2569</p>
        </div>

        <div class="policy-card">
            <h2><i class="bi bi-headset"></i> 10. ช่องทางติดต่อ</h2>
            <div class="contact-pdpa">
                <p class="fw-bold mb-2"><i class="bi bi-building me-2"></i>เจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล (DPO)</p>
                <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>เทศบาลเมืองศิลา 722 หมู่ 14 ตำบลศิลา อำเภอเมืองขอนแก่น จังหวัดขอนแก่น 40000</p>
                <p class="mb-1"><i class="bi bi-telephone me-2"></i>043-246-505-6</p>
                <p class="mb-1"><i class="bi bi-envelope me-2"></i>saraban@sila-kk.go.th</p>
                <p class="mb-0"><i class="bi bi-clock me-2"></i>จันทร์-ศุกร์ 08:30 - 16:30 น.</p>
            </div>
        </div>
    </div>

    <?php include './includes/footer.php'; ?>
    <?php include './includes/scripts.php'; ?>
</body>

</html>
