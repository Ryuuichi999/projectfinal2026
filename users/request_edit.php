<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: my_request.php");
    exit;
}

$request_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM sign_requests WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "ไม่พบคำขอของคุณ";
    exit;
}
$request = $result->fetch_assoc();

if ($request['status'] !== 'need_documents') {
    header("Location: request_detail.php?id=" . $request_id);
    exit;
}

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    $conn->begin_transaction();
    try {
        $uploaded_files = [
            'file_sign_plan' => 'แบบป้าย/รูปภาพโฆษณา',
            'file_id_card' => 'สำเนาบัตรประชาชน',
            'file_land_doc' => 'หนังสือยินยอมเจ้าของที่/สัญญาเช่า',
            'file_other' => 'เอกสารอื่นๆ'
        ];

        $real_upload_dir = "./uploads/{$request_id}/";
        if (!file_exists($real_upload_dir)) {
            mkdir($real_upload_dir, 0777, true);
        }

        foreach ($uploaded_files as $input_name => $doc_type_name) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == UPLOAD_ERR_OK) {
                $file_name = time() . '_' . basename($_FILES[$input_name]['name']);
                $target_path = $real_upload_dir . $file_name;
                $db_path = "/uploads/{$request_id}/" . $file_name;
                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_path)) {
                    $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path) VALUES (?, ?, ?)";
                    $stmt_doc = $conn->prepare($sql_doc);
                    $stmt_doc->bind_param("iss", $request_id, $doc_type_name, $db_path);
                    $stmt_doc->execute();
                }
            }
        }

        if (!empty($_POST['description'])) {
            $new_desc = trim($_POST['description']);
            $stmt_up = $conn->prepare("UPDATE sign_requests SET description = ? WHERE id = ?");
            $stmt_up->bind_param("si", $new_desc, $request_id);
            $stmt_up->execute();
        }

        $stmt_status = $conn->prepare("UPDATE sign_requests SET status = 'reviewing' WHERE id = ?");
        $stmt_status->bind_param("i", $request_id);
        $stmt_status->execute();

        $conn->commit();

        echo '<!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "success",
                        title: "ส่งเอกสารเพิ่มเติมเรียบร้อย",
                        text: "เจ้าหน้าที่จะตรวจสอบข้อมูลที่คุณส่งมา",
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = "request_detail.php?id=' . $request_id . '";
                    });
                });
            </script>
        </body>
        </html>';
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยื่นเอกสารเพิ่มเติม #<?= $request['id'] ?></title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <div class="card p-4">
            <h3 class="mb-3">ยื่นเอกสารเพิ่มเติมสำหรับคำขอ #<?= $request['id'] ?></h3>
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">แบบป้าย/รูปภาพโฆษณา</label>
                        <input type="file" name="file_sign_plan" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">สำเนาบัตรประชาชน</label>
                        <input type="file" name="file_id_card" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">หนังสือยินยอมเจ้าของที่/สัญญาเช่า</label>
                        <input type="file" name="file_land_doc" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เอกสารอื่นๆ</label>
                        <input type="file" name="file_other" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">ปรับปรุงรายละเอียด (ถ้าต้องการ)</label>
                        <textarea name="description" class="form-control" rows="3"
                            placeholder="ระบุรายละเอียดเพิ่มเติม"></textarea>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" name="submit" class="btn btn-action-confirm"><i class="bi bi-upload"></i>
                        ส่งเอกสาร</button>
                    <a href="request_detail.php?id=<?= $request['id'] ?>" class="btn btn-action-cancel">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>