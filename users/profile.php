<?php
require '../includes/db.php';
require_once '../includes/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูลผู้ใช้';
    header('Location: ../login.php');
    exit;
}

// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['update_profile'])) {
        $title = trim($_POST['title_name'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($first) || empty($last)) {
            $message = "กรุณากรอกชื่อและนามสกุล";
            $message_type = 'danger';
        } else {
            $sql_up = "UPDATE users SET title_name=?, first_name=?, last_name=?, phone=?, email=?, address=? WHERE id=?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("ssssssi", $title, $first, $last, $phone, $email, $address, $user_id);
            if ($stmt_up->execute()) {
                $message = "บันทึกข้อมูลเรียบร้อยแล้ว";
                $message_type = 'success';
                $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $user = $stmt2->get_result()->fetch_assoc();
            } else {
                $message = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
                $message_type = 'danger';
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new_pass) || empty($confirm)) {
            $message = "กรุณากรอกรหัสผ่านให้ครบทุกช่อง";
            $message_type = 'danger';
        } elseif ($new_pass !== $confirm) {
            $message = "รหัสผ่านใหม่ไม่ตรงกัน";
            $message_type = 'danger';
        } elseif (strlen($new_pass) < 8 || !preg_match('/[0-9]/', $new_pass) || !preg_match('/[a-zA-Z]/', $new_pass)) {
            $message = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร และมีทั้งตัวอักษรและตัวเลข";
            $message_type = 'danger';
        } elseif (!password_verify($current, $user['password'])) {
            $message = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
            $message_type = 'danger';
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_pw = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pw->bind_param("si", $hashed, $user_id);
            if ($stmt_pw->execute()) {
                $message = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
                $message_type = 'success';
            } else {
                $message = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
                $message_type = 'danger';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ของฉัน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:    #1a56db;
            --primary-dark:#1a4fa0;
            --primary-light:#3b82f6;
            --accent:     #0d6efd;
            --ivory:      #f8fafc;
            --white:      #ffffff;
            --border:     #e2e8f0;
            --text-main:  #1e293b;
            --text-muted: #64748b;
            --success:    #16a34a;
            --danger:     #dc2626;
            --success-bg: #f0fdf4;
            --danger-bg:  #fef2f2;
        }

        body {
            font-family: 'Sarabun', 'IBM Plex Sans Thai', sans-serif;
            background-color: #eef1f6;
            color: var(--text-main);
        }

        /* ─── Page wrapper ─── */
        .profile-wrapper {
            max-width: 860px;
            margin: 2.5rem auto 4rem;
            padding: 0 1rem;
            animation: fadeUp .45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── Header banner ─── */
        .profile-banner {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 14px 14px 0 0;
            padding: 2.2rem 2.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 1.6rem;
            position: relative;
            overflow: hidden;
        }

        .profile-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0.1) 100%);
            pointer-events: none;
        }

        /* Monogram avatar */
        .profile-monogram {
            flex-shrink: 0;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 2.5px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }

        .profile-meta { position: relative; z-index: 1; }

        .profile-meta .label {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin-bottom: .3rem;
        }

        .profile-meta h2 {
            margin: 0 0 .25rem;
            font-size: 1.45rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.25;
        }

        .profile-meta .id-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: .2rem .75rem;
            font-size: .8rem;
            color: rgba(255,255,255,0.7);
        }

        .profile-meta .id-badge svg {
            opacity: .6;
        }

        /* ─── Tab navigation ─── */
        .profile-tabs {
            background: var(--white);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
            display: flex;
            gap: 0;
        }

        .profile-tabs a {
            flex: 1;
            text-align: center;
            padding: .9rem 1rem;
            font-size: .9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: color .2s, border-color .2s, background .2s;
            letter-spacing: .03em;
        }

        .profile-tabs a.active,
        .profile-tabs a[data-active="true"] {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: #f0f4ff;
        }

        .profile-tabs a:hover:not(.active) {
            color: var(--primary-dark);
            background: #f8fafc;
        }

        /* ─── Tab content card ─── */
        .tab-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 14px 14px;
            padding: 2.2rem 2.5rem 2.5rem;
        }

        /* Section divider label */
        .section-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 1.2rem;
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--border);
        }

        /* ─── Form fields ─── */
        .form-label {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: .4rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            display: block;
        }

        .form-label .req { color: #c0392b; margin-left: 2px; }

        /* Uniform field height via fixed line-height + padding */
        .form-control,
        .form-select {
            height: 46px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0 1rem;
            font-size: .92rem;
            font-family: 'Sarabun', sans-serif;
            color: var(--text-main);
            background-color: #fff;
            transition: border-color .2s, box-shadow .2s, background .2s;
            line-height: 46px;
            box-shadow: none;
            width: 100%;
            display: block;
        }

        /* textarea overrides height */
        textarea.form-control {
            height: auto;
            min-height: 80px;
            line-height: 1.55;
            padding: .7rem 1rem;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0d6efd;
            background-color: var(--white);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            outline: none;
        }

        .form-control:disabled {
            background: #f1efe9;
            color: var(--text-muted);
            border-color: #ddd8cc;
            cursor: not-allowed;
        }

        /* Field group: label + input stacked uniformly */
        .field-group {
            display: flex;
            flex-direction: column;
        }

        .hint-text {
            font-size: .74rem;
            color: var(--text-muted);
            margin-top: .35rem;
            line-height: 1.5;
        }

        /* ─── Alert messages ─── */
        .profile-alert {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            border-radius: 9px;
            padding: .9rem 1.1rem;
            font-size: .88rem;
            font-weight: 500;
            margin-bottom: 1.6rem;
            border-left: 4px solid;
            animation: fadeUp .3s ease both;
        }

        .profile-alert.success {
            background: var(--success-bg);
            border-color: var(--success);
            color: var(--success);
        }

        .profile-alert.danger {
            background: var(--danger-bg);
            border-color: var(--danger);
            color: var(--danger);
        }

        .profile-alert .alert-icon { font-size: 1.1rem; flex-shrink: 0; }

        /* ─── Buttons ─── */
        .btn-form-cancel {
            background: transparent;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: .55rem 1.4rem;
            font-size: .88rem;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            color: var(--text-muted);
            text-decoration: none;
            transition: border-color .2s, color .2s, background .2s;
        }

        .btn-form-cancel:hover {
            border-color: #aaa;
            color: var(--text-main);
            background: #fafafa;
        }

        .btn-form-primary {
            background: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 8px;
            padding: .55rem 1.6rem;
            font-size: .88rem;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            color: #fff;
            cursor: pointer;
            transition: background .2s, border-color .2s, transform .1s;
            letter-spacing: .03em;
        }

        .btn-form-primary:hover {
            background: #0d47a1;
            border-color: #0d47a1;
            transform: translateY(-1px);
        }

        .btn-form-primary:active { transform: translateY(0); }

        /* ─── Password strength bar ─── */
        .strength-bar {
            height: 4px;
            border-radius: 4px;
            background: #e5e2db;
            margin-top: .5rem;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: width .35s ease, background .35s ease;
        }

        .strength-label {
            font-size: .73rem;
            color: var(--text-muted);
            margin-top: .25rem;
        }

        /* ─── Locked field row ─── */
        .locked-field {
            position: relative;
        }

        .locked-field .lock-icon {
            position: absolute;
            right: .85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: .85rem;
            pointer-events: none;
        }

        /* ─── Back Button ─── */
        .btn-back {
            color: #64748b;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #334155;
        }

        /* ─── Responsive ─── */
        @media (max-width: 640px) {
            .profile-banner { padding: 1.5rem; flex-direction: column; text-align: center; }
            .tab-card { padding: 1.5rem 1.2rem 2rem; }
            [style*="grid-template-columns"] {
                display: flex !important;
                flex-direction: column !important;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/user_navbar.php'; ?>

    <div class="profile-wrapper">
        <!-- Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="index.php"><i class="bi bi-house-door me-1"></i>หน้าหลัก</a>
            <span class="breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
            <span class="breadcrumb-current">โปรไฟล์</span>
        </nav>

        <!-- ── Banner ── -->
        <div class="profile-banner">
            <div class="profile-monogram">
                <?= mb_substr($user['first_name'], 0, 1, 'UTF-8') . mb_substr($user['last_name'], 0, 1, 'UTF-8') ?>
            </div>
            <div class="profile-meta">
                <div class="label">บัญชีผู้ใช้งาน</div>
                <h2><?= htmlspecialchars($user['title_name'] . ' ' . $user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <span class="id-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
                    <?= htmlspecialchars($user['citizen_id']) ?>
                </span>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <div class="profile-tabs" id="profileTabNav">
            <a href="#" class="active" data-tab="profile-tab" onclick="switchTab(event,'profile-tab')">
                ข้อมูลส่วนตัว
            </a>
            <a href="#" data-tab="password-tab" onclick="switchTab(event,'password-tab')">
                เปลี่ยนรหัสผ่าน
            </a>
        </div>

        <!-- ── Tab content ── -->
        <div class="tab-card">

            <?php if ($message): ?>
                <div class="profile-alert <?= $message_type === 'success' ? 'success' : 'danger' ?>">
                    <span class="alert-icon"><?= $message_type === 'success' ? '✔' : '⚠' ?></span>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Tab: ข้อมูลส่วนตัว -->
            <div id="profile-tab" class="tab-pane">
                <div class="section-label">ข้อมูลทั่วไป</div>
                <form method="POST">
                    <?= csrf_field() ?>

                    <!-- Row 1: คำนำหน้า · ชื่อ · นามสกุล -->
                    <div style="display:grid; grid-template-columns: 140px 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="field-group">
                            <label class="form-label">คำนำหน้า</label>
                            <select name="title_name" class="form-select">
                                <?php
                                $titles = ['นาย', 'นาง', 'นางสาว'];
                                foreach ($titles as $t) {
                                    $sel = ($user['title_name'] === $t) ? 'selected' : '';
                                    echo "<option $sel>$t</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label class="form-label">ชื่อ <span class="req">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                value="<?= htmlspecialchars($user['first_name']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label class="form-label">นามสกุล <span class="req">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                value="<?= htmlspecialchars($user['last_name']) ?>" required>
                        </div>
                    </div>

                    <!-- Row 2: เบอร์โทร · อีเมล -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="field-group">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" class="form-control"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="field-group">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-control"
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Row 3: ที่อยู่ -->
                    <div class="field-group" style="margin-bottom: 1rem;">
                        <label class="form-label">ที่อยู่</label>
                        <textarea name="address" class="form-control"
                            rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <!-- Row 4: เลขบัตรประชาชน -->
                    <div class="field-group" style="margin-bottom: .25rem;">
                        <label class="form-label">เลขบัตรประชาชน</label>
                        <div class="locked-field">
                            <input type="text" class="form-control" disabled
                                value="<?= htmlspecialchars($user['citizen_id']) ?>">
                            <span class="lock-icon">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                        </div>
                        <p class="hint-text">เลขบัตรประชาชนไม่สามารถแก้ไขได้</p>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn-form-cancel">ยกเลิก</a>
                        <button type="submit" name="update_profile" class="btn-form-primary">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>

            <!-- Tab: เปลี่ยนรหัสผ่าน -->
            <div id="password-tab" class="tab-pane" style="display:none;">
                <div class="section-label">ความปลอดภัยของบัญชี</div>
                <form method="POST">
                    <?= csrf_field() ?>

                    <!-- รหัสผ่านปัจจุบัน full width -->
                    <div class="field-group" style="margin-bottom: 1rem;">
                        <label class="form-label">รหัสผ่านปัจจุบัน <span class="req">*</span></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <!-- รหัสผ่านใหม่ · ยืนยัน side by side -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="field-group">
                            <label class="form-label">รหัสผ่านใหม่ <span class="req">*</span></label>
                            <input type="password" name="new_password" id="newPassInput" class="form-control" minlength="8" required
                                oninput="evalStrength(this.value)">
                            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
                            <div class="strength-label" id="strengthLabel"></div>
                            <p class="hint-text">อย่างน้อย 8 ตัวอักษร · ต้องมีทั้งตัวอักษรและตัวเลข</p>
                        </div>
                        <div class="field-group">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่ <span class="req">*</span></label>
                            <input type="password" name="confirm_password" id="confirmPassInput" class="form-control" required
                                oninput="checkMatch()">
                            <p class="hint-text" id="matchHint" style="display:none;color:#8b1a1a;">รหัสผ่านไม่ตรงกัน</p>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn-form-cancel">ยกเลิก</a>
                        <button type="submit" name="change_password" class="btn-form-primary">เปลี่ยนรหัสผ่าน</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>

    <script>
        // ── Tab switching ──
        function switchTab(e, tabId) {
            e.preventDefault();
            document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
            document.getElementById(tabId).style.display = 'block';
            document.querySelectorAll('#profileTabNav a').forEach(a => a.classList.remove('active'));
            e.currentTarget.classList.add('active');
        }

        // ── Password strength ──
        function evalStrength(val) {
            const fill = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { pct: '0%',   color: '#e5e2db', text: '' },
                { pct: '30%',  color: '#c0392b', text: 'อ่อนมาก' },
                { pct: '55%',  color: '#e67e22', text: 'พอใช้' },
                { pct: '80%',  color: '#27ae60', text: 'ดี' },
                { pct: '100%', color: '#1a6b3c', text: 'แข็งแกร่ง' },
            ];
            const lv = val.length === 0 ? levels[0] : levels[score] || levels[1];
            fill.style.width = lv.pct;
            fill.style.background = lv.color;
            label.textContent = lv.text;
            label.style.color = lv.color;
        }

        // ── Confirm match ──
        function checkMatch() {
            const a = document.getElementById('newPassInput').value;
            const b = document.getElementById('confirmPassInput').value;
            const hint = document.getElementById('matchHint');
            hint.style.display = (b.length > 0 && a !== b) ? 'block' : 'none';
        }

        // ── Auto-open correct tab if message returned from password form ──
        <?php if ($message && isset($_POST['change_password'])): ?>
        document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
        document.getElementById('password-tab').style.display = 'block';
        document.querySelectorAll('#profileTabNav a').forEach(a => a.classList.remove('active'));
        document.querySelector('[data-tab="password-tab"]').classList.add('active');
        <?php endif; ?>
    </script>
</body>

</html>