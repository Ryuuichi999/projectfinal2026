<?php
require '../includes/auth.php'; // Session check
require '../includes/db.php';
require '../includes/thaibaht.php';

if (!isset($_GET['id'])) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูล';
    header('Location: my_request.php');
    exit;
}

$request_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

// ตรวจสิทธิ์: เจ้าของคำร้อง หรือ admin/employee เท่านั้น
if (!$request || ($request['user_id'] != $user_id && !in_array($role, ['admin', 'employee']))) {
    $_SESSION['flash_error'] = 'ไม่มีสิทธิ์เข้าถึง';
    header('Location: my_request.php');
    exit;
}

if (!in_array($request['status'], ['approved', 'expired', 'waiting_permit'])) {
    $_SESSION['flash_error'] = 'ใบเสร็จยังไม่พร้อมใช้งาน';
    header('Location: my_request.php');
    exit;
}

// ตรวจสอบว่าเคยดาวน์โหลดฉบับจริงหรือยัง
$is_original = empty($request['receipt_downloaded_at']);

// ถ้ามี action=mark_downloaded (AJAX) ให้บันทึกวันที่ดาวน์โหลดครั้งแรก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_downloaded' && $is_original) {
    $stmt_mark = $conn->prepare("UPDATE sign_requests SET receipt_downloaded_at = NOW() WHERE id = ?");
    $stmt_mark->bind_param("i", $request_id);
    $stmt_mark->execute();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

