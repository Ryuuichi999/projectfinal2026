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
    $conn->query("ALTER TABLE sign_documents ADD UNIQUE INDEX uq_trans_ref (trans_ref)");
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
                                            Toast.fire({
                                                icon: 'success',
                                                title: 'ชำระเงินสำเร็จ!'
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

    <!-- Sarabun: official Thai government font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --gov-navy:    #1a2e5a;
            --gov-gold:    #b8960c;
            --gov-gold-lt: #d4af37;
            --gov-red:     #8b1a1a;
            --gov-bg:      #f5f3ee;
            --gov-white:   #ffffff;
            --gov-border:  #c9c3b4;
            --gov-text:    #1e1e1e;
            --gov-muted:   #5a5a5a;
            --gov-line:    #d4c98a;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--gov-bg);
            color: var(--gov-text);
            font-size: 15px;
            line-height: 1.7;
        }

        /* ===== LETTERHEAD BANNER ===== */
        .gov-letterhead {
            background: var(--gov-navy);
            border-bottom: 4px solid var(--gov-gold);
            padding: 0;
        }
        .gov-letterhead-inner {
            max-width: 960px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .gov-letterhead-logo {
            width: 56px;
            height: 56px;
            background: var(--gov-gold-lt);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .gov-letterhead-title {
            color: #fff;
        }
        .gov-letterhead-title .org-name {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .gov-letterhead-title .org-sub {
            font-size: 12px;
            color: var(--gov-line);
            letter-spacing: 0.05em;
        }

        /* ===== PAGE WRAPPER ===== */
        .gov-page {
            max-width: 960px;
            margin: 28px auto 48px;
            padding: 0 16px;
        }

        /* ===== DOC TITLE STRIP ===== */
        .doc-title-strip {
            background: var(--gov-white);
            border: 1px solid var(--gov-border);
            border-left: 5px solid var(--gov-navy);
            border-radius: 2px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .doc-title-strip h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gov-navy);
            margin: 0;
            letter-spacing: 0.02em;
        }
        .doc-ref-badge {
            background: var(--gov-navy);
            color: #fff;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 2px;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        /* ===== BREADCRUMB ===== */
        .gov-breadcrumb {
            font-size: 12.5px;
            color: var(--gov-muted);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .gov-breadcrumb a {
            color: var(--gov-navy);
            text-decoration: none;
        }
        .gov-breadcrumb a:hover { text-decoration: underline; }
        .gov-breadcrumb .sep { color: var(--gov-border); }

        /* ===== ERROR NOTICE ===== */
        .gov-alert-error {
            background: #fff5f5;
            border: 1px solid #d9534f;
            border-left: 5px solid var(--gov-red);
            border-radius: 2px;
            padding: 12px 16px;
            margin-bottom: 18px;
            color: var(--gov-red);
            font-size: 14px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        /* ===== CARDS ===== */
        .gov-card {
            background: var(--gov-white);
            border: 1px solid var(--gov-border);
            border-radius: 2px;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .gov-card-header {
            background: var(--gov-navy);
            color: #fff;
            padding: 9px 18px;
            font-size: 13.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gov-card-header.gold {
            background: var(--gov-gold);
            color: #fff;
        }
        .gov-card-header.section {
            background: #eae6da;
            color: var(--gov-navy);
            border-bottom: 1px solid var(--gov-border);
        }
        .gov-card-body { padding: 18px; }

        /* ===== DATA TABLE ===== */
        .gov-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14.5px;
        }
        .gov-data-table tr {
            border-bottom: 1px solid #ede9de;
        }
        .gov-data-table tr:last-child { border-bottom: none; }
        .gov-data-table td {
            padding: 8px 6px;
            vertical-align: top;
        }
        .gov-data-table td.label {
            color: var(--gov-muted);
            width: 42%;
            padding-left: 0;
        }
        .gov-data-table td.value {
            font-weight: 600;
            color: var(--gov-text);
        }

        /* Amount row */
        .amount-row {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid var(--gov-navy);
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .amount-row .amount-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--gov-navy);
        }
        .amount-row .amount-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--gov-red);
            letter-spacing: -0.02em;
        }
        .amount-row .amount-unit {
            font-size: 14px;
            font-weight: 400;
            color: var(--gov-muted);
            margin-left: 4px;
        }

        /* ===== QR SECTION ===== */
        .qr-wrap {
            text-align: center;
            padding: 14px;
        }
        .qr-wrap img {
            max-width: 200px;
            border: 1px solid var(--gov-border);
            padding: 8px;
            background: #fff;
        }
        .qr-amount-badge {
            display: inline-block;
            margin-top: 10px;
            background: var(--gov-navy);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 5px 18px;
            border-radius: 2px;
            letter-spacing: 0.04em;
        }
        .qr-sub {
            font-size: 12px;
            color: var(--gov-muted);
            margin-top: 6px;
        }

        /* ===== NOTICE BOX ===== */
        .gov-notice {
            background: #fdfbf3;
            border: 1px solid var(--gov-line);
            border-left: 4px solid var(--gov-gold);
            border-radius: 2px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 13.5px;
        }
        .gov-notice .notice-title {
            font-weight: 700;
            color: var(--gov-navy);
            margin-bottom: 6px;
            font-size: 13.5px;
        }
        .gov-notice ol {
            margin: 0;
            padding-left: 18px;
            color: var(--gov-text);
        }
        .gov-notice ol li { margin-bottom: 3px; }
        .gov-notice strong { color: var(--gov-red); }

        /* ===== UPLOAD ZONE ===== */
        .upload-zone {
            border: 2px dashed var(--gov-border);
            border-radius: 2px;
            padding: 28px 20px;
            text-align: center;
            background: #fafaf7;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: var(--gov-navy);
            background: #f0eee8;
        }
        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .upload-zone-icon { font-size: 32px; color: var(--gov-muted); margin-bottom: 8px; }
        .upload-zone-text { font-size: 14px; color: var(--gov-muted); }
        .upload-zone-hint { font-size: 12px; color: #999; margin-top: 4px; }

        /* Preview */
        #slip_preview_wrap {
            margin-top: 14px;
            text-align: center;
        }
        #slip_preview_wrap img {
            max-height: 180px;
            border: 1px solid var(--gov-border);
            padding: 4px;
            background: #fff;
        }
        .preview-label {
            font-size: 12px;
            color: var(--gov-muted);
            margin-bottom: 6px;
        }

        /* ===== SUBMIT BUTTON ===== */
        .gov-submit-btn {
            display: block;
            width: 100%;
            background: var(--gov-navy);
            color: #fff;
            border: none;
            padding: 13px 24px;
            font-family: 'Sarabun', sans-serif;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.06em;
            border-radius: 2px;
            cursor: pointer;
            margin-top: 18px;
            transition: background 0.18s;
            text-transform: uppercase;
        }
        .gov-submit-btn:hover { background: #253f7a; }
        .gov-submit-btn:active { background: #12224a; }

        /* ===== FOOTER STAMP ===== */
        .gov-footer-note {
            text-align: center;
            font-size: 12px;
            color: var(--gov-muted);
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid var(--gov-border);
            letter-spacing: 0.03em;
        }

        /* ===== DIVIDER LINE (gold) ===== */
        .gov-divider {
            border: none;
            border-top: 1px solid var(--gov-line);
            margin: 14px 0;
        }

        /* responsive */
        @media (max-width: 768px) {
            .doc-title-strip { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>
</head>

<body>

    <!-- Government Letterhead -->

    <?php include './includes/user_navbar.php'; ?>

    <div class="gov-page">

        <!-- Breadcrumb -->
        <div class="gov-breadcrumb">
            <a href="users/my_request.php">คำร้องของฉัน</a>
            <span class="sep">›</span>
            <a href="#">คำร้องเลขที่ #<?= $request_id ?></a>
            <span class="sep">›</span>
            <span>ชำระค่าธรรมเนียม</span>
        </div>

        <!-- Document Title Strip -->
        <div class="doc-title-strip">
            <h1>ใบแจ้งการชำระค่าธรรมเนียมป้าย</h1>
            <div class="doc-ref-badge">เลขที่คำร้อง: #<?= $request_id ?></div>
        </div>

        <!-- Error Alert -->
        <?php if (isset($error)): ?>
        <div class="gov-alert-error">
            <span>⚠</span>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <div class="row g-3">

            <!-- LEFT COLUMN -->
            <div class="col-md-5">

                <!-- Request Details -->
                <div class="gov-card">
                    <div class="gov-card-header">
                        <span>📋</span> รายละเอียดคำร้อง
                    </div>
                    <div class="gov-card-body">
                        <table class="gov-data-table">
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
                            <span class="amount-label">ยอดค่าธรรมเนียมที่ต้องชำระ</span>
                            <span>
                                <span class="amount-value"><?= number_format($amount, 2) ?></span>
                                <span class="amount-unit">บาท</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="gov-card">
                    <div class="gov-card-header gold">
                        <span>📱</span> ชำระผ่านระบบ PromptPay
                    </div>
                    <div class="gov-card-body qr-wrap">
                        <img src="<?= $qr_url ?>" alt="PromptPay QR Code">
                        <br>
                        <div class="qr-amount-badge"><?= number_format($amount, 2) ?> บาท</div>
                        <div class="qr-sub">หมายเลข PromptPay: <?= $promptpay_id ?></div>
                        <div class="qr-sub">เทศบาลเมืองศิลา</div>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-7">
                <div class="gov-card">
                    <div class="gov-card-header">
                        <span>📎</span> แนบหลักฐานการชำระเงิน
                    </div>
                    <div class="gov-card-body">

                        <!-- Instruction Notice -->
                        <div class="gov-notice">
                            <div class="notice-title">ข้อกำหนดในการแนบหลักฐาน</div>
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
                                <div class="upload-zone-icon">⬆</div>
                                <div class="upload-zone-text">คลิกเพื่อเลือกไฟล์ หรือลากและวางไฟล์ที่นี่</div>
                                <div class="upload-zone-hint">JPG / PNG · ขนาดสูงสุด 5 MB</div>
                            </div>

                            <!-- Image Preview -->
                            <div id="slip_preview_wrap" style="display:none;">
                                <div class="preview-label">ตัวอย่างสลิปที่เลือก:</div>
                                <img id="slip_preview" src="#" alt="preview">
                            </div>

                            <hr class="gov-divider">

                            <button type="submit" name="upload_slip" class="gov-submit-btn">
                                ยืนยันการชำระเงิน
                            </button>

                            <p style="font-size:12px; color:var(--gov-muted); text-align:center; margin-top:10px; margin-bottom:0;">
                                การกดปุ่มยืนยัน ถือว่าท่านรับทราบและยอมรับเงื่อนไขการชำระเงินข้างต้น
                            </p>
                        </form>

                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Note -->
        <div class="gov-footer-note">
            เทศบาลเมืองศิลา · ระบบยื่นคำขออนุญาตติดตั้งป้าย
        </div>

    </div><!-- /gov-page -->

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