<?php
require '../includes/auth.php'; // Session check
require '../includes/db.php';
require '../includes/thaibaht.php';

if (!isset($_GET['id'])) {
    die("Invalid Request ID");
}

$request_id = $_GET['id'];
$sql = "SELECT r.*, u.citizen_id 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request || $request['status'] != 'approved') {
    die("ใบเสร็จยังไม่พร้อมใช้งาน");
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
                background: white;
            }

            .page {
                width: 100%;
                margin: 0;
                padding: 10mm;
                box-shadow: none;
                border: none;
            }

            .no-print {
                display: none !important;
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
            top: 20mm;
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
        }

        .subtitle {
            font-size: 16pt;
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
    <div class="no-print" style="text-align: center; padding: 10px;">
        <button onclick="window.print()" style="padding: 10px 20px;">พิมพ์ใบเสร็จ</button>
    </div>

    <div class="page">
        <!-- Watermark -->
        <img src="../image/logoใบเสร็จ.png" class="watermark" alt="watermark">

        <div class="content-layer">
            <!-- Logo Top -->
            <div style="text-align: center;">
                <img src="../image/logoใบเสร็จ.png" class="logo-top" alt="Logo">
            </div>

            <div class="receipt-no">
                <div>เลขที่
                    <?= htmlspecialchars($request['receipt_no'] ?? 'Wait') ?>
                </div>
                <div>วันที่
                    <?= getThaiDate($request['receipt_date'] ?? date('Y-m-d')) ?>
                </div>
            </div>

            <div class="header">
                <div class="title">ใบเสร็จรับเงิน</div>
                <div class="subtitle" style="margin-top: 20px;">เทศบาลเมืองศิลา อำเภอเมืองขอนแก่น จังหวัดขอนแก่น</div>
            </div>

            <div class="info-row">
                ได้รับเงินจาก: <strong>
                    <?= htmlspecialchars($request['applicant_name']) ?>
                </strong>
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
                        ค่าธรรมเนียมปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา
                    </td>
                    <td style="text-align: right;">
                        <?= number_format($request['fee'], 2) ?>
                    </td>
                    <td></td>
                </tr>
                <!-- Padding rows to fill space -->
                <tr style="height: 100px;">
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
                    <div style="height: 70px;"></div>
                    (ลงชื่อ)........................................................ ผู้รับเงิน<br>
                    (<?= htmlspecialchars($request['receipt_issued_by'] ?? '........................................................') ?>)<br>
                    ตำแหน่ง........................................................
                </div>
            </div>
        </div>
    </div>
</body>

</html>