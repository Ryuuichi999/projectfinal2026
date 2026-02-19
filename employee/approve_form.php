<?php
session_start();
require '../includes/db.php';
require '../includes/email_helper.php';
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
$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address, u.phone 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลคำขอ";
    exit;
}

$request = $result->fetch_assoc();

if (isset($_POST['approve_confirm'])) {
    // Update DB: status -> waiting_payment
    // Note: permit_no/permit_date will be set later in issue_receipt.php
    $sql_update = "UPDATE sign_requests SET status = 'waiting_payment', approved_by = ? WHERE id = ?";
    $stmt_up = $conn->prepare($sql_update);
    $approver_id = $_SESSION['user_id'];
    $stmt_up->bind_param("ii", $approver_id, $request_id);

    if ($stmt_up->execute()) {
        send_status_notification($request_id, $conn);
        logRequestAction($conn, $request_id, 'waiting_payment', 'อนุมัติคำร้อง — รอชำระค่าธรรมเนียม', $approver_id, 'ตรวจสอบเอกสารเบื้องต้นผ่านแล้ว');

        require_once '../includes/audit_helper.php';
        logAudit($conn, 'approve', 'sign_requests', $request_id, 'อนุมัติคำร้องให้รอชำระเงิน');

        echo '<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: "success",
                title: "อนุมัติเรียบร้อย",
                text: "ส่งคำร้องให้ผู้ใช้ชำระเงินแล้ว",
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                window.location.href = "request_list.php";
            });
        });
    </script>
</body>
</html>';
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
    <title>อนุมัติคำขอเบื้องต้น</title>
    <?php include '../includes/header.php'; ?>
</head>

<body class="bg-light">
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="container py-4">
            <div class="mb-3">
                <a href="request_list.php" class="btn-back d-inline-flex align-items-center"><i
                        class="bi bi-chevron-left me-1"></i> ย้อนกลับ</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle"></i> ขั้นตอนการอนุมัติคำขอ #
                        <?= $request['id'] ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i> <strong>คำชี้แจง:</strong>
                        กรุณาตรวจสอบรายละเอียดคำขอให้ถูกต้องครบถ้วน
                        <br>เมื่อกดปุ่ม <strong>"อนุมัติและแจ้งให้ชำระเงิน"</strong> สถานะจะเปลี่ยนเป็น <strong>"รอชำระเงิน"</strong>
                        เพื่อให้ประชาชนดำเนินการชำระค่าธรรมเนียมต่อไป (ใบอนุญาตจะออกให้หลังจากชำระเงินแล้ว)
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

                    <form method="post" id="approveForm">
                        <div class="row g-3 justify-content-center mt-4">
                            <div class="col-md-8 text-center">
                                <div class="alert alert-secondary">
                                    <i class="bi bi-question-circle"></i> ยืนยันการตรวจสอบเอกสารและส่งต่อให้ผู้ชำระเงิน?
                                </div>
                                <button type="button" class="btn btn-success btn-lg w-100 py-3" onclick="confirmApprove()">
                                    <i class="bi bi-check-circle-fill me-2"></i> อนุมัติและแจ้งให้ชำระเงิน
                                </button>
                                <input type="hidden" name="approve_confirm" value="1">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
        function confirmApprove() {
            Swal.fire({
                title: 'ยืนยันการอนุมัติ?',
                text: "สถานะจะเปลี่ยนเป็น 'รอชำระเงิน'",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('approveForm').submit();
                }
            })
        }
    </script>
</body>

</html>