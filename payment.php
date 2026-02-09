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
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['slip_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            // Check Slip with Thunder API
            $filePath = $_FILES['slip_file']['tmp_name'];
            $token = '1a4e92a3-11d0-400e-9079-aa374779682a'; // Provided API Key

            $apiResult = checkSlip($filePath, $token);

            if ($apiResult['status'] === 'success') {
                $transRef = $apiResult['transRef'];

                // Check duplicate in Database
                $checkDup = $conn->prepare("SELECT id FROM sign_documents WHERE trans_ref = ?");
                $checkDup->bind_param("s", $transRef);
                $checkDup->execute();
                if ($checkDup->get_result()->num_rows > 0) {
                    $error = "สลิปนี้ถูกใช้งานไปแล้ว กรุณาตรวจสอบอีกครั้ง";
                } else {
                    // Valid and Unique -> Proceed to Upload
                    $upload_dir = "uploads/slips/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = "slip_{$request_id}_" . time() . "." . $ext;
                    $dest_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $dest_path)) {
                        // บันทึกสลิปพร้อม trans_ref
                        $doc_type = 'Payment Slip';
                        $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path, trans_ref) VALUES (?, ?, ?, ?)";
                        $stmt_doc = $conn->prepare($sql_doc);
                        $stmt_doc->bind_param("isss", $request_id, $doc_type, $dest_path, $transRef);

                        if ($stmt_doc->execute()) {
                            // อัปเดตสถานะคำขอ
                            $update_sql = "UPDATE sign_requests SET status = 'waiting_receipt' WHERE id = ?";
                            $stmt_update = $conn->prepare($update_sql);
                            $stmt_update->bind_param("i", $request_id);

                            if ($stmt_update->execute()) {
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
                                        document.addEventListener('DOMContentLoaded', function () {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'ตรวจสอบสลิปสำเร็จ',
                                                html: 'ยอดเงิน: <?= number_format($apiResult['amount'], 2) ?> บาท<br>ผู้โอน: <?= $apiResult['sender_name'] ?>',
                                                showConfirmButton: false,
                                                timer: 2000
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
                                $error = "เกิดข้อผิดพลาดในการอัปเดตสถานะ";
                            }
                        } else {
                            $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                        }
                    } else {
                        $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                    }
                }
            } else {
                // API Error or Invalid Slip
                $error = "ตรวจสอบสลิปไม่ผ่าน: " . $apiResult['message'];
            }
        } else {
            $error = "อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG) สำหรับการตรวจสอบสลิป";
        }
    } else {
        $error = "กรุณาเลือกไฟล์สลิป";
    }
}

function checkSlip($filePath, $token)
{
    $url = 'https://api.thunder.in.th/v1/verify';
    $cfile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));
    $data = ['file' => $cfile];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Dev only

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($response, true);
        if (isset($json['data']['transRef'])) {
            // Extract useful info
            $senderName = $json['data']['sender']['account']['name']['th'] ??
                $json['data']['sender']['account']['name']['en'] ?? 'Unknown';
            return [
                'status' => 'success',
                'transRef' => $json['data']['transRef'],
                'amount' => $json['data']['amount']['amount'],
                'sender_name' => $senderName
            ];
        } else {
            return ['status' => 'error', 'message' => 'ไม่พบข้อมูล Data ใน Response'];
        }
    } else {
        // Handle error codes detailed in docs
        $json = json_decode($response, true);
        $msg = $json['message'] ?? 'Unknown Error';
        return ['status' => 'error', 'message' => "($httpCode) $msg"];
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