<?php
session_start();
require '../includes/db.php';

// ตรวจสอบสิทธิ์ Admin
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
        $status = 'approved';
        $msg = 'อนุมัติคำขอเรียบร้อยแล้ว';
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $msg = 'ปฏิเสธคำขอเรียบร้อยแล้ว';
    } elseif ($action === 'wait_payment') {
        $status = 'waiting_payment';
        $msg = 'ส่งแจ้งเตือนให้ชำระเงินเรียบร้อยแล้ว';
    }

    if ($status) {
        $stmt_update = $conn->prepare("UPDATE sign_requests SET status = ? WHERE id = ?");
        $stmt_update->bind_param("si", $status, $request_id);
        if ($stmt_update->execute()) {
            $success = $msg;
        } else {
            $error = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    }
}

// ดึงข้อมูลคำขอทั้งหมด
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name 
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

function get_status_badge($status)
{
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">⏳ รอพิจารณา</span>';
        case 'reviewing':
            return '<span class="badge bg-primary">🔎 กำลังพิจารณา</span>';
        case 'need_documents':
            return '<span class="badge bg-info">📑 ขอเอกสารเพิ่ม</span>';
        case 'waiting_payment':
            return '<span class="badge bg-danger">💰 รอชำระเงิน</span>';
        case 'waiting_receipt':
            return '<span class="badge bg-info">📄 รอออกใบเสร็จ</span>';
        case 'approved':
            return '<span class="badge bg-success">✅ อนุมัติ</span>';
        case 'rejected':
            return '<span class="badge bg-secondary">❌ ไม่ผ่าน</span>';
        default:
            return '<span class="badge bg-light text-dark">' . $status . '</span>';
    }
}
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
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <h2 class="mb-4">📝 รายการคำขออนุญาตติดตั้งป้าย</h2>

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
                            <th>ID</th>
                            <th>ผู้ยื่นคำขอ</th>
                            <th>ประเภทป้าย</th>
                            <th>วันที่ยื่น</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($row['title_name'] . $row['first_name'] . ' ' . $row['last_name']) ?>
                                    </div>
                                    <!-- <small class="text-muted">ID: <?= $row['user_id'] ?></small> -->
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['sign_type']) ?>
                                    <div class="small text-muted"><?= $row['width'] ?>x<?= $row['height'] ?> ม.</div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td><?= get_status_badge($row['status']) ?></td>
                                <td>
                                    <div class="d-flex gap-1 align-items-center flex-nowrap"
                                        style="min-width: fit-content; white-space: nowrap;">
                                        <a href="request_detail.php?id=<?= $row['id'] ?>"
                                            class="btn btn-sm btn-outline-primary action-btn" title="ดูรายละเอียด">
                                            <i class="bi bi-search"></i> รายละเอียด
                                        </a>

                                        <?php if ($row['status'] == 'pending'): ?>
                                            <!-- Approve Button -->
                                            <a href="approve_form.php?id=<?= $row['id'] ?>"
                                                class="btn btn-sm btn-success action-btn" title="อนุมัติ">
                                                <i class="bi bi-check-circle"></i> อนุมัติ
                                            </a>
                                            <!-- Reject Button -->
                                            <form method="post" onsubmit="return confirmReject(event, this);"
                                                class="m-0 d-inline-flex">
                                                <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button class="btn btn-sm btn-danger action-btn" type="submit" title="ปฏิเสธ">
                                                    <i class="bi bi-x-circle"></i> ปฏิเสธ
                                                </button>
                                            </form>

                                        <?php elseif ($row['status'] == 'waiting_payment'): ?>
                                            <button class="btn btn-sm btn-outline-secondary action-btn" disabled
                                                title="รอผู้ใช้ชำระเงิน">
                                                <i class="bi bi-hourglass-split"></i> รอชำระเงิน
                                            </button>

                                        <?php elseif ($row['status'] == 'waiting_receipt'): ?>
                                            <a href="issue_receipt.php?id=<?= $row['id'] ?>"
                                                class="btn btn-sm btn-warning text-dark action-btn" title="ออกใบเสร็จรับเงิน">
                                                <i class="bi bi-receipt"></i> ออกใบเสร็จ
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
        function confirmReject(event, form) {
            event.preventDefault(); // Stop default submission
            Swal.fire({
                title: 'ยืนยันการปฏิเสธ?',
                text: "คุณแน่ใจหรือไม่ที่จะปฏิเสธคำขอนี้? เมื่อปฏิเสธแล้วจะไม่สามารถแก้ไขได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน, ปฏิเสธ!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Submit the form
                }
            });
        }

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
                        // ลบ dropdown instance เก่าถ้ามี
                        var existingDropdown = bootstrap.Dropdown.getInstance(dropdownToggleEl);
                        if (existingDropdown) {
                            existingDropdown.dispose();
                        }
                        // สร้าง dropdown ใหม่
                        new bootstrap.Dropdown(dropdownToggleEl);
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
    </script>
</body>

</html>⚓,Complexity:2,Description: