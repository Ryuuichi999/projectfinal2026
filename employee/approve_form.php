<?php
session_start();
require '../includes/db.php';

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

// Auto-generate Permission Number (Example: 39/2568)
$current_year_th = date('Y') + 543;
$permit_number = "Wait/" . $current_year_th;

if (isset($_POST['approve_confirm'])) {
    $permit_no = $_POST['permit_no'];
    $permit_date = $_POST['permit_date']; // วันที่ออกหนังสือ

    // Update DB
    $sql_update = "UPDATE sign_requests SET status = 'waiting_payment', permit_no = ?, permit_date = ? WHERE id = ?";
    $stmt_up = $conn->prepare($sql_update);
    $stmt_up->bind_param("ssi", $permit_no, $permit_date, $request_id);

    if ($stmt_up->execute()) {
        echo "<script>alert('ออกหนังสืออนุญาตเรียบร้อยแล้ว'); window.location.href='request_list.php';</script>";
        exit;
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ออกหนังสืออนุญาต</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .paper-a4 {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            font-family: 'Sarabun', sans-serif;
            font-size: 16pt;
            line-height: 1.6;
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
            body * {
                visibility: hidden;
            }

            .paper-a4,
            .paper-a4 * {
                visibility: visible;
            }

            .paper-a4 {
                position: absolute;
                left: 0;
                top: 0;
                margin: 0;
                padding: 20mm;
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
        <div class="card mb-3">
            <div class="card-body">
                <h5>จัดการคำขอ #
                    <?= $request['id'] ?>
                </h5>
                <form method="post">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label>เลขที่หนังสือ</label>
                            <input type="text" name="permit_no" class="form-control"
                                value="<?= htmlspecialchars($permit_number) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label>วันที่ออกหนังสือ</label>
                            <input type="date" name="permit_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer"></i> พิมพ์ตัวอย่าง
                            </button>
                            <button type="submit" name="approve_confirm" class="btn btn-success"
                                onclick="return confirm('ยืนยันการออกหนังสืออนุญาต?');">
                                <i class="bi bi-check-circle"></i> ยืนยันออกหนังสือ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Permission Letter Preview -->
    <div class="paper-a4">
        <!-- Garuda Placeholder (User can replace with upload) -->
        <div style="text-align: center;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Garuda_Emblem_of_Thailand.svg/1200px-Garuda_Emblem_of_Thailand.svg.png"
                class="garuda" alt="Garuda">
        </div>

        <div class="header">
            <h3>หนังสืออนุญาต</h3>
        </div>

        <div class="doc-num">
            เลขที่ <span class="fw-bold">
                <?= htmlspecialchars($permit_number) ?>
            </span>
        </div>

        <div class="text-center mb-4">
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

        <div class="content-para mt-3">
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

        <div class="content-para mt-3">
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

        <div class="content-para mt-3">
            (๔) ได้รับเงินค่าธรรมเนียม จำนวน <strong>
                <?= number_format($request['fee'], 2) ?>
            </strong> บาท
        </div>

        <div class="content-para mt-3">
            (๕) หนังสืออนุญาตให้ไว้ ณ วันที่ <strong>
                <?= date('d/m/Y') ?>
            </strong>
        </div>

        <div class="signature-section">
            <br><br>
            ลงชื่อ................................................................<br>
            (................................................................)<br>
            ตำแหน่ง..........................................................<br>
            เจ้าพนักงานท้องถิ่น
        </div>

        <div class="d-none">แบบ ร.ส. ๒</div>
    </div>

</body>

</html>