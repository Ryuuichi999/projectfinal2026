<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ตรวจสอบว่าเป็นคำร้องของผู้ใช้ และสถานะเป็น approved + ป้ายหมดอายุแล้วหรือภายใน 30 วัน
$stmt = $conn->prepare(
    "SELECT r.*, 
        DATE_ADD(r.permit_date, INTERVAL r.duration_days DAY) as expire_date
     FROM sign_requests r 
     WHERE r.id = ? AND r.user_id = ? AND r.status = 'approved'"
);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$old_request = $stmt->get_result()->fetch_assoc();

if (!$old_request) {
    echo "<script>alert('ไม่สามารถต่ออายุคำร้องนี้ได้'); window.location.href='my_request.php';</script>";
    exit;
}

// ดึงข้อมูลผู้ใช้
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$me = $stmt_user->get_result()->fetch_assoc();

$message = '';
$message_type = '';

if (isset($_POST['submit_renew'])) {
    $duration_days = (int) $_POST['duration_days'];
    $install_date = $_POST['install_date'];
    $end_date = date('Y-m-d', strtotime($install_date . " + $duration_days days"));

    // คำนวณค่าธรรมเนียม: เหมาป้ายละ 200 บาท (ตรงกับ request_form.php)
    $fee = 200 * $old_request['quantity'];

    $sql = "INSERT INTO sign_requests 
            (user_id, applicant_name, applicant_address, sign_type, width, height, quantity, 
             road_name, location_lat, location_lng, fee, status, duration_days, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
    $stmt = $conn->prepare($sql);
    $desc = "ต่ออายุจากคำร้อง #{$old_request['id']} (เลขที่ใบอนุญาต: {$old_request['permit_no']})";
    $stmt->bind_param(
        "isssddiisdsis",
        $user_id,
        $old_request['applicant_name'],
        $old_request['applicant_address'],
        $old_request['sign_type'],
        $old_request['width'],
        $old_request['height'],
        $old_request['quantity'],
        $old_request['road_name'],
        $old_request['location_lat'],
        $old_request['location_lng'],
        $fee,
        $duration_days,
        $desc
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;

        // บันทึก Log
        require_once '../includes/log_helper.php';
        logRequestAction($conn, $new_id, 'created', 'ยื่นคำร้องต่ออายุ', $user_id, "ต่ออายุจาก #{$old_request['id']}");

        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({icon:'success', title:'ยื่นต่ออายุสำเร็จ!', text:'คำร้องใหม่ #$new_id ถูกสร้างแล้ว'})
                .then(() => window.location.href='my_request.php');
            });
        </script>";
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $conn->error;
        $message_type = 'danger';
    }
}

$expire_date = $old_request['expire_date'];
$days_left = (int) ((strtotime($expire_date) - time()) / 86400);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ต่ออายุใบอนุญาต #
        <?= $old_request['id'] ?>
    </title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card p-4">
                    <h3 class="mb-3">🔄 ต่ออายุใบอนุญาต</h3>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <!-- ข้อมูลคำร้องเดิม -->
                    <div class="card bg-light p-3 mb-4">
                        <h6 class="text-primary mb-2">📋 ข้อมูลจากคำร้องเดิม #
                            <?= $old_request['id'] ?>
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small class="text-muted">ประเภทป้าย</small>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($old_request['sign_type']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">ขนาด</small>
                                <div class="fw-bold">
                                    <?= $old_request['width'] ?> ×
                                    <?= $old_request['height'] ?> ม.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">ถนน</small>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($old_request['road_name']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">วันหมดอายุ</small>
                                <div class="fw-bold">
                                    <?= date('d/m/Y', strtotime($expire_date)) ?>
                                    <?php if ($days_left <= 0): ?>
                                        <span class="badge bg-danger">หมดอายุแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">เหลือ
                                            <?= $days_left ?> วัน
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ฟอร์มต่ออายุ -->
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">วันที่เริ่มต้น (ต่ออายุ)</label>
                            <input type="date" name="install_date" class="form-control"
                                value="<?= date('Y-m-d', strtotime($expire_date . ' + 1 day')) ?>" required>
                            <div class="form-text">วันที่เริ่มนับอายุใบอนุญาตใหม่ (ต่อเนื่องจากเดิม)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ระยะเวลาที่ต้องการต่อ (วัน)</label>
                            <input type="number" name="duration_days" id="duration_days" class="form-control"
                                value="<?= $old_request['duration_days'] ?>" min="1" max="365" required>
                            <div class="form-text">จำนวนวันที่ต้องการขอติดตั้งเพิ่ม</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ค่าธรรมเนียม (ประเมิน)</label>
                            <input type="text" id="estimated_fee" class="form-control"
                                value="<?= number_format(200 * $old_request['quantity']) ?> บาท" disabled>
                            <small class="text-muted">อัตราเหมาป้ายละ 200 บาท (จำนวน <?= $old_request['quantity'] ?> ป้าย)</small>
                        </div>
                        <button type="submit" name="submit_renew" class="btn btn-success w-100">
                            🔄 ยื่นต่ออายุ
                        </button>
                        <a href="my_request.php" class="btn btn-outline-secondary w-100 mt-2">← กลับ</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include '../includes/scripts.php'; ?>
    <script>
        const quantity = <?= $old_request['quantity'] ?>;
        const feePerSign = 200;
        const totalFee = feePerSign * quantity;

        const durationInput = document.getElementById('duration_days');
        const feeInput = document.getElementById('estimated_fee');

        function updateFee() {
            feeInput.value = new Intl.NumberFormat('th-TH').format(totalFee) + ' บาท';
        }

        durationInput.addEventListener('input', updateFee);
        // Init
        updateFee();
    </script>
</body>

</html>