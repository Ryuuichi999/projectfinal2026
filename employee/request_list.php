<?php
require '../includes/db.php';
require '../includes/email_helper.php';
require_once '../includes/status_helper.php';
require_once '../includes/log_helper.php';

// ตรวจสอบสิทธิ์ Admin หรือ Employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// ตรวจสอบการอัปเดตสถานะ (Quick Action)
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $status = '';

    if ($action === 'approve') {
        $status = 'waiting_payment';
        $msg = 'อนุมัติคำขอเรียบร้อยแล้ว สถานะเปลี่ยนเป็นรอชำระเงิน';
        $approved_by = $_SESSION['user_id'];
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $msg = 'ปฏิเสธคำขอเรียบร้อยแล้ว';
    } elseif ($action === 'wait_payment') {
        $status = 'waiting_payment';
        $msg = 'ส่งแจ้งเตือนให้ชำระเงินเรียบร้อยแล้ว';
    }

    if ($status) {
        if ($action === 'approve') {
            $stmt_update = $conn->prepare("UPDATE sign_requests SET status = ?, approved_by = ? WHERE id = ?");
            $stmt_update->bind_param("sii", $status, $approved_by, $request_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE sign_requests SET status = ? WHERE id = ?");
            $stmt_update->bind_param("si", $status, $request_id);
        }
        if ($stmt_update->execute()) {
            send_status_notification($request_id, $conn);
            if ($action === 'approve') {
                logRequestAction($conn, $request_id, 'waiting_payment', 'อนุมัติคำร้อง — รอชำระค่าธรรมเนียม', $_SESSION['user_id'], 'ตรวจสอบเอกสารเบื้องต้นผ่านแล้ว');
            } else {
                logRequestAction($conn, $request_id, $status, $msg, $_SESSION['user_id']);
            }

            require_once '../includes/audit_helper.php';
            logAudit($conn, $action, 'sign_requests', $request_id, $msg);

            $success = $msg;
        } else {
            $error = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
        }
    }
}

// ดึงข้อมูลคำขอทั้งหมด
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name,
        DATE_ADD(COALESCE(r.permit_date, r.created_at), INTERVAL r.duration_days DAY) as expire_date
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.id ASC";
$result = $conn->query($sql);

// สร้าง array สำหรับ autocomplete (เก็บข้อมูลทั้งหมด)
$autocomplete_data = [];
while ($row_auto = $result->fetch_assoc()) {
    $search_text = $row_auto['id'] . ' ' .
        $row_auto['title_name'] . $row_auto['first_name'] . ' ' . $row_auto['last_name'] . ' ' .
        $row_auto['sign_type'] . ' ' .
        date('d/m/Y H:i', strtotime($row_auto['created_at']));
    $autocomplete_data[] = trim($search_text);
}
// Reset result pointer
$result->data_seek(0);


