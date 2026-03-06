<script>if(localStorage.getItem('sidebarCollapsed')==='true')document.body.classList.add('sidebar-collapsed');</script>
<div class="sidebar">
    <div>
        <a href="/Project2026/users/index.php" class="logo-link d-block text-center mb-4">
            <img src="/Project2026/image/logosila.png" alt="ทม.ศิลา" class="img-fluid rounded hover-lift"
                style="max-width: 150px;">
        </a>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- เมนูสำหรับผู้ดูแลระบบ (Admin) -->
            <a href="/Project2026/admin/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                📊 ภาพรวมระบบ
            </a>
            <a href="/Project2026/admin/users_list.php"
                class="<?= in_array(basename($_SERVER['PHP_SELF']), ['users_list.php', 'add_user.php']) ? 'active' : '' ?>">
                👥 จัดการผู้ใช้งาน
            </a>

        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'employee'): ?>
            <!-- เมนูสำหรับเจ้าหน้าที่ (Employee) -->
            <a href="/Project2026/employee/dashboard.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                📊 ภาพรวมระบบ
            </a>
            <a href="/Project2026/employee/request_list.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/request_list.php') !== false) ? 'active' : '' ?>">
                📝 รายการคำขอ
            </a>
            <a href="/Project2026/employee/map.php"
                class="<?= (strpos($_SERVER['PHP_SELF'], 'employee/map.php') !== false) ? 'active' : '' ?>">
                🗺️ แผนที่
            </a>
            <a href="/Project2026/employee/settings.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                ⚙️ ตั้งค่าใบเสร็จ
            </a>
            <a href="/Project2026/employee/reports.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                📊 รายงาน
            </a>
        <?php else: ?>
            <!-- เมนูสำหรับผู้ใช้งานทั่วไป (User) -->
            <a href="/Project2026/users/index.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                🏠 หน้าแรก
            </a>
            <a href="/Project2026/users/request_form.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'request_form.php' ? 'active' : '' ?>">
                📝 ยื่นคำขอ
            </a>
            <a href="/Project2026/users/my_request.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'my_request.php' ? 'active' : '' ?>">
                📄 สถานะคำขอ
            </a>
            <a href="/Project2026/map_public.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'map_public.php' ? 'active' : '' ?>">
                🗺️ แผนที่
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-bottom">
        <a href="#"
            onclick="confirmAction('ยืนยันออกจากระบบ', 'คุณต้องการออกจากระบบใช่หรือไม่?', 'ใช่, ออกจากระบบ', 'ยกเลิก', () => window.location.href='/Project2026/logout.php')">
            🚪 ออกจากระบบ
        </a>
    </div>
</div>