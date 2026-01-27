<?php
require '../includes/auth.php'; // Session check
require '../includes/db.php';
require '../includes/thaibaht.php';

if (!isset($_GET['id'])) {
    die("Invalid Request ID");
}

$request_id = $_GET['id'];
$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address 
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
    $d = date('j', $timestamp); // วันที่ไม่มี 0 นำหน้า
    $m = $months[(int) date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;

    // แปลงตัวเลขเป็นเลขไทย
    $thai_digits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $standard_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $d = str_replace($standard_digits, $thai_digits, $d);
    $y = str_replace($standard_digits, $thai_digits, $y);

    return "$d $m $y";
}

function toThaiNum($number)
{
    $thai_digits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $standard_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($standard_digits, $thai_digits, $number);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>หนังสืออนุญาต (แบบ ร.ส. ๒)</title>
    <!-- ใช้ Font Sarabun สำหรับเอกสารราชการ -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #eee;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 25mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            position: relative;
            line-height: 1.6;
            font-size: 16pt;
        }

        @media print {
            body {
                background: white;
                margin: 0;
            }

            .page {
                box-shadow: none;
                margin: 0;
                width: auto;
                height: auto;
            }

            .no-print {
                display: none;
            }
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-justify {
            text-align: justify;
        }

        .bold {
            font-weight: bold;
        }

        .header-garuda {
            text-align: center;
            margin-bottom: 20px;
        }

        .garuda-img {
            width: 3cm;
        }

        .doc-title {
            font-size: 24pt;
            font-weight: bold;
            margin-top: 10px;
        }

        .doc-no {
            position: absolute;
            top: 20mm;
            right: 25mm;
        }

        .form-code {
            position: absolute;
            top: 20mm;
            left: 25mm;
        }

        .content {
            margin-top: 30px;
        }

        .indent {
            text-indent: 2.5cm;
        }

        .signature-block {
            margin-top: 50px;
            margin-left: 50%;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="no-print text-center py-3">
        <button onclick="window.print()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer;">พิมพ์เอกสาร</button>
    </div>

    <div class="page">
        <!-- รหัสแบบฟอร์ม -->
        <div class="field text-right" style="position: absolute; top: 15mm; right: 20mm;">
            แบบ ร.ส. ๒
        </div>

        <div class="header-garuda">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Garuda_Emblem_of_Thailand.svg/1200px-Garuda_Emblem_of_Thailand.svg.png"
                class="garuda-img" alt="Garuda">
            <div class="doc-title">หนังสืออนุญาต</div>
        </div>

        <div class="doc-no">
            เลขที่
            <?= toThaiNum(htmlspecialchars($request['permit_no'])) ?>
        </div>

        <div class="content">
            <div class="indent">
                ๑. อนุญาตให้ <span class="bold">
                    <?= htmlspecialchars($request['applicant_name']) ?>
                </span>
                อยู่บ้านเลขที่ <span class="bold">
                    <?= htmlspecialchars($request['applicant_address']) ?>
                </span>
            </div>

            <div class="indent" style="margin-top: 10px;">
                ๒. โฆษณาด้วยการปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา ได้ ณ ที่
                <br>
                <div style="padding-left: 2.5cm;">
                    <span class="bold">
                        <?= htmlspecialchars($request['road_name']) ?>
                    </span>
                </div>
                <div style="padding-left: 2.5cm;">
                    ข้อความ <span class="bold">
                        <?= htmlspecialchars($request['description']) ?>
                    </span>
                    จำนวน <span class="bold">
                        <?= toThaiNum($request['quantity']) ?>
                    </span> ป้าย
                </div>
            </div>

            <div class="indent" style="margin-top: 10px;">
                ๓. ตั้งแต่วันที่ <span class="bold">
                    <?= getThaiDate($request['created_at']) ?>
                </span>
                ถึง วันที่ <span class="bold">
                    <?= getThaiDate(date('Y-m-d', strtotime($request['created_at'] . ' + ' . $request['duration_days'] . ' days'))) ?>
                </span>
            </div>
            <div style="padding-left: 2.5cm;">
                รวมกำหนดเวลาอนุญาต <span class="bold">
                    <?= toThaiNum($request['duration_days']) ?>
                </span> วัน
            </div>

            <div class="indent" style="margin-top: 10px;">
                ๔. ได้รับค่าธรรมเนียม จำนวน <span class="bold">
                    <?= toThaiNum(number_format($request['fee'], 0)) ?>
                </span> บาท
                (
                <?= ThaiBahtConversion($request['fee']) ?>)
            </div>

            <div class="indent" style="margin-top: 10px;">
                ๕. หนังสืออนุญาตให้ไว้ ณ วันที่ <span class="bold">
                    <?= getThaiDate($request['permit_date']) ?>
                </span>
            </div>
        </div>

        <div class="signature-block">
            <br><br>
            ................................................................<br>
            (................................................................)<br>
            ตำแหน่ง..........................................................<br>
            เจ้าพนักงานท้องถิ่น<br>
            หรือพนักงานเจ้าหน้าที่ผู้ออกหนังสืออนุญาต
        </div>

    </div>

</body>

</html>