?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการคำขอ | Admin</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- DataTables for better table management -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- jQuery UI for Autocomplete -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
    <style>
        .action-btn {
            font-size: 11px !important;
            padding: 4px 8px !important;
            border-radius: 4px;
            white-space: nowrap;
        }

        .badge-sm-expiry {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.2 !important;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">📝 รายการคำขออนุญาตติดตั้งป้าย</h2>
            <a href="export_csv.php" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
        </div>

        <?php if (isset($success)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: '<?= $success ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    Swal.fire({
                        icon: 'error',
                        title: 'ผิดพลาด',
                        text: '<?= $error ?>',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'ตกลง'
                    });
                });
            </script>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="table-responsive">
                <table id="requestsTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>เลขที่คำร้อง</th>
                            <th>ผู้ยื่นคำขอ</th>
                            <th>ประเภทป้าย</th>
                            <th>วันที่ยื่น</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()):
                            // Expiry logic
                            $emp_expire = $row['expire_date'];
                            $emp_days_left = $emp_expire ? (int)((strtotime($emp_expire) - time()) / 86400) : null;
                            $emp_expiry_html = '';
                            if ($row['status'] == 'approved' && $emp_days_left !== null) {
                                if ($emp_days_left < 0) {
                                    $emp_expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-danger bg-opacity-90 px-2'><i class='bi bi-x-circle-fill me-1'></i>หมดอายุแล้ว</span></div>";
                                } elseif ($emp_days_left <= 30) {
                                    $emp_expiry_html = "<div class='mt-1'><span class='badge badge-sm-expiry bg-warning text-dark px-2'><i class='bi bi-clock-fill me-1'></i>เหลือ {$emp_days_left} วัน</span></div>";
                                }
                            }
                        ?>
                            <tr>
                                <td class="req-no-cell">
                                    <div class="req-no-main"><?= htmlspecialchars($row['request_no'] ?: '#' . $row['id']) ?></div>
                                    <div class="req-no-sub">#<?= $row['id'] ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($row['title_name'] . $row['first_name'] . ' ' . $row['last_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['sign_type']) ?>
                                    <div class="small text-muted"><?= $row['width'] ?>x<?= $row['height'] ?> ม.</div>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                <td><?= get_status_badge($row['status']) ?><?= $emp_expiry_html ?></td>
                                <td>
                                    <div class="d-flex gap-1 align-items-center flex-nowrap">
                                        <a href="request_detail.php?id=<?= $row['id'] ?>"
                                            class="btn btn-sm btn-outline-primary px-2" title="ดูรายละเอียด"
                                            data-bs-toggle="tooltip">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>

                                        <?php if ($row['status'] == 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success px-2" title="อนุมัติ"
                                                data-bs-toggle="tooltip"
                                                onclick="confirmApprove(<?= $row['id'] ?>)">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger px-2" title="ปฏิเสธ"
                                                data-bs-toggle="tooltip"
                                                onclick="confirmReject(<?= $row['id'] ?>)">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                            <form id="approveForm<?= $row['id'] ?>" method="post" class="d-none">
                                                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                            </form>
                                            <form id="rejectForm<?= $row['id'] ?>" method="post" class="d-none">
                                                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                            </form>

                                        <?php elseif ($row['status'] == 'waiting_payment'): ?>
                                            <span class="badge bg-secondary px-2 py-2">
                                                <i class="bi bi-hourglass-split"></i> รอชำระ
                                            </span>

                                        <?php elseif ($row['status'] == 'waiting_receipt'): ?>
                                            <a href="issue_receipt.php?id=<?= $row['id'] ?>"
                                                class="btn btn-sm btn-warning text-dark px-2" title="ออกใบเสร็จ"
                                                data-bs-toggle="tooltip">
                                                <i class="bi bi-receipt"></i>
                                            </a>

                                        <?php elseif ($row['status'] == 'waiting_permit'): ?>
                                            <a href="issue_receipt.php?id=<?= $row['id'] ?>"
                                                class="btn btn-sm btn-primary px-2" title="ออกใบอนุญาต"
                                                data-bs-toggle="tooltip">
                                                <i class="bi bi-file-earmark-check-fill"></i>
                                            </a>

                                        <?php elseif ($row['status'] == 'approved'): ?>
                                            <a href="../users/view_permission.php?id=<?= $row['id'] ?>" target="_blank"
                                                class="btn btn-sm btn-outline-success px-2" title="ใบอนุญาต"
                                                data-bs-toggle="tooltip">
                                                <i class="bi bi-file-earmark-check-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <!-- jQuery and Bootstrap JS Bundle (ต้องโหลดก่อน DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- jQuery UI for Autocomplete -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function () {
            var table = $('#requestsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
                },
                "order": [], // ปิด default sort ให้ใช้ตาม SQL
                "dom": "<'row'<'col-sm-12'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row align-items-center'<'col-md-6'l><'col-md-6 d-flex justify-content-end'p>>",
                "pageLength": 10,
                "drawCallback": function (settings) {
                    // Initialize Bootstrap dropdowns หลังจาก DataTables draw
                    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                    dropdownElementList.forEach(function (dropdownToggleEl) {
                        var existingDropdown = bootstrap.Dropdown.getInstance(dropdownToggleEl);
                        if (existingDropdown) { existingDropdown.dispose(); }
                        new bootstrap.Dropdown(dropdownToggleEl);
                    });

                    // Re-init tooltips after each draw
                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
                        var existing = bootstrap.Tooltip.getInstance(el);
                        if (existing) existing.dispose();
                        new bootstrap.Tooltip(el);
                    });

                    // Re-initialize autocomplete หลังจาก draw
                    initAutocomplete();
                }
            });

            // ข้อมูล autocomplete จาก PHP
            var autocompleteData = <?= json_encode($autocomplete_data, JSON_UNESCAPED_UNICODE) ?>;

            // ฟังก์ชันสำหรับ initialize autocomplete
            function initAutocomplete() {
                // หา search input จาก DataTables
                var searchInput = $('input[type="search"]', table.table().container());

                // ถ้ายังไม่มี autocomplete ให้สร้างใหม่
                if (searchInput.length > 0 && !searchInput.hasClass('ui-autocomplete-input')) {
                    // ลบ autocomplete เก่าถ้ามี
                    searchInput.autocomplete('destroy');

                    // สร้าง autocomplete ใหม่
                    searchInput.autocomplete({
                        source: function (request, response) {
                            var term = request.term.toLowerCase();
                            var matches = [];

                            $.each(autocompleteData, function (index, item) {
                                if (item.toLowerCase().indexOf(term) !== -1) {
                                    matches.push({
                                        label: item,
                                        value: item
                                    });
                                }
                            });

                            response(matches.slice(0, 10)); // แสดงสูงสุด 10 รายการ
                        },
                        minLength: 1,
                        select: function (event, ui) {
                            event.preventDefault();
                            // เมื่อเลือกจาก autocomplete ให้ค้นหาในตาราง
                            table.search(ui.item.value).draw();
                        },
                        focus: function (event, ui) {
                            event.preventDefault();
                        }
                    });
                }
            }

            // Initialize autocomplete ครั้งแรกหลังจาก DataTables สร้าง DOM
            setTimeout(function () {
                initAutocomplete();
            }, 100);

            // Initialize Bootstrap dropdowns ครั้งแรก
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            dropdownElementList.forEach(function (dropdownToggleEl) {
                new bootstrap.Dropdown(dropdownToggleEl);
            });
        });

        // SweetAlert ยืนยันก่อนอนุมัติ
        function confirmApprove(requestId) {
            Swal.fire({
                title: 'ยืนยันการอนุมัติ?',
                text: "สถานะจะเปลี่ยนเป็น 'รอชำระเงิน'",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'ยืนยัน อนุมัติ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('approveForm' + requestId).submit();
                }
            });
        }

        // SweetAlert ยืนยันก่อนปฏิเสธ
        function confirmReject(requestId) {
            Swal.fire({
                title: 'ยืนยันการปฏิเสธ?',
                text: 'คุณต้องการปฏิเสธคำขอ #' + requestId + ' หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-x-circle"></i> ปฏิเสธ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('rejectForm' + requestId).submit();
                }
            });
        }
    </script>
</body>

</html>