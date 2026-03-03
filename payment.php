<?php
require './includes/db.php';
require_once './includes/email_helper.php';
require_once './includes/receipt_helper.php';
require_once './includes/settings_helper.php';
require_once './includes/log_helper.php';
require_once './includes/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


if (!isset($_GET['id'])) {
    header("Location: users/my_request.php");
    exit;
}

$request_id = (int) $_GET['id'];
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

// Block: หากสถานะไม่ใช่รอชำระเงิน ให้เด้งกลับทันที
if ($request['status'] !== 'waiting_payment') {
    $status_labels = [
        'pending'        => 'รอพิจารณา',
        'reviewing'      => 'กำลังพิจารณา',
        'need_documents' => 'ขอเอกสารเพิ่มเติม',
        'waiting_permit' => 'รอออกใบอนุญาต',
        'approved'       => 'อนุมัติแล้ว',
        'rejected'       => 'ไม่อนุมัติ',
    ];
    $status_th = $status_labels[$request['status']] ?? $request['status'];
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){';
    echo 'Swal.fire({icon:"info",title:"ไม่สามารถชำระเงินได้",';
    echo 'text:"คำร้องนี้อยู่ในสถานะ: ' . $status_th . ' ไม่ได้รอชำระเงิน",';
    echo 'confirmButtonText:"ตกลง"}).then(()=>{window.location.href="users/my_request.php";});';
    echo '});</script></body></html>';
    exit;
}

