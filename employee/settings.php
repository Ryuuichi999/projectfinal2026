<?php
require '../includes/db.php';
require '../includes/settings_helper.php';

// Check Admin or Employee
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

// Ensure table exists
ensureSettingsTable($conn);

$success = '';
$error = '';

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $signer_name = trim($_POST['receipt_signer_name']);
    $signer_position = trim($_POST['receipt_signer_position']);

    // Save Text Settings
    if (
        updateSetting($conn, 'receipt_signer_name', $signer_name) &&
        updateSetting($conn, 'receipt_signer_position', $signer_position)
    ) {
        $success = "บันทึกข้อมูลเรียบร้อยแล้ว";
    } else {
        $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
    }

    // Handle File Upload
    if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['signature_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/signatures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'sig_' . time() . '.' . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $dest_path)) {
                // Save path relative to root/admin so view_receipt can find it
                // view_receipt is in users/ so path should be ../uploads/...
                // stored value: uploads/signatures/filename
                $db_val = 'uploads/signatures/' . $new_filename;
                updateSetting($conn, 'receipt_signature_path', $db_val);
                $success .= " และอัปโหลดลายเซ็นสำเร็จ";
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            }
        } else {
            $error = "อนุญาตเฉพาะไฟล์รูปภาพ (JPG, PNG)";
        }
    }
}

// Get Current Settings
$curr_name = getSetting($conn, 'receipt_signer_name', 'ระบบอัตโนมัติ');
$curr_pos = getSetting($conn, 'receipt_signer_position', 'เจ้าพนักงานธุรการ');
$curr_sig = getSetting($conn, 'receipt_signature_path', 'image/ลายเซ็น2.png'); // Default asset
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าระบบใบเสร็จ</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="container py-4">
            <h2 class="mb-4">⚙️ ตั้งค่าระบบใบเสร็จรับเงิน</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">ข้อมูลผู้ออกใบเสร็จ (สำหรับระบบอัตโนมัติ)</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">ชื่อผู้รับเงิน / ผู้ออกใบเสร็จ</label>
                            <input type="text" name="receipt_signer_name" class="form-control"
                                value="<?= htmlspecialchars($curr_name) ?>" required>
                            <div class="form-text">ชื่อที่จะปรากฏในช่อง (ลงชื่อ) ... (ชื่อนี้)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ตำแหน่ง</label>
                            <input type="text" name="receipt_signer_position" class="form-control"
                                value="<?= htmlspecialchars($curr_pos) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">รูปภาพลายเซ็น</label>
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="border p-2 text-center bg-light rounded">
                                        <?php if ($curr_sig && file_exists("../" . $curr_sig)): ?>
                                            <img src="../<?= $curr_sig ?>" alt="Signature" class="img-fluid"
                                                style="max-height: 80px;">
                                        <?php elseif (file_exists("../image/ลายเซ็น2.png")): // fallback ?>
                                            <img src="../image/ลายเซ็น2.png" alt="Signature" class="img-fluid"
                                                style="max-height: 80px;">
                                        <?php else: ?>
                                            <span class="text-muted">ไม่มีลายเซ็น</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted text-center mt-1">ลายเซ็นปัจจุบัน</div>
                                </div>
                                <div class="col-md-8">
                                    <input type="file" name="signature_file" class="form-control" accept="image/*">
                                    <div class="form-text">อัปโหลดไฟล์ใหม่เพื่อเปลี่ยน (JPG, PNG) แนะนำพื้นหลังโปร่งใส
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> บันทึกการตั้งค่า
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>