<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="image/logosila.png" alt="Logo" style="height: 70px; width: auto; margin-top: -15px; margin-bottom: -15px; transition: 0.3s;" class="logo-pop">
            <span class="fw-bold fs-5 text-dark ms-2">เทศบาลเมืองศิลา</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto align-items-center gap-3 ms-lg-4">
                <li class="nav-item"><a class="nav-link active" href="index.php">หน้าหลัก</a></li>
                <li class="nav-item"><a class="nav-link" href="#steps">ขั้นตอน</a></li>
                <li class="nav-item"><a class="nav-link" href="#services">บริการ</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">ติดต่อเรา</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="users/index.php" class="nav-link fw-bold">หน้าจัดการ</a>
                    <a href="logout.php" class="btn btn-outline-danger px-4 rounded-pill">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link fw-bold">เข้าสู่ระบบ</a>
                    <a href="register.php" class="btn btn-primary-custom text-white px-4">ลงทะเบียน</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>