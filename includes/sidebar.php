<script>if(localStorage.getItem('sidebarCollapsed')==='true')document.body.classList.add('sidebar-collapsed');</script>
<div class="sidebar">
    <div class="sidebar-main">
        <button type="button" class="sidebar-toggle-rail btn btn-outline-secondary btn-sm rounded-3" data-sidebar-toggle aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <a href="/Project2026/users/index.php" class="logo-link logo-full d-block text-center mb-4">
            <img src="/Project2026/image/logosila.png" alt="ทม.ศิลา" class="img-fluid rounded hover-lift"
                style="max-width: 150px;">
        </a>

        <div class="sidebar-menu">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- เมนูสำหรับผู้ดูแลระบบ (Admin) -->
            <a href="/Project2026/admin/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" title="ภาพรวมระบบ">
                <span class="menu-icon">📊</span><span class="menu-text">ภาพรวมระบบ</span>
            </a>
            <a href="/Project2026/admin/users_list.php"
                class="<?= in_array(basename($_SERVER['PHP_SELF']), ['users_list.php', 'add_user.php']) ? 'active' : '' ?>" title="จัดการผู้ใช้งาน">
                <span class="menu-icon">👥</span><span class="menu-text">จัดการผู้ใช้งาน</span>
            </a>

        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'employee'): ?>
            <!-- เมนูสำหรับเจ้าหน้าที่ (Employee) -->
            <a href="/Project2026/employee/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" title="ภาพรวมระบบ">
                <span class="menu-icon">📊</span><span class="menu-text">ภาพรวมระบบ</span>
            </a>
            <a href="/Project2026/employee/request_list.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/request_list.php') !== false) ? 'active' : '' ?>" title="รายการคำขอ">
                <span class="menu-icon">📝</span><span class="menu-text">รายการคำขอ</span>
            </a>
            <a href="/Project2026/employee/map.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/map.php') !== false) ? 'active' : '' ?>" title="แผนที่">
                <span class="menu-icon">🗺️</span><span class="menu-text">แผนที่</span>
            </a>
            <a href="/Project2026/employee/settings.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" title="ตั้งค่าใบเสร็จ">
                <span class="menu-icon">⚙️</span><span class="menu-text">ตั้งค่าใบเสร็จ</span>
            </a>
            <a href="/Project2026/employee/reports.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" title="รายงาน">
                <span class="menu-icon">📊</span><span class="menu-text">รายงาน</span>
            </a>
        <?php else: ?>
            <!-- เมนูสำหรับผู้ใช้งานทั่วไป (User) -->
            <a href="/Project2026/users/index.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" title="หน้าแรก">
                <span class="menu-icon">🏠</span><span class="menu-text">หน้าแรก</span>
            </a>
            <a href="/Project2026/users/request_form.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'request_form.php' ? 'active' : '' ?>" title="ยื่นคำขอ">
                <span class="menu-icon">📝</span><span class="menu-text">ยื่นคำขอ</span>
            </a>
            <a href="/Project2026/users/my_request.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'my_request.php' ? 'active' : '' ?>" title="สถานะคำขอ">
                <span class="menu-icon">📄</span><span class="menu-text">สถานะคำขอ</span>
            </a>
            <a href="/Project2026/map_public.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'map_public.php' ? 'active' : '' ?>" title="แผนที่">
                <span class="menu-icon">🗺️</span><span class="menu-text">แผนที่</span>
            </a>
        <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-bottom">
        <a href="#" title="ออกจากระบบ"
            onclick="confirmAction('ยืนยันออกจากระบบ', 'คุณต้องการออกจากระบบใช่หรือไม่?', 'ใช่, ออกจากระบบ', 'ยกเลิก', () => window.location.href='/Project2026/logout.php')">
            <span class="menu-icon">🚪</span><span class="menu-text">ออกจากระบบ</span>
        </a>
    </div>
</div>