function getThaiDate($date)
{
    if (!$date)
        return "....................";
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[(int) date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    return "$d $m $y";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จรับเงิน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 14pt;
            background: #eee;
        }

        <?php if (!$is_original): ?>
        /* === สำเนา: โทนเทา === */
        .copy-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80pt;
            color: rgba(200, 0, 0, 0.08);
            font-weight: bold;
            z-index: 10;
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 15px;
        }
        th { background-color: #f0f0f0 !important; }
        .logo-top { filter: grayscale(100%); opacity: 0.7; }
        .watermark { filter: grayscale(100%); }
        <?php else: ?>
        /* === ฉบับจริง: สีดำทางการ === */
        .page { border: none; }
        th { background-color: #e8e8e8 !important; color: #000 !important; font-weight: bold; }
        .title { color: #000; font-size: 22pt; }
        .subtitle { color: #000; font-weight: 600; }
        table { border-color: #000; }
        th, td { border-color: #000; }
        .total-row td { background-color: #f5f5f5; }
        .header { border-bottom: none; padding-bottom: 15px; }
        .original-badge {
            position: absolute;
            top: 20mm;
            left: 20mm;
            background: #e8e8e8;
            color: #000;
            padding: 5px 20px;
            border-radius: 4px;
            font-size: 11pt;
            font-weight: bold;
            z-index: 10;
            letter-spacing: 2px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        <?php endif; ?>

        .page {
            width: 210mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            min-height: 297mm;
            position: relative;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }

            .page {
                width: 210mm;
                height: 297mm;
                margin: 0;
                padding: 15mm 20mm;
                box-shadow: none;
                border: none;
                overflow: hidden;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 0;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            th {
                background-color: #e8e8e8 !important;
                color: #000 !important;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            margin-top: 20px;
        }

        .logo {
            width: 80px;
            position: absolute;
            top: 20mm;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0.1;
        }

        .logo-top {
            width: 120px;
            display: block;
            margin: 0 auto 15px;
        }

        .receipt-no {
            position: absolute;
            top: 37mm;
            right: 1mm;
            text-align: right;
        }

        .receipt-no div {
            margin-bottom: 5px;
        }

        .title {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 5px;
            margin-top: 40px;
        }

        .subtitle {
            font-size: 14pt;
        }

        .info-row {
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 8px;
            vertical-align: top;
        }

        th {
            text-align: center;
            background-color: #f0f0f0;
        }

        .total-row td {
            font-weight: bold;
        }

        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            width: 40%;
            line-height: 1.9;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 520px;
            opacity: 0.10;
            z-index: 0;
            pointer-events: none;
        }

        .content-layer {
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div class="no-print" style="text-align: center; padding: 10px; display: flex; justify-content: center; gap: 10px; align-items: center; flex-wrap: wrap;">
        <?php if ($is_original): ?>
            <span style="background: #155724; color: white; padding: 6px 18px; border-radius: 20px; font-size: 14px; font-weight: bold;">ฉบับจริง</span>
        <?php else: ?>
            <span style="background: #6c757d; color: white; padding: 6px 18px; border-radius: 20px; font-size: 14px;">สำเนา (พิมพ์แล้วเมื่อ <?= getThaiDate($request['receipt_downloaded_at']) ?>)</span>
        <?php endif; ?>
        <button onclick="printReceipt()"
            style="padding: 10px 24px; font-size: 14px; cursor: pointer; background: #0d6efd; color: white; border: none; border-radius: 5px; font-weight: 600;">
            <span style="margin-right:5px;">🖨</span> พิมพ์ใบเสร็จ
        </button>
    </div>

    <div class="page">
        <!-- Watermark -->
        <img src="../image/logoใบเสร็จ.png" class="watermark" alt="watermark">
        
        <?php if (!$is_original): ?>
            <div class="copy-watermark">สำเนา</div>
        <?php endif; ?>
        <div class="content-layer">
            <!-- Logo Top -->
            <div style="text-align: center;">
                <img src="../image/<?= $is_original ? 'Logoจริง.png' : 'logoใบเสร็จ.png' ?>" class="logo-top" alt="Logo">
            </div>

            <div class="receipt-no">
                <table style="border:none; border-collapse:collapse;">
                    <tr><td style="border:none; padding:2px 2px; text-align:right;"><strong>เลขที่</strong></td><td style="border:none; padding:2px 5px;"><?= htmlspecialchars($request['receipt_no'] ?? '-') ?></td></tr>
                    <tr><td style="border:none; padding:2px 5px; text-align:right;"><strong>วันที่</strong></td><td style="border:none; padding:2px 5px;"><?= getThaiDate($request['receipt_date'] ?? date('Y-m-d')) ?></td></tr>
                </table>
            </div>

            <div class="header">
                <div class="title">ใบเสร็จรับเงิน</div>
                <div class="subtitle" style="margin-top: 50px;">เทศบาลเมืองศิลา อำเภอเมืองขอนแก่น จังหวัดขอนแก่น</div>
            </div>

            <div class="info-row">
                ได้รับเงินจาก <strong><?= htmlspecialchars($request['applicant_name']) ?></strong>
            </div>
            <div class="info-row" style="font-size:12pt;">
                ที่อยู่ <?= htmlspecialchars($request['applicant_address'] ?: $request['user_address']) ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">ลำดับ</th>
                    <th style="width: 60%;">รายการ</th>
                    <th style="width: 15%;">จำนวนเงิน (บาท)</th>
                    <th style="width: 15%;">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <div style="padding-bottom:4px;">ค่าธรรมเนียมปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา</div>
                        <div>(<?= htmlspecialchars($request['sign_type']) ?> ขนาด <?= $request['width'] ?> x <?= $request['height'] ?> ม. จำนวน <?= $request['quantity'] ?> ป้าย)</div>
                    </td>
                    <td style="text-align: right;">
                        <?= number_format($request['fee'], 2) ?>
                    </td>
                    <td></td>
                </tr>
                <!-- Padding rows to fill space -->
                <tr style="height: 60px;">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" style="text-align: center;">
                        ตัวอักษร (
                        <?= ThaiBahtConversion($request['fee']) ?>)
                    </td>
                    <td style="text-align: right;">
                        <?= number_format($request['fee'], 2) ?>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <div style="width: 50%;">
                <br>
                ไว้เป็นการถูกต้องแล้ว
            </div>
            <div class="signature">
                <div
                    style="display: flex; align-items: flex-end; justify-content: center; gap: 15px; margin-bottom: 5px;">
                    <span>(ลงชื่อ)</span>
                    <?php
                    require_once '../includes/settings_helper.php';
                    $r_sig_path = getSetting($conn, 'receipt_signature_path', 'image/ลายเซ็น2.png');
                    if (file_exists("../" . $r_sig_path)) {
                        echo '<img src="../' . $r_sig_path . '" style="height: 70px;">';
                    }
                    ?>
                    <span>ผู้รับเงิน</span>
                </div>
                (<?= htmlspecialchars(getSetting($conn, 'receipt_signer_name', '........................................................')) ?>)<br>
                ตำแหน่ง <?= htmlspecialchars(getSetting($conn, 'receipt_signer_position', 'เจ้าพนักงานธุรการ')) ?>
            </div>
        </div>
    </div>

    <script>
        var isOriginal = <?= $is_original ? 'true' : 'false' ?>;

        function markAsDownloaded() {
            if (!isOriginal) return Promise.resolve();
            return fetch('view_receipt.php?id=<?= $request_id ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_downloaded'
            })
                .then(function(r) { return r.json(); })
                .then(function() { isOriginal = false; });
        }

        function printReceipt() {
            // เปิด print dialog ของ browser
            window.print();
            // ไม่ว่าจะพิมพ์จริงหรือกดยกเลิก → mark เป็นสำเนาทันที
            if (isOriginal) {
                markAsDownloaded().then(function() {
                    setTimeout(function() { location.reload(); }, 300);
                });
            }
        }

        // Fallback: ถ้า afterprint event ทำงาน ก็ mark ด้วย (บาง browser)
        window.addEventListener('afterprint', function() {
            if (isOriginal) {
                markAsDownloaded().then(function() {
                    setTimeout(function() { location.reload(); }, 300);
                });
            }
        });
    </script>
</body>

</html>