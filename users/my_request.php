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
        .table th {
            white-space: nowrap;
            vertical-align: middle;
            font-weight: 600;
            font-size: 0.88rem;
            color: #475569;
            letter-spacing: 0.01em;
        }
        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
            font-weight: 400;
            color: #1e293b;
        }
        .req-no-main {
            font-weight: 600;
            color: #1e40af;
        }
        .req-no-sub {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .badge-sm-expiry {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.2 !important;
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
                                        <th width="18%">เลขที่คำร้อง</th>
                                        <th width="15%">ประเภทป้าย</th>
                                        <th width="12%">ขนาด (ม.)</th>
                                        <th class="text-center" width="10%">ค่าธรรมเนียม</th>
                                        <th class="text-center" width="15%">สถานะ</th>
                                        <th width="12%">วันที่ยื่น</th>
                                        <th class="text-center" width="18%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT *, end_date as expire_date FROM sign_requests WHERE user_id=? ORDER BY id DESC";
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
                                            $req_no = !empty($row['request_no']) ? $row['request_no'] : '#' . $row['id'];

                                            // Expiry logic
                                            $expire_date = $row['expire_date'];
                                            $days_left = $expire_date ? (int)((strtotime($expire_date) - time()) / 86400) : null;
                                            $expiry_html = '';
                                            if ($row['status'] == 'approved' && $days_left !== null) {
                                                if ($days_left < 0) {
                                                    $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-danger bg-opacity-90 px-2'><i class='bi bi-x-circle-fill me-1'></i>หมดอายุแล้ว</span></div>";
                                                } elseif ($days_left <= 30) {
                                                    $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-warning text-dark px-2'><i class='bi bi-clock-fill me-1'></i>เหลือ {$days_left} วัน</span></div>";
                                                }
                                            }
                                            if ($row['status'] == 'expired' && $days_left !== null) {
                                                $days_since = abs($days_left);
                                                $collect_remain = max(0, 7 - $days_since);
                                                if ($collect_remain > 0) {
                                                    $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-danger px-2'><i class='bi bi-exclamation-triangle-fill me-1'></i>เก็บป้ายภายใน {$collect_remain} วัน</span></div>";
                                                } else {
                                                    $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-dark px-2'><i class='bi bi-exclamation-triangle-fill me-1'></i>เกินกำหนดเก็บป้าย</span></div>";
                                                }
                                            }
                                            if ($row['status'] == 'waiting_payment') {
                                                $wp_stmt = $conn->prepare("SELECT created_at FROM request_logs WHERE request_id = ? AND action = 'waiting_payment' ORDER BY created_at DESC LIMIT 1");
                                                $wp_stmt->bind_param("i", $row['id']);
                                                $wp_stmt->execute();
                                                $wp_row = $wp_stmt->get_result()->fetch_assoc();
                                                if ($wp_row) {
                                                    $deadline_ts = strtotime($wp_row['created_at'] . ' +24 hours');
                                                    $hours_left = max(0, round(($deadline_ts - time()) / 3600));
                                                    $deadline_str = date('H:i น.', $deadline_ts);
                                                    if ($hours_left > 0) {
                                                        $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-warning text-dark px-2'><i class='bi bi-clock-fill me-1'></i>เหลือ {$hours_left} ชม.</span></div>";
                                                    } else {
                                                        $expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-danger px-2'><i class='bi bi-exclamation-triangle-fill me-1'></i>เกินกำหนดชำระ</span></div>";
                                                    }
                                                }
                                                $wp_stmt->close();
                                            }

                                            echo "<tr>";
                                            echo "<td class='req-no-cell'><div class='req-no-main'>" . htmlspecialchars($req_no) . "</div><div class='req-no-sub'>#{$row['id']}</div></td>";
                                            echo "<td>" . htmlspecialchars($row['sign_type']) . "</td>";
                                            echo "<td>{$size}</td>";
                                            echo "<td class='text-center'>{$fee}</td>";
                                            echo "<td class='text-center'>{$badge}{$expiry_html}</td>";
                                            echo "<td class='small'><i class='bi bi-calendar-event me-1'></i>{$date}</td>";
                                            echo "<td class='text-center'>";
                                            echo "<div class='d-flex gap-1 justify-content-center flex-nowrap align-items-center'>";
                                            // ปุ่มดูรายละเอียด (เสมอ)
                                            echo "<a href='request_detail.php?id={$row['id']}' class='btn btn-outline-primary btn-sm px-2' data-bs-toggle='tooltip' title='ดูรายละเอียด'><i class='bi bi-eye-fill'></i></a>";
                                            if ($row['status'] == 'approved' || $row['status'] == 'expired') {
                                                // Dropdown รวมเอกสาร 3 ปุ่ม
                                                echo "<div class='dropdown'>
                                                    <button class='btn btn-outline-secondary btn-sm px-2 dropdown-toggle' type='button' data-bs-toggle='dropdown' aria-expanded='false' title='เอกสาร'>
                                                        <i class='bi bi-file-earmark-text'></i>
                                                    </button>
                                                    <ul class='dropdown-menu dropdown-menu-end shadow-sm'>
                                                        <li><a class='dropdown-item' href='view_receipt.php?id={$row['id']}' target='_blank'><i class='bi bi-receipt text-success me-2'></i>ใบเสร็จรับเงิน</a></li>
                                                        <li><a class='dropdown-item' href='view_permission.php?id={$row['id']}' target='_blank'><i class='bi bi-file-earmark-check-fill text-info me-2'></i>ใบอนุญาต</a></li>
                                                        <li><a class='dropdown-item' href='view_sticker.php?id={$row['id']}' target='_blank'><i class='bi bi-patch-check-fill text-warning me-2'></i>สติกเกอร์</a></li>
                                                    </ul>
                                                </div>";
                                            } elseif ($row['status'] == 'waiting_permit' && !empty($row['receipt_no'])) {
                                                // มีใบเสร็จแล้ว แต่ยังไม่มีใบอนุญาต → แสดงปุ่มใบเสร็จอย่างเดียว
                                                echo "<a href='view_receipt.php?id={$row['id']}' target='_blank' class='btn btn-outline-success btn-sm px-2' data-bs-toggle='tooltip' title='ใบเสร็จรับเงิน'><i class='bi bi-receipt'></i></a>";
                                            }
                                            if ($row['status'] == 'waiting_payment') {
                                                echo "<a href='../payment.php?id={$row['id']}' class='btn btn-primary btn-sm px-2' data-bs-toggle='tooltip' title='ชำระเงิน'><i class='bi bi-qr-code'></i></a>";
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
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const Toast = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:2000, timerProgressBar:true});
                Toast.fire({ icon: 'success', title: <?= json_encode($_SESSION['flash_success']) ?> });
            });
        </script>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const Toast = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true});
                Toast.fire({ icon: 'error', title: <?= json_encode($_SESSION['flash_error']) ?> });
            });
        </script>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $('#myRequestsTable').DataTable({
                "language": {
                    "search": "ค้นหา:",
                    "lengthMenu": "แสดง _MENU_ รายการ",
                    "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
                    "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
                    "infoFiltered": "(กรองจาก _MAX_ รายการทั้งหมด)",
                    "zeroRecords": "ไม่พบข้อมูลที่ค้นหา",
                    "emptyTable": "ไม่มีข้อมูลในตาราง",
                    "paginate": {
                        "first": "<<",
                        "last": ">>",
                        "next": ">>",
                        "previous": "<<"
                    }
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
                                <option value="">ทั้งหมด</option>
                                <option value="รอกำลังพิจารณา">รอกำลังพิจารณา</option>
                                <option value="กำลังพิจารณา">กำลังพิจารณา</option>
                                <option value="ขอเอกสารเพิ่ม">ขอเอกสารเพิ่ม</option>
                                <option value="รอชำระเงิน">รอชำระเงิน</option>
                                <option value="รอออกใบอนุญาต">รอออกใบอนุญาต</option>
                                <option value="อนุมัติแล้ว">อนุมัติแล้ว</option>
                                <option value="ไม่อนุมัติ">ไม่อนุมัติ</option>
                                <option value="หมดอายุ">หมดอายุ</option>
                                <option value="ยกเลิก">ยกเลิก</option>
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
                        // Column 4 is Status (contains search for badge text)
                        table.column(4).search(val ? val : '', true, false).draw();
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