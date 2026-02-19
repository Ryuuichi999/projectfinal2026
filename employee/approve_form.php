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
                text: "สถานะเปลี่ยนเป็นรอชำระเงิน",
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
    <title>อนุมัติคำขอ (Approve Request)</title>
    <?php include '../includes/header.php'; ?>
    <style>
        body {
            background-color: #f1f5f9;
        }
        /* No custom main-container max-width needed, utilizing container-fluid */
        
        .paper-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 0;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header-styled {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 30px 40px;
            color: white;
            position: relative;
        }
        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .header-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 300;
        }
        .status-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(5px);
        }

        .card-content {
            padding: 40px;
        }

        .section-head {
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .section-head i {
            margin-right: 10px;
            color: #10b981;
            font-size: 1.1rem;
        }

        .data-row {
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
        }
        .data-label {
            color: #64748b;
            font-size: 0.9rem;
            width: 140px;
            flex-shrink: 0;
        }
        .data-value {
            color: #0f172a;
            font-weight: 500;
            font-size: 1rem;
        }

        .fee-box {
            background-color: #f0fdf4;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            margin-bottom: 30px;
            border: 1px solid #dcfce7;
        }
        .fee-total {
            font-size: 1.75rem;
            font-weight: 800;
            color: #15803d;
        }

        .btn-back {
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 500;
            font-size: 1rem;
        }
        .btn-back:hover {
            color: #0f172a;
        }
        
        .instruction-text {
            color: #64748b;
            font-size: 0.9rem;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #cbd5e1;
            margin-bottom: 30px;
        }
        
        .hover-lift {
            transition: transform 0.2s;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-light">
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <!-- Using container-fluid to match request_detail.php margins -->
        <div class="container-fluid py-4">
            
            <a href="request_list.php" class="btn-back d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> ย้อนกลับ
            </a>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger shadow-sm mb-4 border-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="paper-card">
                <!-- Styled Header -->
                <div class="card-header-styled d-flex justify-content-between align-items-start">
                    <div>
                        <div class="header-title">ตรวจสอบคำขออนุญาต</div>
                        <div class="header-subtitle"><i class="bi bi-files me-1"></i> คำขอเลขที่ #<?= $request['id'] ?></div>
                    </div>
                    <div class="status-badge">
                        <i class="bi bi-clock me-1"></i> รอตรวจสอบ
                    </div>
                </div>

                <div class="card-content">
                    
                    <div class="instruction-text">
                        <i class="bi bi-info-circle me-2"></i> <strong>คำชี้แจง:</strong> กรุณาตรวจสอบรายละเอียดความถูกต้องก่อนทำการอนุมัติ
                    </div>

                    <div class="row gx-5">
                        <!-- Column 1 -->
                        <div class="col-lg-6 mb-4">
                            <div class="section-head text-secondary">
                                <i class="bi bi-person-badge"></i> ข้อมูลผู้ยื่นคำขอ
                            </div>
                            <div class="data-row">
                                <div class="data-label">ชื่อ-นามสกุล</div>
                                <div class="data-value"><?= $request['title_name'] . $request['first_name'] . ' ' . $request['last_name'] ?></div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">เลขบัตรประชาชน</div>
                                <div class="data-value"><?= $request['citizen_id'] ?? '-' ?></div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">เบอร์โทรศัพท์</div>
                                <div class="data-value"><?= $request['phone'] ?? '-' ?></div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">ที่อยู่</div>
                                <div class="data-value"><?= $request['user_address'] ?? '-' ?></div>
                            </div>
                        </div>

                        <!-- Column 2 -->
                        <div class="col-lg-6 mb-4">
                            <div class="section-head text-secondary">
                                <i class="bi bi-signpost-2"></i> ข้อมูลป้ายโฆษณา
                            </div>
                            <div class="data-row">
                                <div class="data-label">ประเภทป้าย</div>
                                <div class="data-value"><?= $request['sign_type'] ?></div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">จำนวน</div>
                                <div class="data-value"><?= $request['quantity'] ?> ป้าย</div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">ขนาด</div>
                                <div class="data-value"><?= $request['width'] ?> x <?= $request['height'] ?> เมตร</div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">ข้อความในป้าย</div>
                                <div class="data-value text-break">"<?= $request['description'] ?>"</div>
                            </div>
                            <div class="data-row">
                                <div class="data-label">สถานที่ติดตั้ง</div>
                                <div class="data-value"><?= $request['road_name'] ?></div>
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25 mb-4">

                    <!-- Fee Section -->
                    <div class="fee-box">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-wallet2 fs-3 text-success me-3"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">ค่าธรรมเนียมที่ต้องชำระ</h6>
                                <small class="text-muted">Fee Amount</small>
                            </div>
                        </div>
                        <div class="fee-total">฿<?= number_format($request['fee'], 2) ?></div>
                    </div>

                    <!-- Actions -->
                    <form method="post" id="approveForm" class="text-center mt-4">
                        <input type="hidden" name="approve_confirm" value="1">
                        <div class="d-flex gap-3 justify-content-center">
                            <a href="request_list.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-bold border-0 bg-light">
                                ยกเลิก
                            </a>
                            <button type="button" class="btn btn-success px-5 py-2 rounded-pill fw-bold shadow hover-lift" onclick="confirmApprove()">
                                <i class="bi bi-check-lg me-1"></i> ยืนยันการอนุมัติ
                            </button>
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
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยัน อนุมัติ',
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