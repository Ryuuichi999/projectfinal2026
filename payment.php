<?php
require './includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: users/my_request.php");
    exit;
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลคำขอ
$sql = "SELECT * FROM sign_requests WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลคำขอ";
    exit;
}

$request = $result->fetch_assoc();
$amount = $request['fee'];

// หากสถานะไม่ใช่รอชำระเงิน ให้เด้งกลับ (หรือถ้าเป็น pending แล้วก็บอกว่าจ่ายแล้ว)
if ($request['status'] !== 'waiting_payment') {
    $alert_message = "รายการนี้ไม่ได้อยู่ในสถานะรอชำระเงิน (สถานะปัจจุบัน: {$request['status']})";
    // echo "<script>
    //     document.addEventListener('DOMContentLoaded', function() {
    //         Swal.fire({
    //             icon: 'info',
    //             title: 'แจ้งเตือน',
    //             text: '$alert_message',
    //             confirmButtonText: 'ตกลง'
    //         }).then(() => {
    //             window.location.href='users/my_request.php';
    //         });
    //     });
    // </script>";
    // exit;
    // ปล่อยผ่านเผื่อ user อยากจ่ายซ้ำ หรือ logic อื่นๆ แต่ปกติควร block
}

// Handle Slip Upload
if (isset($_POST['upload_slip'])) {
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] == UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['slip_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            $upload_dir = "uploads/slips/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = "slip_{$request_id}_" . time() . "." . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $dest_path)) {
                // บันทึกสลิปใน sign_documents
                $doc_type = 'Payment Slip';
                $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path) VALUES (?, ?, ?)";
                $stmt_doc = $conn->prepare($sql_doc);
                $stmt_doc->bind_param("iss", $request_id, $doc_type, $dest_path);

                if ($stmt_doc->execute()) {
                    // อัปเดตสถานะคำขอเป็น 'waiting_receipt' (รอออกใบเสร็จ)
                    $update_sql = "UPDATE sign_requests SET status = 'waiting_receipt' WHERE id = ?";
                    $stmt_update = $conn->prepare($update_sql);
                    $stmt_update->bind_param("i", $request_id);
                    
                    if ($stmt_update->execute()) {
                        // ใช้ HTML structure ที่สมบูรณ์พร้อม SweetAlert
                        ?>
                        <!DOCTYPE html>
                        <html lang="th">
                        <head>
                            <meta charset="UTF-8">
                            <title>สำเร็จ</title>
                            <?php include './includes/header.php'; ?>
                        </head>
                        <body>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'สำเร็จ',
                                        text: 'แจ้งชำระเงินเรียบร้อยแล้ว! เจ้าหน้าที่จะตรวจสอบสลิปของท่าน',
                                        confirmButtonText: 'ตกลง'
                                    }).then(() => {
                                        window.location.href = 'users/my_request.php';
                                    });
                                });
                            </script>
                            <?php include './includes/scripts.php'; ?>
                        </body>
                        </html>
                        <?php
                        exit;
                    } else {
                        $error = "เกิดข้อผิดพลาดในการอัปเดตสถานะ: " . $conn->error;
                    }
                } else {
                    $error = "เกิดข้อผิดพลาดในการบันทึกสลิป: " . $conn->error;
                }
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ กรุณาลองใหม่อีกครั้ง";
            }
        } else {
            $error = "ไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ JPG, PNG, PDF";
        }
    } else {
        $error = "กรุณาเลือกไฟล์สลิป";
    }
}

// สร้าง URL QR Code (PromptPay AnyID)
// รูปแบบ: https://promptpay.io/{id}/{amount}
// ID สมมติ: 0999999999 (เบอร์โทร) หรือ Text ID
$promptpay_id = "0990740305"; // <--- แก้ไขเป็นเบอร์จริงได้ที่นี่
$qr_url = "https://promptpay.io/{$promptpay_id}/{$amount}.png";

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ชำระค่าธรรมเนียม</title>
    <?php include './includes/header.php'; ?>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>

<body>

    <?php include './includes/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid" style="max-width: 800px;">
            <div class="card p-4 fade-in-up">
                <h2 class="text-center mb-4">💳 ชำระค่าธรรมเนียมคำขอ #
                    <?= $request_id ?>
                </h2>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 text-center border-end">
                        <h5 class="text-muted">สแกน QR Code เพื่อจ่ายเงิน</h5>
                        <img src="<?= $qr_url ?>" alt="PromptPay QR" class="img-fluid my-3"
                            style="max-width: 300px; border: 1px solid #ddd; border-radius: 8px;">
                        <h3 class="text-primary">
                            <?= number_format($amount, 2) ?> บาท
                        </h3>
                        <p class="text-muted small">PromptPay ID:
                            <?= $promptpay_id ?>
                        </p>
                    </div>

                    <div class="col-md-6 d-flex flex-column justify-content-center p-4">
                        <h5 class="mb-3">📢 แจ้งโอนเงิน (Upload Slip)</h5>
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">อัปโหลดหลักฐานการโอนเงิน</label>
                                <input type="file" name="slip_file" class="form-control" required
                                    accept="image/*, .pdf">
                                <div class="form-text">รองรับไฟล์ .jpg, .png, .pdf</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="upload_slip" class="btn btn-success btn-lg">
                                    ✅ ยืนยันการชำระเงิน
                                </button>
                                <a href="users/my_request.php" class="btn btn-outline-secondary">กลับไปหน้ารายการ</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <?php include './includes/scripts.php'; ?>
</body>

</html>