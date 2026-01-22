<?php
session_start();
require '../includes/db.php';

// Allow 'admin' and 'employee'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: request_list.php");
    exit;
}

$request_id = $_GET['id'];
$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address, u.phone 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo "ไม่พบข้อมูลคำขอ";
    exit;
}

// Receipt Number Logic (Example: RCPT-01362/68)
// In a real system, you might want to query the max receipt ID from DB or use a sequence.
$current_year_th = date('Y') + 543;
$receipt_number = "RCPT-" . str_pad($request['id'], 5, "0", STR_PAD_LEFT) . "/" . substr($current_year_th, 2);

if (isset($_POST['issue_confirm'])) {
    $rcpt_no = $_POST['rcpt_no'];
    $rcpt_date = $_POST['rcpt_date'];

    // Update DB: status -> approved
    $sql_update = "UPDATE sign_requests SET status = 'approved', receipt_no = ?, receipt_date = ? WHERE id = ?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("ssi", $rcpt_no, $rcpt_date, $request_id);

    if ($stmt_up->execute()) {
        echo "<script>alert('ออกใบเสร็จและอนุมัติสำเร็จ'); window.location.href='request_list.php';</script>";
        exit;
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

function bahtText($amount_number)
{
    // Placeholder for BahtText function. 
    // You can implement a full conversion function here if needed.
    return "(" . number_format($amount_number, 2) . " บาท)";
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ออกใบเสร็จรับเงิน</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .paper-receipt {
            width: 210mm;
            /* A4 width */
            min-height: 148mm;
            /* Half A4 height approx */
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            font-family: 'Sarabun', sans-serif;
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
            body * {
                visibility: hidden;
            }

            .paper-receipt,
            .paper-receipt * {
                visibility: visible;
            }

            .paper-receipt {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-4 no-print">
        <a href="request_list.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> กลับ</a>
        <div class="card">
            <div class="card-body">
                <h5>ออกใบเสร็จรับเงิน #
                    <?= $request['id'] ?>
                </h5>
                <form method="post">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label>เลขที่ใบเสร็จ</label>
                            <input type="text" name="rcpt_no" class="form-control"
                                value="<?= htmlspecialchars($receipt_number) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label>วันที่</label>
                            <input type="date" name="rcpt_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer"></i> ตรวจสอบก่อนพิมพ์
                            </button>
                            <button type="submit" name="issue_confirm" class="btn btn-success"
                                onclick="return confirm('ยืนยันออกใบเสร็จและอนุมัติ?');">
                                <i class="bi bi-cash-coin"></i> ยืนยันออกใบเสร็จ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
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
                    <?= date('d/m/Y') ?>
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