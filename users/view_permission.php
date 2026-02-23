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
            padding-left: 3.0cm;
            text-indent: -1.0cm;
        }

        .indent-2 {
            padding-left: 3.0cm;
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

    <div class="no-print" style="text-align: center; padding: 10px; display: flex; justify-content: center; gap: 10px;">
        <button onclick="downloadPDF()"
            style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 5px;">
            ⬇ ดาวน์โหลด PDF
        </button>
        <button onclick="window.print()"
            style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
            🖨 พิมพ์เอกสาร
        </button>
    </div>

    <div class="page">
        <!-- รหัสแบบฟอร์ม (ขวาบน) -->
        <div class="text-right" style="position: absolute; top: 15mm; right: 20mm; font-size: 14pt;">
            แบบ ร.ส. ๒
            <div id="qrcode" style="margin-top: 10px; display: flex; justify-content: flex-end;"></div>
            <div style="font-size: 10pt; margin-top: 5px; color: #666;">สแกนเพื่อตรวจสอบสถานะ</div>
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
            <?php
                $permit_start = !empty($request['permit_date']) ? $request['permit_date'] : $request['created_at'];
                $permit_end   = date('Y-m-d', strtotime($permit_start . ' + ' . ($request['duration_days'] - 1) . ' days'));
            ?>
            <div class="item-block">
                <p class="indent">
                    ๓. ตั้งแต่วันที่ <span class="bold"><?= getThaiDate($permit_start) ?></span>
                    ถึง วันที่ <span class="bold"><?= getThaiDate($permit_end) ?></span>
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
            require_once '../includes/settings_helper.php';

            // 1. Name and Position (Snapshot > Settings)
            $signer_name = !empty($request['permit_signer_name'])
                ? $request['permit_signer_name']
                : getSetting($conn, 'permit_signer_name', '................................................................');

            $signer_pos = !empty($request['permit_signer_position'])
                ? $request['permit_signer_position']
                : getSetting($conn, 'permit_signer_position', 'นายกเทศมนตรีเมืองศิลา');

            // 2. Signature Image (Settings only, we don't snapshot image file path usually)
            // But if we wanted to be perfect we would have copied the file. 
            // For now, use current setting.
            $p_sig_path = getSetting($conn, 'permit_signature_path', '');

            // Check existence
            if ($p_sig_path && file_exists("../" . $p_sig_path)) {
                echo "<img src='../$p_sig_path' style='height: 80px; display: block; margin: 0 auto 0 auto;'>";
            }
            ?>

            <div>(<?= htmlspecialchars($signer_name) ?>)</div>
            <div style="margin-top: 5px; white-space: pre-wrap;"><?= htmlspecialchars($signer_pos) ?></div>
            
            <div>หรือพนักงานเจ้าหน้าที่ผู้ออกหนังสืออนุญาต</div>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var permitUrl = "http://" + window.location.host + "/Project2026/check_permit.php?id=<?= $request['id'] ?>";
            new QRCode(document.getElementById("qrcode"), {
                text: permitUrl,
                width: 70,
                height: 70
            });
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector('.page');
            const origMargin = element.style.margin;
            element.style.margin = '0';
            element.style.height = '297mm';
            element.style.overflow = 'hidden';

            const opt = {
                margin: 0,
                filename: 'permission_<?= str_replace('/', '-', $request['permit_no']) ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    scrollY: 0,
                    width: element.scrollWidth,
                    height: element.scrollHeight
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function () {
                element.style.margin = origMargin;
                element.style.height = '';
                element.style.overflow = '';
            });
        }
    </script>
</body>

</html>