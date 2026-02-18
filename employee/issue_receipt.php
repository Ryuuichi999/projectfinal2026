<?php
session_start();
require '../includes/db.php';
require '../includes/email_helper.php';
require '../includes/receipt_helper.php';
require '../includes/settings_helper.php';
require_once '../includes/log_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}
// ... (lines 9-47 skipped)
// Ensure columns exist (via helper)
ensureReceiptColumnsExist($conn);
ensureSettingsTable($conn); // Lazy init

// ดึงชื่อผู้ลงนาม (Official Signer) จากการตั้งค่า
$issuer_name_default = getSetting($conn, 'receipt_signer_name', '');

// ถ้าไม่ได้ตั้งค่าไว้ ให้ใช้ชื่อพนักงานที่ login อยู่
if (empty($issuer_name_default)) {
    $stmt_emp = $conn->prepare("SELECT title_name, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $stmt_emp->bind_param("i", $_SESSION['user_id']);
    $stmt_emp->execute();
    $emp = $stmt_emp->get_result()->fetch_assoc();
    if ($emp) {
        $issuer_name_default = trim(($emp['title_name'] ?? '') . ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
    }
}

// ตรวจสอบว่าสถานะเป็น waiting_receipt
if ($request['status'] !== 'waiting_receipt') {
    echo "<script>alert('คำขอนี้ไม่ได้อยู่ในสถานะรอออกใบเสร็จ (สถานะปัจจุบัน: {$request['status']})'); window.location.href='request_list.php';</script>";
    exit;
}

// Auto-generate Receipt Number
$receipt_number = generateNextReceiptNumber($conn);

if (isset($_POST['issue_receipt_confirm'])) {
    $receipt_no = $_POST['receipt_no'];
    $receipt_date = $_POST['receipt_date']; // วันที่ออกใบเสร็จ
    $receipt_issued_by = trim($_POST['receipt_issued_by'] ?? '');

    // Update DB: status -> approved และบันทึก receipt_no, receipt_date
    $sql_update = "UPDATE sign_requests 
                   SET status = 'approved', receipt_no = ?, receipt_date = ?, receipt_issued_by = ?
                   WHERE id = ?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("sssi", $receipt_no, $receipt_date, $receipt_issued_by, $request_id);

    if ($stmt_up->execute()) {
        logRequestAction($conn, $request_id, 'receipt_issued', 'ออกใบเสร็จรับเงิน', $_SESSION['user_id'], 'เลขที่: ' . $receipt_no);
        logRequestAction($conn, $request_id, 'approved', 'อนุมัติคำร้อง', $_SESSION['user_id']);
        send_status_notification($request_id, $conn);

        ?>
        <!DOCTYPE html>
        <html lang="th">

        <head>
            <meta charset="UTF-8">
            <title>ออกใบเสร็จสำเร็จ</title>
            <?php include '../includes/header.php'; ?>
        </head>

        <body>
            <?php include '../includes/scripts.php'; ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'ออกใบเสร็จเรียบร้อยแล้ว! ผู้ใช้สามารถดาวน์โหลดใบเสร็จและหนังสืออนุญาตได้แล้ว',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = 'request_list.php';
                    });
                });
            </script>
        </body>

        </html>
        <?php
        exit;
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ออกใบเสร็จรับเงิน</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="container py-4">
            <a href="request_list.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i>
                กลับหน้ารายงาน</a>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> ออกใบเสร็จรับเงิน คำขอ #
                        <?= $request['id'] ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i> <strong>คำชี้แจง:</strong>
                        ในขั้นตอนนี้ ท่านจะต้องระบุ <strong>"เลขที่ใบเสร็จ"</strong> และ
                        <strong>"วันที่ออกใบเสร็จ"</strong>
                        เพื่อระบบจะนำข้อมูลนี้ไปสร้างใบเสร็จรับเงินให้กับประชาชน
                        <br>เมื่อกดปุ่ม "บันทึกและออกใบเสร็จ" สถานะจะเปลี่ยนเป็น <strong>"อนุมัติแล้ว"</strong>
                        และผู้ใช้สามารถดาวน์โหลดใบเสร็จและหนังสืออนุญาตได้ทันที
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong>ผู้ยื่นคำขอ:</strong>
                            <?= $request['title_name'] . $request['first_name'] . ' ' . $request['last_name'] ?><br>
                            <strong>ประเภทป้าย:</strong>
                            <?= $request['sign_type'] ?><br>
                            <strong>ขนาด:</strong>
                            <?= $request['width'] ?> x
                            <?= $request['height'] ?> เมตร<br>
                            <strong>จำนวน:</strong>
                            <?= $request['quantity'] ?> ป้าย
                        </div>
                        <div class="col-md-6">
                            <strong>ค่าธรรมเนียม:</strong> <span class="text-success h5">
                                <?= number_format($request['fee'], 2) ?>
                            </span> บาท<br>
                            <strong>สถานที่:</strong>
                            <?= $request['road_name'] ?><br>
                            <strong>ข้อความ:</strong>
                            <?= $request['description'] ?>
                        </div>
                    </div>

                    <!-- แสดงสลิปการชำระเงิน (ถ้ามี) -->
                    <?php
                    $sql_slip = "SELECT * FROM sign_documents WHERE request_id = ? AND doc_type = 'Payment Slip' ORDER BY id DESC LIMIT 1";
                    $stmt_slip = $conn->prepare($sql_slip);
                    $stmt_slip->bind_param("i", $request_id);
                    $stmt_slip->execute();
                    $slip_result = $stmt_slip->get_result();
                    if ($slip_result->num_rows > 0):
                        $slip = $slip_result->fetch_assoc();
                        ?>
                        <div class="alert alert-success">
                            <strong>หลักฐานการชำระเงิน:</strong><br>
                            <a href="../<?= htmlspecialchars($slip['file_path']) ?>" target="_blank"
                                class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-file-earmark-image"></i> ดูสลิปการชำระเงิน
                            </a>
                            <button type="button" onclick="checkSlipDetails(<?= $slip['id'] ?>)"
                                class="btn btn-sm btn-info mt-2 text-white">
                                <i class="bi bi-shield-check"></i> ตรวจสอบข้อมูลสลิป
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Slip Check Script -->
                    <script>
                        function checkSlipDetails(docId) {
                            Swal.fire({
                                title: 'กำลังตรวจสอบ...',
                                text: 'กำลังเชื่อมต่อกับธนาคารเพื่อตรวจสอบข้อมูลสลิป',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            $.ajax({
                                url: 'check_slip_ajax.php',
                                type: 'POST',
                                data: { doc_id: docId },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'success') {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'ข้อมูลสลิปถูกต้อง',
                                            html: `
                                                <div class="text-start">
                                                    <table class="table table-bordered table-sm">
                                                        <tr><th width="35%">รหัสธุรกรรม</th><td class="text-primary font-monospace">${response.transRef}</td></tr>
                                                        <tr><th>ผู้โอน</th><td>${response.sender}</td></tr>
                                                        <tr><th>ธนาคาร</th><td>${response.bank}</td></tr>
                                                        <tr><th>จำนวนเงิน</th><td class="text-success fw-bold">${parseFloat(response.amount).toFixed(2)} บาท</td></tr>
                                                        <tr><th>ผู้รับ</th><td>${response.receiver}</td></tr>
                                                        <tr><th>วันที่โอน</th><td>${response.date}</td></tr>
                                                    </table>
                                                </div>
                                            `,
                                            confirmButtonText: 'ปิด'
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ตรวจสอบล้มเหลว',
                                            text: response.message
                                        });
                                    }
                                },
                                error: function () {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'เกิดข้อผิดพลาด',
                                        text: 'ไม่สามารถเชื่อมต่อกับ Server ได้'
                                    });
                                }
                            });
                        }
                    </script>

                    <form method="post" id="issueReceiptForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">เลขที่ใบเสร็จ</label>
                                <input type="text" name="receipt_no" class="form-control"
                                    value="<?= htmlspecialchars($receipt_number) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่ออกใบเสร็จ</label>
                                <input type="date" name="receipt_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ชื่อผู้รับเงิน/ผู้ออกใบเสร็จ</label>
                                <input type="text" name="receipt_issued_by" class="form-control"
                                    value="<?= htmlspecialchars($issuer_name_default ?: '................................') ?>"
                                    required>
                            </div>
                            <div class="col-md-12">
                                <button type="button" class="btn btn-warning w-100 mt-2"
                                    onclick="confirmIssueReceipt()">
                                    <i class="bi bi-save"></i> บันทึกและออกใบเสร็จ
                                </button>
                                <!-- Hidden input to simulate button click for PHP check -->
                                <input type="hidden" name="issue_receipt_confirm" value="1">
                            </div>
                        </div>
                    </form>

                    <script>
                        function confirmIssueReceipt() {
                            Swal.fire({
                                title: 'ยืนยันการออกใบเสร็จ?',
                                text: "กรุณาตรวจสอบความถูกต้องก่อนบันทึก",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'ยืนยัน',
                                cancelButtonText: 'ยกเลิก'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    document.getElementById('issueReceiptForm').submit();
                                }
                            });
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>