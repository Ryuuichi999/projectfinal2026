<footer id="contact">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5 class="fw-bold mb-3">เทศบาลเมืองศิลา</h5>
                <p class="opacity-75 small">ระบบบริหารจัดการคำร้องออนไลน์ เพื่อยกระดับการให้บริการประชาชน</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm rounded-circle"><i class="bi bi-line"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <h6 class="fw-bold mb-3">เมนู</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small">
                    <li><a href="index.php" class="footer-link">หน้าหลัก</a></li>
                    <li><a href="#services" class="footer-link">บริการ</a></li>
                    <li><a href="login.php" class="footer-link">เข้าสู่ระบบ</a></li>
                    <li><a href="<?= BASE_URL ?>/privacy_policy.php" class="footer-link"><i class="bi bi-shield-check me-1"></i>นโยบาย PDPA</a></li>
                </ul>
            </div>
            <div class="col-lg-6 col-12 ms-lg-auto">
                <h6 class="fw-bold mb-3">ติดต่อสอบถาม</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <ul class="list-unstyled d-flex flex-column gap-2 small opacity-75">
                            <li><i class="bi bi-geo-alt-fill me-2 text-primary"></i> <strong>ที่อยู่:</strong> 722 หมู่
                                14 ตำบลศิลา อำเภอเมืองขอนแก่น จังหวัดขอนแก่น 40000</li>
                            <li><i class="bi bi-telephone-fill me-2 text-primary"></i> <strong>โทรศัพท์:</strong>
                                043-246-505-6</li>
                            <li><i class="bi bi-clock-fill me-2 text-primary"></i> <strong>เวลาทำการ:</strong>
                                จันทร์-ศุกร์ 08:30 - 16:30 น.</li>
                            <li>
                                <i class="bi bi-envelope-at-fill me-2 text-primary"></i> <strong>Email:</strong>
                                <span class="ms-1">saraban@sila-kk.go.th (สารบรรณกลาง)</span>,
                                <span class="ms-1">contact@sila-kk.go.th (ติดต่อทั่วไป)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-top border-secondary mt-5 pt-4 text-center small opacity-50">
            &copy; 2026 เทศบาลเมืองศิลา. All rights reserved. |
            <a href="<?= BASE_URL ?>/privacy_policy.php" class="footer-link">นโยบายความเป็นส่วนตัว</a>
        </div>
    </div>
</footer>

<!-- Cookie Consent Banner -->
<div id="cookieConsent" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999; background:rgba(15,23,42,0.95); backdrop-filter:blur(10px); padding:16px 24px; box-shadow:0 -4px 20px rgba(0,0,0,0.15);">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 text-white" style="flex:1; min-width:280px;">
            <i class="bi bi-cookie" style="font-size:1.5rem; color:#f59e0b;"></i>
            <p class="mb-0 small" style="line-height:1.5;">เว็บไซต์นี้ใช้คุกกี้ที่จำเป็นสำหรับการทำงานของระบบเท่านั้น (Session Cookie) เราไม่ใช้คุกกี้เพื่อการติดตามหรือโฆษณา
                <a href="<?= BASE_URL ?>/privacy_policy.php" class="text-info text-decoration-none fw-semibold">อ่านนโยบาย PDPA</a>
            </p>
        </div>
        <button onclick="acceptCookies()" class="btn btn-primary btn-sm px-4 py-2 rounded-pill fw-semibold" style="white-space:nowrap;"><i class="bi bi-check-lg me-1"></i>ยอมรับ</button>
    </div>
</div>
<script>
(function() {
    if (!localStorage.getItem('cookie_consent')) {
        document.getElementById('cookieConsent').style.display = 'block';
    }
})();
function acceptCookies() {
    localStorage.setItem('cookie_consent', '1');
    document.getElementById('cookieConsent').style.display = 'none';
}
</script>