<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
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

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card shadow-sm border-0">
                    <div
                        class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>รายการคำขอของฉัน</h5>
                        <a href="request_form.php" class="btn btn-light btn-sm fw-bold text-primary">
                            <i class="bi bi-plus-lg"></i> ยื่นคำขอใหม่
                        </a>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table id="myRequestsTable" class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" width="5%">#</th>
                                        <th width="15%">ประเภทป้าย</th>
                                        <th width="15%">ขนาด (ม.)</th>
                                        <th class="text-center" width="10%">ค่าธรรมเนียม</th>
                                        <th class="text-center" width="15%">สถานะ</th>
                                        <th width="15%">วันที่ยื่น</th>
                                        <th class="text-center" width="15%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT * FROM sign_requests WHERE user_id=? ORDER BY id DESC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $_SESSION['user_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $badge = get_status_badge($row['status']);
                                            $date = date('d/m/Y', strtotime($row['created_at']));
                                            $size = "{$row['width']} x {$row['height']}";
                                            $fee = number_format($row['fee']);

                                            echo "<tr>";
                                            echo "<td class='text-center text-muted'>{$row['id']}</td>";
                                            echo "<td class='fw-bold text-primary'>{$row['sign_type']}</td>";
                                            echo "<td><span class='badge bg-light text-dark border'>{$size}</span></td>";
                                            echo "<td class='text-center'>{$fee}</td>";
                                            echo "<td class='text-center'>{$badge}</td>";
                                            echo "<td class='text-secondary small'><i class='bi bi-calendar-event me-1'></i>{$date}</td>";
                                            echo "<td class='text-center'>";
                                            echo "<div class='btn-group shadow-sm' role='group'>";
                                            // Eye Icon (Details)
                                            echo "<a href='request_detail.php?id={$row['id']}' class='btn btn-light btn-sm text-primary border' data-bs-toggle='tooltip' title='ดูรายละเอียด'>";
                                            echo "<i class='bi bi-eye-fill'></i>";
                                            echo "</a>";

                                            // Receipt & Permission Buttons
                                            if ($row['status'] == 'approved') {
                                                echo "<a href='view_receipt.php?id={$row['id']}' target='_blank' class='btn btn-light btn-sm text-success border' data-bs-toggle='tooltip' title='ใบเสร็จ'>";
                                                echo "<i class='bi bi-receipt'></i>";
                                                echo "</a>";
                                                echo "<a href='view_permission.php?id={$row['id']}' target='_blank' class='btn btn-light btn-sm text-info border' data-bs-toggle='tooltip' title='ใบอนุญาต'>";
                                                echo "<i class='bi bi-file-earmark-check-fill'></i>";
                                                echo "</a>";
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
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $('#myRequestsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json",
                    "search": "ค้นหา:"
                },
                "order": [[0, "desc"]],
                // Custom DOM to place filter buttons
                "dom": '<"row mb-3 align-items-center"<"col-md-6"B><"col-md-6 text-md-end"f>>rt<"row mt-3"<"col-md-6"l><"col-md-6"p>>',
                initComplete: function () {
                    // Create Custom Status Filter
                    var filterHtml = `
                        <div class="d-flex align-items-center">
                            <label class="me-2 fw-bold text-muted"><i class="bi bi-funnel"></i> สถานะ:</label>
                            <select id="statusFilter" class="form-select form-select-sm w-auto shadow-sm border-primary">
                                <option value="">ทั่งหมด</option>
                                <option value="รอชำระเงิน">รอชำระเงิน</option>
                                <option value="รอตรวจสอบ">รอตรวจสอบ</option>
                                <option value="อนุมัติแล้ว">อนุมัติแล้ว</option>
                                <option value="ไม่อนุมัติ">ไม่อนุมัติ</option>
                            </select>
                        </div>`;
                    
                    // Inject into the first column of the header row (where 'B' would be, but we hijack it or prepend)
                    // Actually, let's use a custom container. 
                    // Since I used 'B' (Buttons) placeholder but didn't include buttons extension, it might be empty.
                    // Let's target the wrapper nicely.
                    $('.dataTables_wrapper .row:first-child .col-md-6:first-child').html(filterHtml);

                    // Add Event Listener
                    $('#statusFilter').on('change', function () {
                        var val = $.fn.dataTable.util.escapeRegex($(this).val());
                        // Column 4 is Status
                        table.column(4).search(val ? '^' + val + '$' : '', true, false).draw();
                    });
                }
            });

            // Initialize Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
</body>

</html>