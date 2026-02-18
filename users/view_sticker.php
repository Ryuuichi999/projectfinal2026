<?php
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/thaibaht.php';

if (!isset($_GET['id'])) {
    die("Invalid Request ID");
}

$request_id = $_GET['id'];
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request || $request['status'] != 'approved') {
    die("เอกสารไม่พร้อมใช้งาน หรือยังไม่ได้รับการอนุมัติ");
}

// Calculate Expiry
$expire_date = date('Y-m-d', strtotime($request['created_at'] . ' + ' . $request['duration_days'] . ' days'));
function getThaiDateShort($date)
{
    if (!$date)
        return "-";
    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.'
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
    <title>สติ๊กเกอร์ใบอนุญาต #<?= $request['permit_no'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #eee;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .sticker {
            width: 100mm;
            height: 100mm;
            background: white;
            border: 2px solid #000;
            padding: 4mm;
            box-sizing: border-box;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media print {
            body {
                background: white;
                display: block;
            }

            .sticker {
                box-shadow: none;
                margin: 0;
                page-break-after: always;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: 100mm 100mm;
                margin: 0;
            }
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 2px;
            margin-bottom: 2px;
        }

        .logo {
            width: 15mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .org-name {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 2px;
        }

        .title {
            font-size: 16pt;
            font-weight: 800;
            text-align: center;
            background: #000;
            color: #fff;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
            line-height: 1.5;
        }

        .permit-no {
            font-size: 20pt;
            font-weight: 900;
            text-align: center;
            color: #d32f2f;
            line-height: 1;
            margin: 2px 0;
        }

        .detail-row {
            font-size: 9pt;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted #ccc;
            padding: 1px 0;
        }

        .detail-row strong {
            font-weight: 700;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 5px;
            border-top: 2px solid #000;
            padding-top: 5px;
        }

        .qr-section {
            text-align: center;
        }

        .expiry {
            text-align: right;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1.2;
        }

        .expiry-date {
            font-size: 14pt;
            color: #d32f2f;
            display: block;
        }
    </style>
</head>

<body>

    <div class="no-print" style="position: fixed; top: 20px; right: 20px; display: flex; gap: 10px;">
        <button onclick="downloadSticker()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 5px; display: flex; align-items: center; gap: 5px;">
            <i class="bi bi-download"></i> ดาวน์โหลดสติ๊กเกอร์ (รูปภาพ)
        </button>
        <button onclick="window.print()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; display: flex; align-items: center; gap: 5px;">
            <i class="bi bi-printer"></i> พิมพ์
        </button>
    </div>

    <div class="sticker" id="sticker-content">
        <div class="header">
            <img src="../image/logosila.png" class="logo" alt="Logo">
            <div class="org-name">เทศบาลเมืองศิลา</div>
        </div>

        <div class="title">ใบอนุญาตติดตั้งป้าย</div>

        <div style="text-align: center; font-size: 10pt;">เลขที่ใบอนุญาต</div>
        <div class="permit-no"><?= htmlspecialchars($request['permit_no']) ?></div>

        <div style="flex-grow: 1;">
            <div class="detail-row">
                <strong>ประเภท:</strong> <span><?= htmlspecialchars($request['sign_type']) ?></span>
            </div>
            <div class="detail-row">
                <strong>ขนาด:</strong> <span><?= $request['width'] ?> x <?= $request['height'] ?> ม.</span>
            </div>
            <div class="detail-row">
                <strong>สถานที่:</strong> <span
                    style="font-size: 9pt;"><?= mb_strimwidth($request['road_name'], 0, 30, '...') ?></span>
            </div>
        </div>

        <div class="footer">
            <div class="qr-section">
                <div id="qrcode"></div>
                <div style="font-size: 8pt; margin-top: 2px;">สแกนตรวจสอบ</div>
            </div>
            <div class="expiry">
                วันหมดอายุ
                <span class="expiry-date"><?= getThaiDateShort(date('Y-m-d', strtotime($expire_date))) ?></span>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var permitUrl = "http://" + window.location.host + "/Project2026/check_permit.php?id=<?= $request['id'] ?>";
            new QRCode(document.getElementById("qrcode"), {
                text: permitUrl,
                width: 60,
                height: 60,
                correctLevel: QRCode.CorrectLevel.M
            });
        });

        function downloadSticker() {
            var element = document.getElementById("sticker-content");
            html2canvas(element, { scale: 3 }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = 'sticker_permit_<?= str_replace('/', '-', $request['permit_no']) ?>.png';
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>
</body>

</html>