<?php
require '../includes/db.php';
require '../includes/email_helper.php';
require '../includes/settings_helper.php';
require '../includes/permit_helper.php';
require_once '../includes/log_helper.php';
require_once '../includes/csrf_helper.php';
require '../includes/thaibaht.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: request_list.php");
    exit;
}

$request_id = (int) $_GET['id'];
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name, u.citizen_id, u.address as user_address
        FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูลคำขอ';
    header('Location: request_list.php');
    exit;
}

// Check Status
if ($request['status'] !== 'waiting_permit' && $request['status'] !== 'waiting_receipt') {
    $_SESSION['flash_error'] = 'คำขอนี้ไม่ได้อยู่ในสถานะรอออกใบอนุญาต';
    header('Location: request_list.php');
    exit;
}

// 1. Ensure Columns Exist (Autofix DB)
ensurePermitColumnsExist($conn);
ensureSettingsTable($conn);

// 2. Prepare Defaults
// Use Request ID as Permit No (per user request)
$thYear = date('Y') + 543;
$next_permit_no = $request['id'] . '/' . $thYear;
// $next_permit_no = generateNextPermitNumber($conn); // Disabled: User prefers Request ID match
$permit_date_default = date('Y-m-d');

// Load Signer from Settings
$setting_signer_name = getSetting($conn, 'permit_signer_name', '');
$setting_signer_pos = getSetting($conn, 'permit_signer_position', '');
$setting_sig_path = getSetting($conn, 'permit_signature_path', '');

// Helper: Thai date for preview
function getThaiDatePreview($date) {
    if (!$date) return "....................";
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    $std  = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($std,$thai,$d)." $m ".str_replace($std,$thai,$y);
}
function toThaiNumPreview($number) {
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    $std  = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($std, $thai, $number);
}

// Preview data
$permit_start = !empty($request['install_date']) ? $request['install_date'] : $permit_date_default;
$permit_end   = !empty($request['end_date']) ? $request['end_date'] : date('Y-m-d', strtotime($permit_start . ' + ' . ($request['duration_days'] - 1) . ' days'));

// เตรียม base64 ลายเซ็นปัจจุบัน
$sig_base64 = '';
if ($setting_sig_path && file_exists("../" . $setting_sig_path)) {
    $sig_data = file_get_contents("../" . $setting_sig_path);
    $sig_mime = mime_content_type("../" . $setting_sig_path);
    $sig_base64 = 'data:' . $sig_mime . ';base64,' . base64_encode($sig_data);
}

