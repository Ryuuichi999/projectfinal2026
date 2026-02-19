<?php
session_start();
require '../includes/db.php';
require '../includes/email_helper.php';
require '../includes/settings_helper.php';
require '../includes/permit_helper.php'; 
require_once '../includes/log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: request_list.php");
    exit;
}

$request_id = $_GET['id'];
$sql = "SELECT * FROM sign_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    die("Error: Request not found.");
}

// Check Status
if ($request['status'] !== 'waiting_permit' && $request['status'] !== 'waiting_receipt') {
    echo "<script>alert('ผิดพลาด: คำขอนี้ไม่ได้อยู่ในสถานะรอออกใบอนุญาต (สถานะปัจจุบัน: " . htmlspecialchars($request['status']) . ")'); window.location.href='request_list.php';</script>";
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

// 3. Handle Form Submission
if (isset($_POST['issue_permit_confirm'])) {
    $permit_no = $_POST['permit_no'];
    $permit_date = $_POST['permit_date'];
    $p_signer_name = $_POST['permit_signer_name'];
    $p_signer_pos = $_POST['permit_signer_position'];
    
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
        send_status_notification($request_id, $conn);
        
        // Redirect
        echo "<script>
            alert('บันทึกข้อมูลและออกใบอนุญาตเรียบร้อยแล้ว');
            window.location.href = 'request_list.php';
        </script>";
        exit;
    } else {
        $error = "Error updating record: " . $conn->error;
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
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="container-fluid px-4 mt-4">
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
                            <span class="ms-2 text-muted">(<?= htmlspecialchars($request['receipt_date'] ?? '-') ?>)</span>
                        </div>
                    </div>

                    <form method="post" id="issuePermitForm">
                        
                        <!-- Section 1: Permit Details -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i class="bi bi-1-circle me-1"></i>ข้อมูลใบอนุญาต</h6>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted">เลขที่ใบอนุญาต <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-hash"></i>
                                    </span>
                                    <input type="text" name="permit_no" class="form-control fw-bold fs-5 text-primary border-start-0 ps-0" 
                                           value="<?= htmlspecialchars($next_permit_no) ?>" required>
                                </div>
                                <div class="form-text">รูปแบบ: ลำดับที่/ปีพ.ศ. (เช่น 34/2568)</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted">วันที่ออกใบอนุญาต <span class="text-danger">*</span></label>
                                <input type="date" name="permit_date" class="form-control" 
                                       value="<?= $permit_date_default ?>" required>
                            </div>
                        </div>

                        <!-- Section 2: Signer Details -->
                        <h6 class="border-bottom pb-2 mb-3 text-secondary"><i class="bi bi-2-circle me-1"></i>ข้อมูลผู้ลงนาม (ปรากฏท้ายใบอนุญาต)</h6>
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <label class="form-label text-muted">ชื่อผู้ลงนาม <span class="text-danger">*</span></label>
                                <input type="text" name="permit_signer_name" class="form-control" 
                                       value="<?= htmlspecialchars($setting_signer_name) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted">ตำแหน่ง <span class="text-danger">*</span></label>
                                <input type="text" name="permit_signer_position" class="form-control" 
                                       value="<?= htmlspecialchars($setting_signer_pos) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted">ตัวอย่างลายเซ็น</label>
                                <div class="border p-2 bg-light rounded text-center position-relative" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                                    <?php if ($setting_sig_path && file_exists("../" . $setting_sig_path)): ?>
                                        <img src="../<?= $setting_sig_path ?>" style="max-height: 60px; max-width: 100%;" alt="Signature">
                                    <?php else: ?>
                                        <span class="text-muted small">ยังไม่มีลายเซ็น</span>
                                    <?php endif; ?>
                                    <a href="settings.php" target="_blank" class="position-absolute top-0 end-0 p-1 text-decoration-none" title="ไปที่ตั้งค่า">
                                        <i class="bi bi-gear-fill text-secondary"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-5 pt-3 border-top bg-light py-3 rounded">
                            <div class="col-12 text-end">
                                <a href="request_list.php" class="btn btn-secondary me-2">ยกเลิก</a>
                                <button type="button" class="btn btn-success px-4" onclick="confirmIssue()">
                                    <i class="bi bi-check-circle-fill"></i> ยืนยันและออกใบอนุญาต
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