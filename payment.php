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
    echo 'const Toast=Swal.mixin({toast:true,position:"top-end",showConfirmButton:false,timer:3000,timerProgressBar:true,didOpen:(t)=>{t.onmouseenter=Swal.stopTimer;t.onmouseleave=Swal.resumeTimer}});';
    echo 'Toast.fire({icon:"info",title:"ไม่สามารถชำระเงินได้"}).then(()=>{window.location.href="users/my_request.php";});';
    echo '});</script></body></html>';
    exit;
}

// Auto-migrate: เพิ่ม UNIQUE index บน trans_ref (ป้องกันสลิปซ้ำระดับ DB)
$idx_check = $conn->query("SHOW INDEX FROM sign_documents WHERE Key_name = 'uq_trans_ref'");
if ($idx_check && $idx_check->num_rows === 0) {
    // ลบ trans_ref ที่ซ้ำก่อน (เก็บแถวแรกไว้)
    $conn->query("DELETE d1 FROM sign_documents d1
                   INNER JOIN sign_documents d2
                   WHERE d1.id > d2.id AND d1.trans_ref = d2.trans_ref AND d1.trans_ref IS NOT NULL AND d1.trans_ref != ''");
    // ลองสร้าง index ถ้าไม่ได้ก็ข้ามไป
    @$conn->query("ALTER TABLE sign_documents ADD UNIQUE INDEX uq_trans_ref (trans_ref)");
}

if (isset($_POST['upload_slip'])) {
    csrf_check();
    if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] == UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $allowed_mimes = ['image/jpeg', 'image/png'];
        $max_slip_size = 5 * 1024 * 1024;
        $filename = $_FILES['slip_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_mime = $finfo->file($_FILES['slip_file']['tmp_name']);

        if ($_FILES['slip_file']['size'] > $max_slip_size) {
            $error = "ไฟล์สลิปมีขนาดเกิน 5MB";
        } elseif (!in_array($real_mime, $allowed_mimes)) {
            $error = "ไฟล์สลิปต้องเป็นรูปภาพ JPG หรือ PNG เท่านั้น (ตรวจพบ: {$real_mime})";
        } elseif (in_array($ext, $allowed)) {
            $filePath = $_FILES['slip_file']['tmp_name'];
            $token = THUNDER_API_TOKEN;
            $apiResult = checkSlip($filePath, $token);

            if ($apiResult['status'] === 'success') {
                $transRef = $apiResult['transRef'];
                $slipAmount = (float) $apiResult['amount'];
                $requiredAmount = (float) $amount;

                // ตรวจสอบสลิปซ้ำ (transRef เคยใช้แล้วหรือยัง)
                $stmt_dup = $conn->prepare("SELECT id FROM sign_documents WHERE trans_ref = ?");
                $stmt_dup->bind_param("s", $transRef);
                $stmt_dup->execute();
                $dup_result = $stmt_dup->get_result();

                // ตรวจสอบชื่อผู้รับ / เลข PromptPay
                $receiverName  = $apiResult['receiver_name'] ?? '';
                $receiverProxy = $apiResult['receiver_proxy'] ?? '';
                $expectedName  = 'รัชชานนท์';
                $expectedProxy = '0990740305';

                // ตรวจ: ชื่อต้องมี "รัชชานนท์" หรือเลข PromptPay ต้องตรง
                $nameMatch  = (mb_strpos($receiverName, $expectedName) !== false);
                $proxyMatch = (str_replace(['-', ' '], '', $receiverProxy) === $expectedProxy);
                $receiverValid = ($nameMatch || $proxyMatch);

                if ($dup_result->num_rows > 0) {
                    $error = "สลิปนี้เคยถูกใช้แล้ว (TransRef: {$transRef}) กรุณาโอนเงินใหม่และใช้สลิปใหม่";
                } elseif (abs($slipAmount - $requiredAmount) > 0.01) {
                    $error = "จำนวนเงินในสลิปไม่ตรง: สลิประบุ " . number_format($slipAmount, 2) . " บาท แต่ต้องชำระ " . number_format($requiredAmount, 2) . " บาท กรุณาตรวจสอบอีกครั้ง";
                } elseif (!$receiverValid) {
                    $error = "ผู้รับเงินไม่ถูกต้อง: สลิประบุผู้รับ \"" . htmlspecialchars($receiverName) . "\" กรุณาโอนเงินไปยังบัญชี PromptPay หมายเลข {$expectedProxy} (นาย รัชชานนท์ อินกันหา) เท่านั้น";
                } else {
                    $upload_dir = "uploads/slips/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $new_filename = "slip_{$request_id}_" . time() . "." . $ext;
                    $dest_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['slip_file']['tmp_name'], $dest_path)) {
                        $doc_type = 'Payment Slip';
                        $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path, trans_ref) VALUES (?, ?, ?, ?)";
                        $stmt_doc = $conn->prepare($sql_doc);
                        $stmt_doc->bind_param("isss", $request_id, $doc_type, $dest_path, $transRef);

                        if ($stmt_doc->execute()) {
                            ensureSettingsTable($conn);
                            $receipt_no = generateNextReceiptNumber($conn);
                            $receipt_date = date('Y-m-d');
                            $receipt_issued_by = getSetting($conn, 'receipt_signer_name', 'ระบบอัตโนมัติ (ชำระเงินออนไลน์)');

                            $update_sql = "UPDATE sign_requests SET status = 'waiting_permit', receipt_no = ?, receipt_date = ?, receipt_issued_by = ? WHERE id = ?";
                            $stmt_update = $conn->prepare($update_sql);
                            $stmt_update->bind_param("sssi", $receipt_no, $receipt_date, $receipt_issued_by, $request_id);

                            if ($stmt_update->execute()) {
                                logRequestAction($conn, $request_id, 'paid', 'ชำระเงินสำเร็จ', $user_id, 'จำนวน: ' . number_format($amount) . ' บาท');
                                logRequestAction($conn, $request_id, 'receipt_issued', 'ออกใบเสร็จอัตโนมัติ', null, 'เลขที่: ' . $receipt_no);
                                logRequestAction($conn, $request_id, 'waiting_permit', 'รอออกใบอนุญาต', null, 'ชำระเงินแล้ว รอเจ้าหน้าที่ออกใบอนุญาต');
                                queue_status_notification($request_id, $conn);
                                csrf_regenerate();
                                $_SESSION['flash_success'] = 'ชำระเงินสำเร็จ!';
                                header('Location: users/my_request.php');
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($response, true);
        if (isset($json['data']['transRef'])) {
            $senderName = $json['data']['sender']['account']['name']['th'] ??
                $json['data']['sender']['account']['name']['en'] ?? 'Unknown';
            $receiverName = $json['data']['receiver']['account']['name']['th'] ??
                $json['data']['receiver']['account']['name']['en'] ?? '';
            $receiverProxy = $json['data']['receiver']['proxy']['value'] ?? '';
            return [
                'status'        => 'success',
                'transRef'      => $json['data']['transRef'],
                'amount'        => $json['data']['amount']['amount'],
                'sender_name'   => $senderName,
                'receiver_name' => $receiverName,
                'receiver_proxy'=> $receiverProxy
            ];
        } else {
            return ['status' => 'error', 'message' => 'ไม่พบข้อมูล Data ใน Response'];
        }
    } else {
        $json = json_decode($response, true);
        $msg = $json['message'] ?? 'Unknown Error';
        return ['status' => 'error', 'message' => "($httpCode) $msg"];
    }
}

$promptpay_id = "0990740305";
$qr_url = "https://promptpay.io/{$promptpay_id}/{$amount}.png";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระค่าธรรมเนียม — เทศบาลเมืองศิลา</title>
    <?php include './includes/header.php'; ?>

    <style>
        :root {
            --pay-primary: #1a56db;
            --pay-primary-dark: #1e40af;
            --pay-primary-light: #dbeafe;
            --pay-bg: #f1f5f9;
            --pay-white: #ffffff;
            --pay-border: #e2e8f0;
            --pay-text: #1e293b;
            --pay-muted: #64748b;
            --pay-danger: #dc2626;
            --pay-success: #16a34a;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--pay-bg);
            color: var(--pay-text);
        }

        .pay-page {
            max-width: 960px;
            margin: 24px auto 48px;
            padding: 0 16px;
        }

        .pay-breadcrumb {
            font-size: 13px;
            color: var(--pay-muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pay-breadcrumb a { color: var(--pay-primary); text-decoration: none; }
        .pay-breadcrumb a:hover { text-decoration: underline; }
        .pay-breadcrumb .sep { color: #cbd5e1; }

        .pay-title-strip {
            background: var(--pay-white);
            border: 1px solid var(--pay-border);
            border-left: 5px solid var(--pay-primary);
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .pay-title-strip h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--pay-primary-dark);
            margin: 0;
        }
        .pay-ref-badge {
            background: var(--pay-primary);
            color: #fff;
            font-size: 12px;
            padding: 5px 14px;
            border-radius: 20px;
            font-weight: 600;
            white-space: nowrap;
        }

        .pay-alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 5px solid var(--pay-danger);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 18px;
            color: var(--pay-danger);
            font-size: 14px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .pay-card {
            background: var(--pay-white);
            border: 1px solid var(--pay-border);
            border-radius: 14px;
            margin-bottom: 18px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .pay-card-header {
            background: linear-gradient(135deg, var(--pay-primary), var(--pay-primary-dark));
            color: #fff;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pay-card-header.qr-header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }
        .pay-card-body { padding: 20px; }

        .pay-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .pay-data-table tr { border-bottom: 1px solid #f1f5f9; }
        .pay-data-table tr:last-child { border-bottom: none; }
        .pay-data-table td { padding: 10px 8px; vertical-align: top; }
        .pay-data-table td.label { color: var(--pay-muted); width: 42%; }
        .pay-data-table td.value { font-weight: 600; color: var(--pay-text); }

        .amount-row {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 2px solid var(--pay-primary);
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .amount-row .amount-label { font-size: 14px; font-weight: 600; color: var(--pay-primary-dark); }
        .amount-row .amount-value { font-size: 28px; font-weight: 700; color: var(--pay-primary); }
        .amount-row .amount-unit { font-size: 14px; color: var(--pay-muted); margin-left: 4px; }

        .qr-wrap { text-align: center; padding: 20px; }
        .qr-wrap img {
            max-width: 200px;
            border: 2px solid var(--pay-border);
            padding: 10px;
            background: #fff;
            border-radius: 12px;
        }
        .qr-amount-badge {
            display: inline-block;
            margin-top: 12px;
            background: var(--pay-primary);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            padding: 6px 22px;
            border-radius: 20px;
        }
        .qr-sub { font-size: 12px; color: var(--pay-muted); margin-top: 6px; }

        .pay-notice {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid var(--pay-primary);
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 18px;
            font-size: 13.5px;
        }
        .pay-notice .notice-title { font-weight: 700; color: var(--pay-primary-dark); margin-bottom: 6px; }
        .pay-notice ol { margin: 0; padding-left: 18px; color: var(--pay-text); }
        .pay-notice ol li { margin-bottom: 4px; }
        .pay-notice strong { color: var(--pay-danger); }

        .upload-zone {
            border: 2px dashed #93c5fd;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: var(--pay-primary);
            background: #eff6ff;
        }
        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .upload-zone-icon { font-size: 36px; color: #93c5fd; margin-bottom: 8px; }
        .upload-zone-text { font-size: 14px; color: var(--pay-muted); }
        .upload-zone-hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }

        #slip_preview_wrap { margin-top: 16px; text-align: center; }
        #slip_preview_wrap img {
            max-height: 200px;
            border: 2px solid var(--pay-border);
            padding: 4px;
            background: #fff;
            border-radius: 10px;
        }
        .preview-label { font-size: 12px; color: var(--pay-muted); margin-bottom: 6px; }

        .pay-submit-btn {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, var(--pay-primary), var(--pay-primary-dark));
            color: #fff;
            border: none;
            padding: 14px 24px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 18px;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(26,86,219,0.3);
        }
        .pay-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(26,86,219,0.4); }
        .pay-submit-btn:active { transform: translateY(0); }

        .pay-footer-note {
            text-align: center;
            font-size: 12px;
            color: var(--pay-muted);
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid var(--pay-border);
        }

        .pay-divider { border: none; border-top: 1px solid var(--pay-border); margin: 16px 0; }

        @media (max-width: 768px) {
            .pay-title-strip { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>
</head>

<body>

    <?php include './includes/user_navbar.php'; ?>

    <div class="pay-page">

        <!-- Breadcrumb -->
        <div class="pay-breadcrumb">
            <a href="users/my_request.php"><i class="bi bi-house-door"></i> คำร้องของฉัน</a>
            <span class="sep">›</span>
            <a href="users/request_detail.php?id=<?= $request_id ?>"><?= htmlspecialchars($request['request_no'] ?? '#'.$request_id) ?></a>
            <span class="sep">›</span>
            <span>ชำระค่าธรรมเนียม</span>
        </div>

        <!-- Title Strip -->
        <div class="pay-title-strip">
            <h1><i class="bi bi-credit-card me-2"></i>ชำระค่าธรรมเนียมป้ายโฆษณา</h1>
            <div class="pay-ref-badge"><?= htmlspecialchars($request['request_no'] ?? '#'.$request_id) ?></div>
        </div>

        <!-- Error Alert -->
        <?php if (isset($error)): ?>
        <div class="pay-alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="row g-3">

            <!-- LEFT COLUMN -->
            <div class="col-md-5">

                <!-- Request Details -->
                <div class="pay-card">
                    <div class="pay-card-header">
                        <i class="bi bi-receipt"></i> รายละเอียดคำร้อง
                    </div>
                    <div class="pay-card-body">
                        <table class="pay-data-table">
                            <tr>
                                <td class="label">ประเภทป้าย</td>
                                <td class="value"><?= htmlspecialchars($request['sign_type']) ?></td>
                            </tr>
                            <tr>
                                <td class="label">ขนาด (กว้าง × สูง)</td>
                                <td class="value"><?= $request['width'] ?> × <?= $request['height'] ?> เมตร</td>
                            </tr>
                            <tr>
                                <td class="label">จำนวนป้าย</td>
                                <td class="value"><?= $request['quantity'] ?> ป้าย</td>
                            </tr>
                            <tr>
                                <td class="label">สถานที่ / ถนน</td>
                                <td class="value"><?= htmlspecialchars($request['road_name']) ?></td>
                            </tr>
                        </table>
                        <div class="amount-row">
                            <span class="amount-label">ยอดที่ต้องชำระ</span>
                            <span>
                                <span class="amount-value"><?= number_format($amount, 2) ?></span>
                                <span class="amount-unit">บาท</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="pay-card">
                    <div class="pay-card-header qr-header">
                        <i class="bi bi-qr-code"></i> ชำระผ่าน PromptPay
                    </div>
                    <div class="pay-card-body qr-wrap">
                        <img src="<?= $qr_url ?>" alt="PromptPay QR Code">
                        <br>
                        <div class="qr-amount-badge"><?= number_format($amount, 2) ?> บาท</div>
                        <div class="qr-sub">PromptPay: <?= $promptpay_id ?></div>
                        <div class="qr-sub">นาย รัชชานนท์ อินกันหา</div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-7">
                <div class="pay-card">
                    <div class="pay-card-header">
                        <i class="bi bi-cloud-arrow-up"></i> แนบหลักฐานการชำระเงิน
                    </div>
                    <div class="pay-card-body">

                        <!-- Instruction Notice -->
                        <div class="pay-notice">
                            <div class="notice-title"><i class="bi bi-info-circle me-1"></i> ข้อกำหนดในการแนบหลักฐาน</div>
                            <ol>
                                <li>ระบบจะตรวจสอบความถูกต้องของสลิปโอนเงินโดยอัตโนมัติ</li>
                                <li>ยอดเงินในสลิปต้องตรงกับ <strong><?= number_format($amount, 2) ?> บาท</strong> เท่านั้น</li>
                                <li>ผู้รับเงินต้องเป็น <strong>นาย รัชชานนท์ อินกันหา (PromptPay: 0990740305)</strong></li>
                                <li>ห้ามใช้สลิปซ้ำหรือสลิปที่ดัดแปลงแก้ไขโดยเด็ดขาด</li>
                                <li>รองรับไฟล์นามสกุล .jpg, .jpeg, .png ขนาดไม่เกิน 5 MB</li>
                            </ol>
                        </div>

                        <form method="post" enctype="multipart/form-data" id="slipForm">
                            <?= csrf_field() ?>

                            <!-- Upload Zone -->
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="slip_file" id="slip_file" required accept=".jpg,.jpeg,.png">
                                <div class="upload-zone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="upload-zone-text">คลิกเพื่อเลือกไฟล์ หรือลากและวางไฟล์ที่นี่</div>
                                <div class="upload-zone-hint">JPG / PNG · ขนาดสูงสุด 5 MB</div>
                            </div>

                            <!-- Image Preview -->
                            <div id="slip_preview_wrap" style="display:none;">
                                <div class="preview-label">ตัวอย่างสลิปที่เลือก:</div>
                                <img id="slip_preview" src="#" alt="preview">
                            </div>

                            <hr class="pay-divider">

                            <button type="submit" name="upload_slip" class="pay-submit-btn">
                                <i class="bi bi-check-circle me-2"></i>ยืนยันการชำระเงิน
                            </button>

                            <p style="font-size:12px; color:var(--pay-muted); text-align:center; margin-top:10px; margin-bottom:0;">
                                การกดปุ่มยืนยัน ถือว่าท่านรับทราบและยอมรับเงื่อนไขการชำระเงินข้างต้น
                            </p>
                        </form>

                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Note -->
        <div class="pay-footer-note">
            เทศบาลเมืองศิลา · ระบบยื่นคำขออนุญาตติดตั้งป้าย
        </div>

    </div>

    <?php include './includes/scripts.php'; ?>

    <script>
        // Preview
        document.getElementById('slip_file').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    document.getElementById('slip_preview').src = ev.target.result;
                    document.getElementById('slip_preview_wrap').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Drag-over highlight
        const zone = document.getElementById('uploadZone');
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', () => zone.classList.remove('drag-over'));
    </script>

</body>
</html>