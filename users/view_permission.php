<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "ไม่พบคำขอ";
    exit;
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// ดึงข้อมูลคำขอ (ต้องเป็นเจ้าของ หรือ เป็น admin/employee)
$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address, u.phone 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";

// ถ้าไม่ใช่ admin/employee ต้องเช็คว่าเป็นเจ้าของ
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee') {
    $sql .= " AND r.user_id = $user_id";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo "ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เข้าถึง";
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>หนังสืออนุญาต -
        <?= htmlspecialchars($request['permit_no']) ?>
    </title>
    <!-- ใช้ CSS เดียวกับหน้า approve_form.php -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #eee;
            margin: 0;
            padding: 20px;
        }

        .paper-a4 {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            font-family: 'Sarabun', sans-serif;
            font-size: 16pt;
            line-height: 1.6;
            color: #000;
        }

        .garuda {
            width: 30mm;
            display: block;
            margin: 0 auto 10mm;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 5mm;
        }

        .doc-num {
            position: absolute;
            top: 40mm;
            right: 20mm;
        }

        .content-para {
            text-align: justify;
            text-indent: 15mm;
            margin-bottom: 2mm;
        }

        .signature-section {
            margin-top: 20mm;
            text-align: right;
            margin-right: 10mm;
        }

        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .paper-a4 {
                box-shadow: none;
                margin: 0;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">🖨️
            พิมพ์หนังสืออนุญาต</button>
    </div>

    <!-- Permission Letter Preview -->
    <div class="paper-a4">
        <div style="text-align: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Garuda_Emblem_of_Thailand.svg/1200px-Garuda_Emblem_of_Thailand.svg.png"
                class="garuda" alt="Garuda">
        </div>

        <div class="header">
            <h3>หนังสืออนุญาต</h3>
        </div>

        <div class="doc-num">
            เลขที่ <span style="font-weight: bold;">
                <?= htmlspecialchars($request['permit_no']) ?>
            </span>
        </div>

        <div class="text-center" style="text-align: center; margin-bottom: 20px;">
            <strong>องค์การบริหารส่วนตำบลบ้านเหล่า</strong>
        </div>

        <div class="content-para">
            (๑) อนุญาตให้ <strong>
                <?= htmlspecialchars($request['title_name'] . $request['first_name'] . ' ' . $request['last_name']) ?>
            </strong>
            เลขประจำตัวประชาชน <strong>
                <?= htmlspecialchars($request['citizen_id']) ?>
            </strong>
        </div>
        <div class="content-para">
            อยู่บ้านเลขที่
            <?= htmlspecialchars($request['applicant_address']) ?>
        </div>

        <div class="content-para" style="margin-top: 15px;">
            (๒) โฆษณา ติดตั้งป้ายโฆษณาได้ ณ ที่ <strong>
                <?= htmlspecialchars($request['road_name']) ?>
            </strong>
        </div>
        <div class="content-para">
            ข้อความ <strong>
                <?= htmlspecialchars($request['description']) ?>
            </strong>
            จำนวน <strong>
                <?= htmlspecialchars($request['quantity']) ?>
            </strong> ป้าย
        </div>

        <div class="content-para" style="margin-top: 15px;">
            (๓) ตั้งแต่วันที่ <strong>
                <?= date('d/m/Y', strtotime($request['created_at'])) ?>
            </strong>
            ถึงวันที่ <strong>
                <?= date('d/m/Y', strtotime($request['created_at'] . ' + ' . $request['duration_days'] . ' days')) ?>
            </strong>
        </div>
        <div class="content-para">
            รวมกำหนดเวลาอนุญาต <strong>
                <?= $request['duration_days'] ?>
            </strong> วัน
        </div>

        <div class="content-para" style="margin-top: 15px;">
            (๔) ได้รับเงินค่าธรรมเนียม จำนวน <strong>
                <?= number_format($request['fee'], 2) ?>
            </strong> บาท
        </div>

        <div class="content-para" style="margin-top: 15px;">
            (๕) หนังสืออนุญาตให้ไว้ ณ วันที่ <strong>
                <?= date('d/m/Y', strtotime($request['permit_date'])) ?>
            </strong>
        </div>

        <div class="signature-section">
            <br><br>
            ลงชื่อ................................................................<br>
            (................................................................)<br>
            ตำแหน่ง..........................................................<br>
            เจ้าพนักงานท้องถิ่น
        </div>

        <div style="position: absolute; bottom: 20mm; left: 20mm; font-size: 12pt;">แบบ ร.ส. ๒</div>
    </div>

</body>

</html>