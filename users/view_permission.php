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
            color: #000;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 25mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            position: relative;
            line-height: 1.8;
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

        .bold {
            font-weight: bold;
        }

        .header-garuda {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 0px;
        }

        .garuda-img {
            width: 3cm;
            height: auto;
        }

        .doc-title {
            font-size: 20pt;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .doc-number {
            text-align: right;
            margin-top: 10px;
            margin-bottom: 40px;
            /* Increased spacing below Doc No */
        }

        .content {
            margin-top: 0px;
        }

        .indent {
            text-indent: 2.0cm;
        }

        .indent-2 {
            padding-left: 2.0cm;
        }

        /* Justify content like official docs */
        p {
            margin-bottom: 0px;
            text-align: justify;
        }

        /* Spacing between numbered items */
        .item-block {
            margin-bottom: 20px;
        }

        .signature-section {
            margin-top: 60px;
            float: right;
            text-align: center;
            width: 350px;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>

    <div class="no-print text-center py-3">
        <button onclick="window.print()"
            style="padding: 10px 20px; font-size: 16px; cursor: pointer;">พิมพ์เอกสาร</button>
    </div>

    <div class="page">
        <!-- รหัสแบบฟอร์ม (ขวาบน) -->
        <div class="text-right" style="position: absolute; top: 15mm; right: 20mm; font-size: 14pt;">
            แบบ ร.ส. ๒
        </div>

        <div class="header-garuda">
            <img src="../image/ตราครุฑ.png" class="garuda-img" alt="Garuda">
            <div class="doc-title">หนังสืออนุญาต</div>
        </div>

        <div class="doc-number">
            เลขที่ <?= toThaiNum(htmlspecialchars($request['permit_no'])) ?>
        </div>

        <div class="content">
            <!-- ข้อ 1 -->
            <div class="item-block">
                <p class="indent">
                    ๑. อนุญาตให้ <span class="bold"><?= htmlspecialchars($request['applicant_name']) ?></span>
                    อยู่บ้านเลขที่ <span class="bold"><?= toThaiNum($request['applicant_address']) ?></span>
                </p>
            </div>

            <!-- ข้อ 2 -->
            <div class="item-block">
                <p class="indent">
                    ๒. โฆษณาด้วยการปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา ได้ ณ ที่
                </p>
                <div class="indent-2">
                    ตำบล ศิลา อำเภอ เมืองขอนแก่น จังหวัด ขอนแก่น
                </div>
                <div class="indent-2">
                    ข้อความ <span class="bold"><?= htmlspecialchars($request['description']) ?></span>
                    (<span class="bold"><?= htmlspecialchars($request['road_name']) ?></span>)
                    จำนวน <span class="bold"><?= toThaiNum($request['quantity']) ?></span> ป้าย
                </div>
            </div>

            <!-- ข้อ 3 -->
            <div class="item-block">
                <p class="indent">
                    ๓. ตั้งแต่วันที่ <span class="bold"><?= getThaiDate($request['created_at']) ?></span>
                    ถึง วันที่ <span
                        class="bold"><?= getThaiDate(date('Y-m-d', strtotime($request['created_at'] . ' + ' . $request['duration_days'] . ' days'))) ?></span>
                </p>
                <div class="indent-2">
                    รวมกำหนดเวลาอนุญาต <span class="bold"><?= toThaiNum($request['duration_days']) ?></span> วัน
                </div>
            </div>

            <!-- ข้อ 4 -->
            <div class="item-block">
                <p class="indent">
                    ๔. ได้รับค่าธรรมเนียม จำนวน <span
                        class="bold"><?= toThaiNum(number_format($request['fee'], 0)) ?></span> บาท
                    (<?= ThaiBahtConversion($request['fee']) ?>)
                </p>
            </div>

            <!-- ข้อ 5 -->
            <div class="item-block">
                <p class="indent">
                    ๕. หนังสืออนุญาตนี้ให้ไว้ ณ วันที่ <span
                        class="bold"><?= getThaiDate($request['permit_date']) ?></span>
                </p>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="signature-section">
            <br><br>
            <?php
            // Fetch Approver Name
            $sql_approver = "SELECT title_name, first_name, last_name FROM users WHERE id = ?";
            $stmt_app = $conn->prepare($sql_approver);
            if ($request['approved_by']) {
                $stmt_app->bind_param("i", $request['approved_by']);
                $stmt_app->execute();
                $res_app = $stmt_app->get_result();
                $approver = $res_app->fetch_assoc();
                $approver_name = $approver['title_name'] . $approver['first_name'] . ' ' . $approver['last_name'];
            } else {
                $approver_name = "(................................................................)";
            }

            // Signature Image (Check if exists)
            $sig_path = "../image/signatures/" . $request['approved_by'] . ".png";

            // Specific mapping for Employee (ID 2)
            if ($request['approved_by'] == 2) {
                $sig_path = "../image/ลายเซ็น.png";
            }

            if ($request['approved_by'] && file_exists($sig_path)) {
                echo "<img src='$sig_path' style='height: 80px; display: block; margin: 0 auto 0 auto;'>";
            }
            ?>


            <div>(
                <?= $approver_name ?>)
            </div>

            <div style="margin-top: 20px;">เจ้าพนักงานท้องถิ่น</div>
            <div>หรือพนักงานเจ้าหน้าที่ผู้ออกหนังสืออนุญาต</div>
        </div>

    </div>

</body>

</html>