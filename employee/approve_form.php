<?php
session_start();
require '../includes/db.php';

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

// Auto-generate Permission Number (Example: Wait/2569)
$current_year_th = date('Y') + 543;
$permit_number = "Wait/" . $current_year_th;

if (isset($_POST['approve_confirm'])) {
    $permit_no = $_POST['permit_no'];
    $permit_date = $_POST['permit_date']; // วันที่ออกหนังสือ

    // Update DB: status -> waiting_payment
    $sql_update = "UPDATE sign_requests SET status = 'waiting_payment', permit_no = ?, permit_date = ? WHERE id = ?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("ssi", $permit_no, $permit_date, $request_id);

    if ($stmt_up->execute()) {
        echo "<script>alert('อนุมัติเรียบร้อย ส่งคำร้องให้ผู้ใช้ชำระเงินแล้ว'); window.location.href='request_list.php';</script>";
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
    <title>ออกหนังสืออนุญาต</title>
    <?php include '../includes/header.php'; ?>
</head>

<body class="bg-light">
    <?php include '../includes/sidebar.php'; ?>

    <div class="content fade-in-up">
        <div class="container py-4">
            <a href="request_list.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> กลับ</a>

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
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle-fill"></i> <strong>คำชี้แจง:</strong>
                        ในขั้นตอนนี้ ท่านจะต้องระบุ <strong>"เลขที่หนังสืออนุญาต"</strong> และ
                        <strong>"วันที่ออกหนังสือ"</strong>
                        เพื่อระบบจะนำข้อมูลนี้ไปสร้างหนังสืออนุญาต (แบบ ร.ส. ๒) ให้กับประชาชน
                        <br>เมื่อกดปุ่ม "บันทึกและอนุมัติ" สถานะจะเปลี่ยนเป็น <strong>"รอชำระเงิน"</strong>
                        เพื่อให้ประชาชนดำเนินการชำระค่าธรรมเนียมต่อไป
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

                    <form method="post">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">เลขที่หนังสืออนุญาต</label>
                                <input type="text" name="permit_no" class="form-control"
                                    value="<?= htmlspecialchars($permit_number) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่ออกหนังสือ</label>
                                <input type="date" name="permit_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="approve_confirm" class="btn btn-success w-100"
                                    onclick="return confirm('ยืนยันการอนุมัติ?');">
                                    <i class="bi bi-save"></i> บันทึกและอนุมัติ
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>