// Handle Slip Upload
if (isset($_POST['upload_slip'])) {
    csrf_check();
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] == UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $allowed_mimes = ['image/jpeg', 'image/png'];
        $max_slip_size = 5 * 1024 * 1024; // 5MB
        $filename = $_FILES['slip_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validate real MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_mime = $finfo->file($_FILES['slip_file']['tmp_name']);

        if ($_FILES['slip_file']['size'] > $max_slip_size) {
            $error = "ไฟล์สลิปมีขนาดเกิน 5MB";
        } elseif (!in_array($real_mime, $allowed_mimes)) {
            $error = "ไฟล์สลิปต้องเป็นรูปภาพ JPG หรือ PNG เท่านั้น (ตรวจพบ: {$real_mime})";
        } elseif (in_array($ext, $allowed)) {
            // Check Slip with Thunder API
            $filePath = $_FILES['slip_file']['tmp_name'];
            $token = THUNDER_API_TOKEN;

            $apiResult = checkSlip($filePath, $token);

            if ($apiResult['status'] === 'success') {
                $transRef = $apiResult['transRef'];
                $slipAmount = (float) $apiResult['amount'];
                $requiredAmount = (float) $amount;

                // ตรวจสอบจำนวนเงินในสลิปต้องตรงกับค่าธรรมเนียม
                if (abs($slipAmount - $requiredAmount) > 0.01) {
                    $error = "จำนวนเงินในสลิปไม่ตรง: สลิประบุ " . number_format($slipAmount, 2) . " บาท แต่ต้องชำระ " . number_format($requiredAmount, 2) . " บาท กรุณาตรวจสอบอีกครั้ง";
                } else {
                    // Valid and Unique -> Proceed to Upload
                    $upload_dir = "uploads/slips/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
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
                            // Auto-generate Receipt Number BUT Status -> Waiting Permit
                            // Ensure Settings Table exists (lazy init)
                            ensureSettingsTable($conn);

                            $receipt_no = generateNextReceiptNumber($conn);
                            $receipt_date = date('Y-m-d');
                            $receipt_issued_by = getSetting($conn, 'receipt_signer_name', 'ระบบอัตโนมัติ (ชำระเงินออนไลน์)');

                            $update_sql = "UPDATE sign_requests 
                                           SET status = 'waiting_permit', receipt_no = ?, receipt_date = ?, receipt_issued_by = ? 
                                           WHERE id = ?";
                            $stmt_update = $conn->prepare($update_sql);
                            $stmt_update->bind_param("sssi", $receipt_no, $receipt_date, $receipt_issued_by, $request_id);

                            if ($stmt_update->execute()) {
                                // บันทึก Log
                                logRequestAction($conn, $request_id, 'paid', 'ชำระเงินสำเร็จ', $user_id, 'จำนวน: ' . number_format($amount) . ' บาท');
                                logRequestAction($conn, $request_id, 'receipt_issued', 'ออกใบเสร็จอัตโนมัติ', null, 'เลขที่: ' . $receipt_no);
                                logRequestAction($conn, $request_id, 'waiting_permit', 'รอออกใบอนุญาต', null, 'ชำระเงินแล้ว รอเจ้าหน้าที่ออกใบอนุญาต');
                                // ส่ง email แจ้งเตือนสถานะ
                                send_status_notification($request_id, $conn);
                                ?>
                                <!DOCTYPE html>
                                <html lang="th">

                                <head>
                                    <meta charset="UTF-8">
                                    <title>ชำระเงินสำเร็จ</title>
                                    <?php include './includes/header.php'; ?>
                                </head>

                                <body>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'ชำระเงินสำเร็จ!',
                                                html: 'ระบบตรวจสอบยอดเงินเรียบร้อยแล้ว<br>ออกใบเสร็จเลขที่: <strong><?= $receipt_no ?></strong><br>สถานะ: <strong>รอเจ้าหน้าที่ออกใบอนุญาต</strong>',
                                                showConfirmButton: true,
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
                                $error = "เกิดข้อผิดพลาดในการอัปเดตสถานะ กรุณาลองใหม่";
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

    <?php include './includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4 mb-5" style="max-width: 960px;">

        <!-- Header -->
        <div class="d-flex align-items-center mb-3">
            <a href="users/my_request.php" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i> กลับ
            </a>
            <div>
                <h4 class="mb-0 fw-bold"><i class="bi bi-credit-card-2-front-fill text-primary me-2"></i>ชำระค่าธรรมเนียม</h4>
                <small class="text-muted">คำร้องเลขที่ #<?= $request_id ?> — <?= htmlspecialchars($request['sign_type']) ?></small>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <!-- ซ้าย: ข้อมูลคำร้อง + QR -->
            <div class="col-md-5">
                <!-- ข้อมูลคำร้อง -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-primary text-white py-2 px-3">
                        <i class="bi bi-file-earmark-text me-1"></i> รายละเอียดคำร้อง
                    </div>
                    <div class="card-body p-3">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted" style="width:45%">ประเภทป้าย</td>
                                <td class="fw-bold"><?= htmlspecialchars($request['sign_type']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">ขนาด</td>
                                <td class="fw-bold"><?= $request['width'] ?> × <?= $request['height'] ?> ม.</td>
                            </tr>
                            <tr>
                                <td class="text-muted">จำนวน</td>
                                <td class="fw-bold"><?= $request['quantity'] ?> ป้าย</td>
                            </tr>
                            <tr>
                                <td class="text-muted">ถนน</td>
                                <td class="fw-bold"><?= htmlspecialchars($request['road_name']) ?></td>
                            </tr>
                        </table>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">ยอดที่ต้องชำระ</span>
                            <span class="fs-4 fw-bold text-danger"><?= number_format($amount, 2) ?> <small class="fs-6">บาท</small></span>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-header bg-success text-white py-2 px-3">
                        <i class="bi bi-qr-code me-1"></i> สแกน QR PromptPay
                    </div>
                    <div class="card-body p-3">
                        <img src="<?= $qr_url ?>" alt="PromptPay QR" class="img-fluid"
                            style="max-width: 220px; border: 1px solid #ddd; border-radius: 8px; padding: 6px;">
                        <div class="mt-2">
                            <span class="badge bg-success fs-6 px-3 py-2"><?= number_format($amount, 2) ?> บาท</span>
                        </div>
                        <p class="text-muted small mt-2 mb-0">PromptPay: <?= $promptpay_id ?></p>
                        <p class="text-muted small">เทศบาลเมืองศิลา</p>
                    </div>
                </div>
            </div>

            <!-- ขวา: Upload Slip -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-warning text-dark py-2 px-3">
                        <i class="bi bi-upload me-1"></i> แนบหลักฐานการโอนเงิน
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info d-flex gap-2 align-items-start mb-3">
                            <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>ข้อควรทราบ:</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    <li>ระบบจะตรวจสอบยอดเงินในสลิปอัตโนมัติ</li>
                                    <li>ยอดในสลิปต้องตรงกับ <strong><?= number_format($amount, 2) ?> บาท</strong></li>
                                    <li>ห้ามใช้สลิปซ้ำหรือสลิปปลอม</li>
                                    <li>รองรับไฟล์ .jpg, .jpeg, .png เท่านั้น</li>
                                </ul>
                            </div>
                        </div>

                        <form method="post" enctype="multipart/form-data" id="slipForm">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">เลือกไฟล์สลิปการโอนเงิน <span class="text-danger">*</span></label>
                                <input type="file" name="slip_file" id="slip_file" class="form-control form-control-lg"
                                    required accept=".jpg,.jpeg,.png">
                                <div class="form-text">รองรับ JPG, PNG เท่านั้น (ขนาดไม่เกิน 5MB)</div>
                            </div>

                            <!-- Preview -->
                            <div id="slip_preview_wrap" class="mb-3 d-none">
                                <label class="form-label text-muted small">ตัวอย่างสลิปที่เลือก:</label>
                                <img id="slip_preview" src="#" alt="preview"
                                    class="img-fluid rounded border" style="max-height: 200px;">
                            </div>

                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" name="upload_slip" class="btn btn-success btn-lg fw-bold">
                                    <i class="bi bi-check-circle-fill me-2"></i>ยืนยันการชำระเงิน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include './includes/scripts.php'; ?>
    <script>
        // Preview slip image before upload
        document.getElementById('slip_file').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    document.getElementById('slip_preview').src = ev.target.result;
                    document.getElementById('slip_preview_wrap').classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>