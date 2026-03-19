<?php
require '../includes/db.php';
require '../includes/email_helper.php';
require_once '../includes/status_helper.php';
require_once '../includes/log_helper.php';
require_once '../includes/csrf_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

/* ===============================
   จัดการ Quick Action
================================ */
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    csrf_check();

    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $status = '';
    $msg = '';

    // ตรวจสอบสถานะปัจจุบันก่อนดำเนินการ
    $stmt_chk = $conn->prepare("SELECT status FROM sign_requests WHERE id = ?");
    $stmt_chk->bind_param("i", $request_id);
    $stmt_chk->execute();
    $current = $stmt_chk->get_result()->fetch_assoc();
    $allowed_statuses = ['pending', 'reviewing', 'need_documents'];

    if (!$current || !in_array($current['status'], $allowed_statuses)) {
        $error = "ไม่สามารถดำเนินการได้ สถานะปัจจุบันไม่อนุญาต";
    } else {
        if ($action === 'start_review') {
            if ($current['status'] === 'pending') {
                $status = 'reviewing';
                $msg = 'เริ่มตรวจสอบคำขอแล้ว';
            } else {
                $error = 'คำขอนี้ไม่อยู่ในสถานะรอพิจารณา';
            }
        } elseif ($action === 'approve') {
            $status = 'waiting_payment';
            $approved_by = $_SESSION['user_id'];
            $msg = 'อนุมัติคำขอเรียบร้อยแล้ว สถานะเปลี่ยนเป็นรอชำระเงิน';
        } elseif ($action === 'reject') {
            $status = 'rejected';
            $msg = 'ปฏิเสธคำขอเรียบร้อยแล้ว';
        }

        if (!empty($status)) {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE sign_requests SET status=?, approved_by=? WHERE id=?");
                $stmt->bind_param("sii", $status, $approved_by, $request_id);
            } else {
                $stmt = $conn->prepare("UPDATE sign_requests SET status=? WHERE id=?");
                $stmt->bind_param("si", $status, $request_id);
            }

            if ($stmt->execute()) {
                queue_status_notification($request_id, $conn);
                logRequestAction($conn, $request_id, $status, $msg, $_SESSION['user_id']);
                $success = $msg;
            } else {
                $error = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
            }
        }
    }
}

$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name
        FROM sign_requests r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการคำขอ</title>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<style>
.badge-sm { font-size: .75rem; }
</style>
</head>

<body>

<?php include '../includes/sidebar.php'; ?>
<?php include '../includes/topbar.php'; ?>

<div class="content fade-in-up">
<h3 class="mb-4">📝 รายการคำขออนุญาตติดตั้งป้าย</h3>

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

<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['request_no'] ?: '#'.$row['id']) ?></td>
<td><?= htmlspecialchars($row['title_name'].$row['first_name'].' '.$row['last_name']) ?></td>
<td><?= htmlspecialchars($row['sign_type']) ?></td>
<td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>

<td data-status="<?= $row['status'] ?>">
<?= get_status_badge($row['status']); ?>
</td>

<td>
<a href="request_detail.php?id=<?= $row['id'] ?>" 
class="btn btn-sm btn-outline-primary" 
data-bs-toggle="tooltip" title="ดูรายละเอียด">
<i class="bi bi-eye-fill"></i>
</a>

<?php if ($row['status'] == 'pending'): ?>
<button class="btn btn-sm btn-info text-white" onclick="confirmStartReview(<?= $row['id'] ?>)" data-bs-toggle="tooltip" title="เริ่มตรวจสอบ">
<i class="bi bi-search"></i>
</button>

<form id="startReviewForm<?= $row['id'] ?>" method="post" class="d-none">
<?= csrf_field() ?>
<input type="hidden" name="request_id" value="<?= $row['id'] ?>">
<input type="hidden" name="action" value="start_review">
</form>
<?php endif; ?>

<?php if (in_array($row['status'], ['reviewing', 'need_documents'])): ?>
<button class="btn btn-sm btn-success" onclick="confirmApprove(<?= $row['id'] ?>)" data-bs-toggle="tooltip" title="อนุมัติ">
<i class="bi bi-check-circle-fill"></i>
</button>

<button class="btn btn-sm btn-danger" onclick="confirmReject(<?= $row['id'] ?>)" data-bs-toggle="tooltip" title="ปฏิเสธ">
<i class="bi bi-x-circle-fill"></i>
</button>

<form id="approveForm<?= $row['id'] ?>" method="post" class="d-none">
<?= csrf_field() ?>
<input type="hidden" name="request_id" value="<?= $row['id'] ?>">
<input type="hidden" name="action" value="approve">
</form>

<form id="rejectForm<?= $row['id'] ?>" method="post" class="d-none">
<?= csrf_field() ?>
<input type="hidden" name="request_id" value="<?= $row['id'] ?>">
<input type="hidden" name="action" value="reject">
</form>
<?php endif; ?>