// 3. Handle Form Submission
if (isset($_POST['issue_permit_confirm'])) {
    csrf_check();
    $permit_no = $_POST['permit_no'];
    $permit_date = $_POST['permit_date'];
    $p_signer_name = $_POST['permit_signer_name'];
    $p_signer_pos = $_POST['permit_signer_position'];

    // จัดการลายเซ็นที่วาดมา (ถ้ามี)
    if (!empty($_POST['drawn_signature'])) {
        $sig_data_url = $_POST['drawn_signature'];
        if (preg_match('/^data:image\/(png|jpeg);base64,/', $sig_data_url, $matches)) {
            $ext = $matches[1] === 'jpeg' ? 'jpg' : 'png';
            $sig_binary = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $sig_data_url));
            $upload_dir = '../uploads/signatures/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
            $new_filename = 'permit_sig_drawn_' . time() . '.' . $ext;
            $dest = $upload_dir . $new_filename;
            file_put_contents($dest, $sig_binary);
            $db_val = 'uploads/signatures/' . $new_filename;
            updateSetting($conn, 'permit_signature_path', $db_val);
        }
    }

    // Update DB
    $update_sql = "UPDATE sign_requests 
                   SET status = 'approved', 
                       permit_no = ?, 
                       permit_date = ?,
                       permit_signer_name = ?,
                       permit_signer_position = ?,
                       approved_by = ?
                   WHERE id = ?";
    $stmt_up = $conn->prepare($update_sql);
    $stmt_up->bind_param("ssssii", $permit_no, $permit_date, $p_signer_name, $p_signer_pos, $_SESSION['user_id'], $request_id);

    if ($stmt_up->execute()) {
        // Log
        logRequestAction($conn, $request_id, 'approved', 'ออกใบอนุญาตและอนุมัติ', $_SESSION['user_id'], "เลขที่ใบอนุญาต: $permit_no");

        // Send Email
        queue_status_notification($request_id, $conn);

        $_SESSION['flash_success'] = 'ออกใบอนุญาตสำเร็จ';
        header('Location: request_list.php');
        exit;
    } else {
        $error = "เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่อีกครั้ง";
    }
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ออกใบอนุญาต (Issue Permit)</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .btn-back {
            color: #64748b;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            font-weight: 500;
        }
        .btn-back:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="container-fluid px-4 mt-3">
            <nav class="breadcrumb-nav mb-2">
                <a href="dashboard.php"><i class="bi bi-house-door me-1"></i>หน้าหลัก</a>
                <span class="breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
                <a href="request_detail.php?id=<?= $request_id ?>">รายละเอียดคำร้อง</a>
                <span class="breadcrumb-sep"><i class="bi bi-chevron-right"></i></span>
                <span class="breadcrumb-current">ออกใบอนุญาต</span>
            </nav>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i>ออกใบอนุญาต (Issue Permit)</h5>
                </div>

                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <!-- Info Alert -->
                    <div class="alert alert-light border d-flex align-items-center mb-4">
                        <i class="bi bi-info-circle-fill text-primary me-3 fs-4"></i>
                        <div>
                            <span class="text-muted small">อ้างอิงการชำระเงิน</span><br>
                            <strong>ใบเสร็จเลขที่: <?= htmlspecialchars($request['receipt_no'] ?? '-') ?></strong>
                            <span
                                class="ms-2 text-muted">(<?= htmlspecialchars($request['receipt_date'] ?? '-') ?>)</span>
                        </div>
                    </div>

                    <form method="post" id="issuePermitForm">
                        <?= csrf_field() ?>

                        <!-- Section 1: Permit Details -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i
                                class="bi bi-1-circle me-1"></i>ข้อมูลใบอนุญาต</h6>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted">เลขที่ใบอนุญาต <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-hash"></i>
                                    </span>
                                    <input type="text" name="permit_no"
                                        class="form-control fw-bold fs-5 text-black border-start-0 ps-0"
                                        value="<?= htmlspecialchars($next_permit_no) ?>" required>
                                </div>
                                <div class="form-text">รูปแบบ: ลำดับที่/ปีพ.ศ. (เช่น 34/2568)</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted">วันที่ออกใบอนุญาต <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="permit_date" class="form-control"
                                    value="<?= $permit_date_default ?>" required>
                            </div>
                        </div>

                        <!-- Section 2: Signer Details -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i
                                class="bi bi-2-circle me-1"></i>ข้อมูลผู้ลงนาม (ปรากฏท้ายใบอนุญาต)</h6>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted">ชื่อผู้ลงนาม <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="permit_signer_name" id="inp_signer_name" class="form-control"
                                    value="<?= htmlspecialchars($setting_signer_name) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted">ตำแหน่ง <span class="text-danger">*</span></label>
                                <input type="text" name="permit_signer_position" id="inp_signer_pos" class="form-control"
                                    value="<?= htmlspecialchars($setting_signer_pos) ?>" required>
                            </div>
                        </div>

                        <!-- Section 3: Signature -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i class="bi bi-3-circle me-1"></i>ลายเซ็น</h6>
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="btn-group mb-3" role="group">
                                    <input type="radio" class="btn-check" name="sig_mode" id="sigModeExisting" value="existing" checked>
                                    <label class="btn btn-outline-secondary btn-sm" for="sigModeExisting"><i class="bi bi-image me-1"></i>ใช้ลายเซ็นที่มีอยู่</label>
                                    <input type="radio" class="btn-check" name="sig_mode" id="sigModeDraw" value="draw">
                                    <label class="btn btn-outline-secondary btn-sm" for="sigModeDraw"><i class="bi bi-pencil me-1"></i>วาดลายเซ็นใหม่</label>
                                    <input type="radio" class="btn-check" name="sig_mode" id="sigModeUpload" value="upload">
                                    <label class="btn btn-outline-secondary btn-sm" for="sigModeUpload"><i class="bi bi-upload me-1"></i>อัปโหลดรูปลายเซ็น</label>
                                </div>

                                <!-- Existing Signature -->
                                <div id="sigExistingPanel">
                                    <div class="border p-3 bg-light rounded text-center" style="min-height:100px; display:flex; align-items:center; justify-content:center;">
                                        <?php if ($sig_base64): ?>
                                            <img src="<?= $sig_base64 ?>" style="max-height:80px;" alt="ลายเซ็นปัจจุบัน" id="existingSigImg">
                                        <?php else: ?>
                                            <span class="text-muted">ยังไม่มีลายเซ็น — กรุณาวาดหรืออัปโหลด</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Draw Signature -->
                                <div id="sigDrawPanel" class="d-none">
                                    <div class="border rounded bg-white position-relative" style="max-width:500px;">
                                        <canvas id="sigCanvas" width="500" height="160" style="width:100%; cursor:crosshair; touch-action:none;"></canvas>
                                    </div>
                                    <div class="mt-2 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearSignature()"><i class="bi bi-eraser me-1"></i>ล้าง</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="undoSignature()"><i class="bi bi-arrow-counterclockwise me-1"></i>ย้อนกลับ</button>
                                        <div class="ms-auto d-flex align-items-center gap-2">
                                            <label class="small text-muted mb-0">ความหนาเส้น:</label>
                                            <input type="range" min="1" max="5" value="2" id="penSize" style="width:80px;" oninput="updatePenSize(this.value)">
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Signature -->
                                <div id="sigUploadPanel" class="d-none">
                                    <input type="file" id="sigUploadFile" class="form-control" accept="image/*" onchange="previewUploadedSig(this)">
                                    <div class="mt-2 border p-2 bg-light rounded text-center" style="min-height:80px; display:flex; align-items:center; justify-content:center;">
                                        <img id="sigUploadPreview" style="max-height:80px; display:none;" alt="Preview">
                                        <span id="sigUploadPlaceholder" class="text-muted small">เลือกไฟล์ลายเซ็น...</span>
                                    </div>
                                </div>

                                <input type="hidden" name="drawn_signature" id="drawnSignatureData">
                            </div>
                        </div>

                        <!-- Section 4: Preview -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i class="bi bi-4-circle me-1"></i>ตัวอย่างใบอนุญาต (Preview)</h6>
                        <div class="border rounded p-3 bg-white mb-4" style="overflow-x:auto;">
                            <div id="permitPreview" style="width:600px; margin:0 auto; font-family:'Sarabun',sans-serif; font-size:11pt; line-height:1.85; padding:30px 50px; border:1px solid #ddd; background:#fff; position:relative;">
                                <div style="position:absolute; top:30px; right:50px; font-size:10pt;">แบบ ร.ส. ๒</div>
                                <div style="text-align:center;">
                                    <img src="../image/ตราครุฑ.png" style="width:70px;" alt="Garuda">
                                    <div style="font-size:14pt; font-weight:bold; margin:4px 0;">หนังสืออนุญาต</div>
                                </div>
                                <div style="text-align:right; margin:6px 0 16px;">
                                    เลขที่ <span id="pvPermitNo"><?= toThaiNumPreview(htmlspecialchars($next_permit_no)) ?></span>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <span>๑.</span>
                                    อนุญาตให้ <strong><?= htmlspecialchars($request['applicant_name']) ?></strong>
                                    อยู่บ้านเลขที่ <strong><?= toThaiNumPreview(htmlspecialchars($request['applicant_address'])) ?></strong>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <span>๒.</span>
                                    โฆษณาด้วยการปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา ได้ ณ ที่<br>
                                    <span style="margin-left:24px;">ตำบล ศิลา อำเภอ เมืองขอนแก่น จังหวัด ขอนแก่น</span><br>
                                    <span style="margin-left:24px;">ข้อความ <strong><?= htmlspecialchars($request['description']) ?></strong>
                                    (<?= htmlspecialchars($request['road_name']) ?>)
                                    จำนวน <strong><?= toThaiNumPreview($request['quantity']) ?></strong> ป้าย</span>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <span>๓.</span>
                                    ตั้งแต่วันที่ <strong><?= getThaiDatePreview($permit_start) ?></strong>
                                    ถึง วันที่ <strong><?= getThaiDatePreview($permit_end) ?></strong><br>
                                    <span style="margin-left:24px;">รวมกำหนดเวลาอนุญาต <strong><?= toThaiNumPreview($request['duration_days']) ?></strong> วัน</span>
                                </div>
                                <div style="margin-bottom:10px;">
                                    <span>๔.</span>
                                    ได้รับค่าธรรมเนียม จำนวน <strong><?= toThaiNumPreview(number_format($request['fee'], 0)) ?></strong> บาท
                                    (<?= ThaiBahtConversion($request['fee']) ?>)
                                </div>
                                <div style="margin-bottom:10px;">
                                    <span>๕.</span>
                                    หนังสืออนุญาตนี้ให้ไว้ ณ วันที่ <strong id="pvPermitDate"><?= getThaiDatePreview($permit_date_default) ?></strong>
                                </div>
                                <div style="margin-top:30px; text-align:right; padding-right:20px;">
                                    <div style="display:inline-block; text-align:center; min-width:200px;">
                                        <div id="pvSigImg" style="height:60px; display:flex; align-items:center; justify-content:center;">
                                            <?php if ($sig_base64): ?>
                                                <img src="<?= $sig_base64 ?>" style="max-height:55px;" id="previewSigImage">
                                            <?php else: ?>
                                                <span class="text-muted small" id="previewSigPlaceholder">(ลายเซ็น)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div id="pvSignerName">(<?= htmlspecialchars($setting_signer_name) ?>)</div>
                                        <div id="pvSignerPos"><?= htmlspecialchars($setting_signer_pos) ?></div>
                                        <div style="font-size:9pt;">หรือพนักงานเจ้าหน้าที่ผู้ออกหนังสืออนุญาต</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4 pt-3 border-top bg-light py-3 rounded">
                            <div class="col-12 text-end">
                                <a href="request_list.php" class="btn btn-secondary me-2">ยกเลิก</a>
                                <button type="button" class="btn btn-success px-4" onclick="confirmIssue()">
                                    <i class="bi bi-check-circle-fill"></i> ยืนยันออกใบอนุญาต
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="issue_permit_confirm" value="1">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
    // === Signature Pad ===
    var canvas = document.getElementById('sigCanvas');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var paths = [];
    var currentPath = [];
    var penWidth = 2;

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);
    canvas.addEventListener('touchstart', function(e){ e.preventDefault(); startDraw(getTouchPos(e)); }, {passive:false});
    canvas.addEventListener('touchmove', function(e){ e.preventDefault(); draw(getTouchPos(e)); }, {passive:false});
    canvas.addEventListener('touchend', function(e){ e.preventDefault(); stopDraw(); }, {passive:false});

    function getTouchPos(e) {
        var rect = canvas.getBoundingClientRect();
        var touch = e.touches[0];
        return { offsetX: touch.clientX - rect.left, offsetY: touch.clientY - rect.top };
    }
    function startDraw(e) {
        drawing = true;
        currentPath = [{x: e.offsetX, y: e.offsetY, w: penWidth}];
        ctx.beginPath();
        ctx.moveTo(e.offsetX, e.offsetY);
    }
    function draw(e) {
        if (!drawing) return;
        ctx.lineWidth = penWidth;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
        currentPath.push({x: e.offsetX, y: e.offsetY, w: penWidth});
    }
    function stopDraw() {
        if (drawing && currentPath.length > 0) {
            paths.push(currentPath);
        }
        drawing = false;
        updateDrawnPreview();
    }
    function clearSignature() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        paths = [];
        currentPath = [];
        updateDrawnPreview();
    }
    function undoSignature() {
        paths.pop();
        redrawCanvas();
        updateDrawnPreview();
    }
    function redrawCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        paths.forEach(function(path) {
            if (path.length < 1) return;
            ctx.beginPath();
            ctx.moveTo(path[0].x, path[0].y);
            for (var i = 1; i < path.length; i++) {
                ctx.lineWidth = path[i].w;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#000';
                ctx.lineTo(path[i].x, path[i].y);
            }
            ctx.stroke();
        });
    }
    function updatePenSize(val) { penWidth = parseInt(val); }

    function updateDrawnPreview() {
        var mode = document.querySelector('input[name="sig_mode"]:checked').value;
        var pvSig = document.getElementById('pvSigImg');
        if (mode === 'draw' && paths.length > 0) {
            var dataUrl = canvas.toDataURL('image/png');
            pvSig.innerHTML = '<img src="'+dataUrl+'" style="max-height:55px;">';
            document.getElementById('drawnSignatureData').value = dataUrl;
        } else if (mode === 'draw') {
            pvSig.innerHTML = '<span class="text-muted small">(ลายเซ็น)</span>';
            document.getElementById('drawnSignatureData').value = '';
        }
    }

    // === Signature Mode Toggle ===
    document.querySelectorAll('input[name="sig_mode"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('sigExistingPanel').classList.toggle('d-none', this.value !== 'existing');
            document.getElementById('sigDrawPanel').classList.toggle('d-none', this.value !== 'draw');
            document.getElementById('sigUploadPanel').classList.toggle('d-none', this.value !== 'upload');

            var pvSig = document.getElementById('pvSigImg');
            if (this.value === 'existing') {
                var existImg = document.getElementById('existingSigImg');
                pvSig.innerHTML = existImg ? '<img src="'+existImg.src+'" style="max-height:55px;">' : '<span class="text-muted small">(ลายเซ็น)</span>';
                document.getElementById('drawnSignatureData').value = '';
            } else if (this.value === 'draw') {
                updateDrawnPreview();
            } else if (this.value === 'upload') {
                var upImg = document.getElementById('sigUploadPreview');
                if (upImg && upImg.style.display !== 'none') {
                    pvSig.innerHTML = '<img src="'+upImg.src+'" style="max-height:55px;">';
                    document.getElementById('drawnSignatureData').value = upImg.src;
                } else {
                    pvSig.innerHTML = '<span class="text-muted small">(ลายเซ็น)</span>';
                }
            }
        });
    });

    function previewUploadedSig(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.getElementById('sigUploadPreview');
                img.src = e.target.result;
                img.style.display = 'block';
                document.getElementById('sigUploadPlaceholder').style.display = 'none';
                document.getElementById('pvSigImg').innerHTML = '<img src="'+e.target.result+'" style="max-height:55px;">';
                document.getElementById('drawnSignatureData').value = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // === Live Preview Updates ===
    document.querySelector('input[name="permit_no"]').addEventListener('input', function() {
        document.getElementById('pvPermitNo').textContent = this.value;
    });
    document.getElementById('inp_signer_name').addEventListener('input', function() {
        document.getElementById('pvSignerName').textContent = '(' + this.value + ')';
    });
    document.getElementById('inp_signer_pos').addEventListener('input', function() {
        document.getElementById('pvSignerPos').textContent = this.value;
    });
    document.querySelector('input[name="permit_date"]').addEventListener('change', function() {
        var d = new Date(this.value);
        if (isNaN(d)) return;
        var thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        var thaiDigits = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
        function toThai(n) { return String(n).split('').map(c => thaiDigits[parseInt(c)] || c).join(''); }
        var txt = toThai(d.getDate()) + ' ' + thaiMonths[d.getMonth()] + ' ' + toThai(d.getFullYear() + 543);
        document.getElementById('pvPermitDate').textContent = txt;
    });

    // === Confirm Issue ===
    function confirmIssue() {
        Swal.fire({
            title: 'ยืนยันการออกใบอนุญาต?',
            html: "เมื่อบันทึกแล้ว สถานะจะเปลี่ยนเป็น <b>'อนุมัติแล้ว'</b><br>และระบบจะส่งอีเมลแจ้งผู้ยื่นคำขอทันที",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยันการอนุมัติ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('issuePermitForm').submit();
            }
        });
    }
    </script>
</body>

</html>