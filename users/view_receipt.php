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

// ดึงข้อมูลคำขอ 
$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address, u.phone 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";

// Allow Owner AND Admin/Employee
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee') {
    $sql .= " AND r.user_id = $user_id";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request || $request['status'] !== 'approved') {
    echo "ยังไม่ได้รับอนุมัติ (ใบเสร็จจะออกเมื่อเจ้าหน้าที่ตรวจสอบการชำระเงินแล้ว)";
    exit;
}

function bahtText($amount_number)
{
    return "(" . number_format($amount_number, 2) . " บาท)";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จรับเงิน -
        <?= htmlspecialchars($request['receipt_no']) ?>
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #eee;
            margin: 0;
            padding: 20px;
        }

        .paper-receipt {
            width: 210mm;
            min-height: 148mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            font-family: 'Sarabun', sans-serif;
            color: #000;
        }

        .header-logo {
            text-align: center;
        }

        .header-logo img {
            width: 20mm;
        }

        .receipt-title {
            text-align: center;
            font-size: 20pt;
            font-weight: bold;
            margin-top: 10px;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .receipt-table th,
        .receipt-table td {
            border: 1px solid #000;
            padding: 8px;
        }

        .receipt-table th {
            text-align: center;
            background: #f0f0f0;
        }

        .total-text {
            text-align: center;
            font-weight: bold;
        }

        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            text-align: center;
        }

        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }

            .paper-receipt {
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
            พิมพ์ใบเสร็จ</button>
    </div>

    <div class="paper-receipt">
        <div class="header-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Garuda_Emblem_of_Thailand.svg/1200px-Garuda_Emblem_of_Thailand.svg.png"
                alt="Logo">
            <div><strong>เทศบาลเมืองศิลา</strong></div>
        </div>

        <div class="receipt-title">ใบเสร็จรับเงิน</div>

        <div class="receipt-info">
            <div>
                ได้รับเงินจาก <strong>
                    <?= htmlspecialchars($request['title_name'] . $request['first_name'] . ' ' . $request['last_name']) ?>
                </strong><br>
                ที่อยู่
                <?= htmlspecialchars($request['applicant_address']) ?>
            </div>
            <div style="text-align: right;">
                เลขที่ <strong>
                    <?= htmlspecialchars($request['receipt_no']) ?>
                </strong><br>
                วันที่ <strong>
                    <?= date('d/m/Y', strtotime($request['receipt_date'])) ?>
                </strong>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ลำดับ</th>
                    <th>รายการ</th>
                    <th style="width: 150px;">จำนวนเงิน (บาท)</th>
                    <th style="width: 100px;">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        ค่าธรรมเนียมปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิวเพื่อการโฆษณา<br>
                        (
                        <?= htmlspecialchars($request['description']) ?> จำนวน
                        <?= $request['quantity'] ?> ป้าย)
                    </td>
                    <td style="text-align: right;">
                        <?= number_format($request['fee'], 2) ?>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2" class="total-text">
                        ตัวอักษร
                        <?= bahtText($request['fee']) ?>
                    </td>
                    <td style="text-align: right;"><strong>
                            <?= number_format($request['fee'], 2) ?>
                        </strong></td>
                    <td>รวมเงิน</td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div class="signature-block">
                <br><br>
                ลงชื่อ...................................................... ผู้รับเงิน<br>
                (......................................................)<br>
                เจ้าพนักงานธุรการ
            </div>
        </div>

    </div>

</body>

</html>