<?php if ($row['status'] == 'waiting_permit'): ?>
<a href="issue_receipt.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
<i class="bi bi-file-earmark-check-fill"></i>
</a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>
</div>
</div>

<?php include '../includes/scripts.php'; ?>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<?php if (!empty($_SESSION['flash_success'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Toast.fire({ icon: 'success', title: <?= json_encode($_SESSION['flash_success']) ?> });
    });
</script>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Toast.fire({ icon: 'error', title: <?= json_encode($_SESSION['flash_error']) ?> });
    });
</script>
<?php unset($_SESSION['flash_error']); endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('.topbar [data-bs-toggle="dropdown"]').forEach(function (el) {
bootstrap.Dropdown.getOrCreateInstance(el);
});
});

$(document).ready(function () {

var table = $('#requestsTable').DataTable({

language: {
search: "ค้นหา:",
lengthMenu: "แสดง _MENU_ รายการ",
info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
paginate: {
first: "<<",
last: ">>",
next: ">>",
previous: "<<"
},
zeroRecords: "ไม่พบข้อมูล"
},

order: [],

dom:
"<'row mb-3 align-items-center'<'col-md-6'<'statusFilter'>><'col-md-6 text-end'f>>" +
"<'row'<'col-12'tr>>" +
"<'row mt-3 align-items-center'<'col-md-6'l><'col-md-6 text-end'p>>",

pageLength: 10
});

/* ===== Filter สถานะ ===== */

$("div.statusFilter").html(`
<div class="d-flex align-items-center">
<label class="me-2 fw-bold text-muted"><i class="bi bi-funnel"></i> สถานะ:</label>
<select id="statusFilterSelect" class="form-select form-select-sm w-auto shadow-sm border-primary">
<option value="">ทั้งหมด</option>
<option value="pending">รอกำลังพิจารณา</option>
<option value="reviewing">กำลังพิจารณา</option>
<option value="need_documents">ขอเอกสารเพิ่มเติม</option>
<option value="waiting_payment">รอชำระเงิน</option>
<option value="waiting_permit">รอออกใบอนุญาต</option>
<option value="waiting_receipt">รอออกใบเสร็จ</option>
<option value="approved">อนุมัติแล้ว</option>
<option value="rejected">ไม่อนุมัติ</option>
<option value="expired">หมดอายุ</option>
<option value="cancelled_payment">ยกเลิก</option>
</select>
</div>
`);

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

var selected = $('#statusFilterSelect').val();
if (!selected) return true;

var row = table.row(dataIndex).node();
var status = $(row).find('td[data-status]').data('status');

return status === selected;
});

$('#statusFilterSelect').on('change', function(){
table.draw();
});

});
</script>

<script>
function confirmStartReview(id){
Swal.fire({
title: 'เริ่มตรวจสอบคำขอ?',
text: 'สถานะจะเปลี่ยนเป็น กำลังพิจารณา',
icon: 'info',
showCancelButton: true,
confirmButtonColor: '#0dcaf0',
cancelButtonColor: '#6b7280',
confirmButtonText: 'เริ่มตรวจสอบ',
cancelButtonText: 'ยกเลิก'
}).then((result) => {
if (result.isConfirmed) {
document.getElementById('startReviewForm'+id).submit();
}
});
}

function confirmApprove(id){
Swal.fire({
title: 'ยืนยันการอนุมัติ?',
text: "สถานะจะเปลี่ยนเป็น 'รอชำระเงิน'",
icon: 'question',
showCancelButton: true,
confirmButtonColor: '#16a34a',
cancelButtonColor: '#6b7280',
confirmButtonText: 'ยืนยัน',
cancelButtonText: 'ยกเลิก'
}).then((result) => {
if (result.isConfirmed) {
document.getElementById('approveForm'+id).submit();
}
});
}

function confirmReject(id){
Swal.fire({
title: 'ยืนยันการปฏิเสธ?',
text: 'คุณต้องการปฏิเสธคำขอนี้หรือไม่?',
icon: 'warning',
showCancelButton: true,
confirmButtonColor: '#dc2626',
cancelButtonColor: '#6b7280',
confirmButtonText: 'ปฏิเสธ',
cancelButtonText: 'ยกเลิก'
}).then((result) => {
if (result.isConfirmed) {
document.getElementById('rejectForm'+id).submit();
}
});
}
</script>

<?php if (isset($success)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const T = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:2000, timerProgressBar:true});
    T.fire({ icon: 'success', title: <?= json_encode($success) ?> });
});
</script>
<?php endif; ?>

<?php if (isset($error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const T = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true});
    T.fire({ icon: 'error', title: <?= json_encode($error) ?> });
});
</script>
<?php endif; ?>

</body>
</html>