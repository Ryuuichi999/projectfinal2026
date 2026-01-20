<?php
require '../includes/db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// ฟังก์ชันช่วยในการแสดงสถานะเป็น Badge สี
function get_status_badge($status)
{
    switch ($status) {
        case 'pending':
            $class = 'warning';
            $text = '⏳ รอกำลังพิจารณา';
            break;
        case 'waiting_payment':
            $class = 'danger';
            $text = '⚠️ รอชำระเงิน';
            break;
        case 'approved':
            $class = 'success';
            $text = '✅ อนุมัติแล้ว';
            break;
        case 'rejected':
            $class = 'secondary';
            $text = '❌ ไม่อนุมัติ';
            break;
        default:
            $class = 'info';
            $text = $status;
    }
    return "<span class='badge bg-$class'>$text</span>";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สถานะคำขอ</title>
    <?php include '../includes/header.php';  ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* CSS สำหรับป้ายสถานะเพื่อให้สวยงาม */
        .badge {
            padding: 0.5em 0.8em;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
        <div class="card p-4 fade-in-up">
            <h2 class="mb-2">📄 สถานะคำขอของฉัน</h2>
            <p class="text-muted mb-4">รายการคำขออนุญาตติดตั้งป้ายชั่วคราวทั้งหมด</p>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ประเภทป้าย</th>
                            <th>ขนาด (ม.)</th>
                            <th>พื้นที่ (ตร.ม.)</th>
                            <th>ค่าธรรมเนียม (บาท)</th>
                            <th>สถานะ</th>
                            <th>วันที่ยื่น</th>
                            <th>รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM sign_requests WHERE user_id=? ORDER BY id ASC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $area = $row['width'] * $row['height'];
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>{$row['sign_type']}</td>";
                                echo "<td>{$row['width']} x {$row['height']}</td>";
                                echo "<td>" . number_format($area, 2) . "</td>";
                                echo "<td>" . number_format($row['fee']) . "</td>";
                                echo "<td>" . get_status_badge($row['status']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                echo "<td>";
                                echo "<a href='request_detail.php?id={$row['id']}' class='btn btn-sm btn-info'>ดู</a>";
                                if ($row['status'] == 'waiting_payment') {
                                    echo " <a href='../payment.php?id={$row['id']}' class='btn btn-sm btn-success'>ชำระเงิน</a>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted'>ยังไม่มีคำขอที่ถูกยื่น</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
<?php include '../includes/scripts.php'; ?>

</html>