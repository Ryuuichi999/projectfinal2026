<script>if(localStorage.getItem('sidebarCollapsed')==='true')document.body.classList.add('sidebar-collapsed');</script>
<div class="sidebar">
    <div class="sidebar-main">
        <button type="button" class="sidebar-toggle-rail btn btn-outline-secondary btn-sm rounded-3" data-sidebar-toggle aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <a href="<?= BASE_URL ?>/users/index.php" class="logo-link logo-full d-block text-center mb-4">
            <img src="<?= BASE_URL ?>/image/logosila.png" alt="ทม.ศิลา" class="img-fluid rounded hover-lift"
                style="max-width: 150px;">
        </a>

        <div class="sidebar-menu">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- เมนูสำหรับผู้ดูแลระบบ (Admin) -->
            <div class="sidebar-section-title">MENU</div>
            <a href="<?= BASE_URL ?>/admin/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" title="ภาพรวมระบบ">
                <span class="menu-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="menu-text">ภาพรวมระบบ</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/users_list.php"
                class="<?= in_array(basename($_SERVER['PHP_SELF']), ['users_list.php', 'add_user.php']) ? 'active' : '' ?>" title="จัดการผู้ใช้งาน">
                <span class="menu-icon"><i class="bi bi-people-fill"></i></span><span class="menu-text">จัดการผู้ใช้งาน</span>
            </a>

        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'employee'): ?>
            <!-- เมนูสำหรับเจ้าหน้าที่ (Employee) -->
            <div class="sidebar-section-title">MENU</div>
            <a href="<?= BASE_URL ?>/employee/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" title="ภาพรวมระบบ">
                <span class="menu-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="menu-text">ภาพรวมระบบ</span>
            </a>
            <a href="<?= BASE_URL ?>/employee/request_list.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/request_list.php') !== false) ? 'active' : '' ?>" title="รายการคำขอ">
                <span class="menu-icon"><i class="bi bi-file-earmark-text-fill"></i></span><span class="menu-text">รายการคำขอ</span>
            </a>
            <a href="<?= BASE_URL ?>/employee/map.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/map.php') !== false) ? 'active' : '' ?>" title="แผนที่">
                <span class="menu-icon"><i class="bi bi-map-fill"></i></span><span class="menu-text">แผนที่</span>
            </a>
            <a href="<?= BASE_URL ?>/employee/reports.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" title="รายงาน">
                <span class="menu-icon"><i class="bi bi-bar-chart-line-fill"></i></span><span class="menu-text">รายงาน</span>
            </a>
        <?php else: ?>
            <!-- เมนูสำหรับผู้ใช้งานทั่วไป (User) -->
            <div class="sidebar-section-title">MENU</div>
            <a href="<?= BASE_URL ?>/users/index.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" title="หน้าแรก">
                <span class="menu-icon"><i class="bi bi-house-door-fill"></i></span><span class="menu-text">หน้าแรก</span>
            </a>
            <a href="<?= BASE_URL ?>/users/request_form.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'request_form.php' ? 'active' : '' ?>" title="ยื่นคำขอ">
                <span class="menu-icon"><i class="bi bi-pencil-square"></i></span><span class="menu-text">ยื่นคำขอ</span>
            </a>
            <a href="<?= BASE_URL ?>/users/my_request.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'my_request.php' ? 'active' : '' ?>" title="สถานะคำขอ">
                <span class="menu-icon"><i class="bi bi-file-earmark-check-fill"></i></span><span class="menu-text">สถานะคำขอ</span>
            </a>
            <a href="<?= BASE_URL ?>/map_public.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'map_public.php' ? 'active' : '' ?>" title="แผนที่">
                <span class="menu-icon"><i class="bi bi-map-fill"></i></span><span class="menu-text">แผนที่</span>
            </a>
        <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-bottom">
        <div class="sidebar-section-title">OTHERS</div>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee'): ?>
        <a href="<?= BASE_URL ?>/employee/settings.php"
            class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" title="ตั้งค่าใบเสร็จ">
            <span class="menu-icon"><i class="bi bi-gear-fill"></i></span><span class="menu-text">ตั้งค่าใบเสร็จ</span>
        </a>
        <?php endif; ?>
        <a href="#" title="ออกจากระบบ"
            onclick="confirmAction('ยืนยันออกจากระบบ', 'คุณต้องการออกจากระบบใช่หรือไม่?', 'ออกจากระบบ', 'ยกเลิก', () => window.location.href='<?= BASE_URL ?>/logout.php')">
            <span class="menu-icon"><i class="bi bi-box-arrow-right"></i></span><span class="menu-text">ออกจากระบบ</span>
        </a>
    </div>
</div>