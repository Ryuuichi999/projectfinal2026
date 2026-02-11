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
        case 'reviewing':
            $class = 'primary';
            $text = '🔎 กำลังพิจารณา';
            break;
        case 'need_documents':
            $class = 'info';
            $text = '📑 ขอเอกสารเพิ่ม';
            break;
        case 'waiting_payment':
            $class = 'danger';
            $text = '⚠️ รอชำระเงิน';
            break;
        case 'waiting_receipt':
            $class = 'info';
            $text = '🧾 รอออกใบเสร็จ';
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
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        /* CSS สำหรับป้ายสถานะเพื่อให้สวยงาม */
        .badge {
            padding: 0.5em 0.8em;
        }

        /* ปรับ layout ตารางให้กระชับ ไม่ตัดบรรทัด */
        .table {
            /* font-size: 0.8rem; REMOVED for consistency */
        }

        .table th {
            white-space: nowrap;
            vertical-align: middle;
            background-color: #f8f9fa;
        }

        .table td {
            vertical-align: middle;
            padding: 0.4rem 0.5rem;
        }

        /* คอลัมน์รายละเอียดไม่ให้ตัดบรรทัด + ปุ่มอยู่บรรทัดเดียว */
        td.action-cell {
            white-space: nowrap;
        }

        td.action-cell .btn-group {
            flex-wrap: nowrap;
        }

        td.action-cell .btn {
            /* font-size: 0.85rem; REMOVED */
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

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
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>{$row['sign_type']}</td>";
                                echo "<td>{$row['width']} x {$row['height']}</td>";
                                echo "<td>" . number_format($row['fee']) . "</td>";
                                echo "<td>" . get_status_badge($row['status']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                echo "<td class='action-cell'>";
                                echo "<div class='btn-group' role='group' aria-label='การทำรายการ'>";
                                echo "<a href='request_detail.php?id={$row['id']}' class='btn btn-sm btn-info' title='ดูรายละเอียด'>ดู</a>";
                                if ($row['status'] == 'waiting_payment') {
                                    echo "<a href='../payment.php?id={$row['id']}' class='btn btn-sm btn-success' title='ชำระเงิน'>ชำระเงิน</a>";
                                }
                                if ($row['status'] == 'approved') {
                                    echo "<a href='view_receipt.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-primary' title='พิมพ์ใบเสร็จ'>ใบเสร็จ</a>";
                                    echo "<a href='view_permission.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary' title='พิมพ์ใบอนุญาต'>ใบอนุญาต</a>";
                                }
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
<?php include '../includes/scripts.php'; ?>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('.table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
            },
            "order": [], // Disable initial sort
            "dom": "<'row'<'col-sm-12'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row align-items-center'<'col-md-6'l><'col-md-6 d-flex justify-content-end'p>>",
            "pageLength": 10
        });
    });
</script>

</html>
