<?php
require '../includes/db.php';
require_once '../includes/log_helper.php';
require_once '../includes/receipt_helper.php';
require_once '../includes/csrf_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit;
}

// ดึงข้อมูลผู้ใช้เพื่อ Pre-fill 
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$me = $stmt_user->get_result()->fetch_assoc();

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    csrf_check();
    // 1. รับค่า
    $applicant_name = trim($_POST['applicant_name']);
    $applicant_address = trim($_POST['applicant_address']);
    $sign_type = trim($_POST['sign_type']);
    $width = (float) $_POST['width'];
    $height = (float) $_POST['height'];
    $quantity = (int) $_POST['quantity'];
    $road_name = trim($_POST['road_name']);
    $description = trim($_POST['description']);
    $email = trim($_POST['email']);

    // ตรวจสอบประเภทป้าย
    $allowed_types = ['ไวนิล', 'ผ้าใบ', 'ไม้อัด'];
    if (!in_array($sign_type, $allowed_types)) {
        $message = "ประเภทป้ายไม่ถูกต้อง";
        $message_type = 'danger';
    }

    // ตรวจสอบขนาด: ต้อง > 0 และ กว้าง ≤1.20 ม., สูง ≤2.40 ม.
    if ($width <= 0 || $height <= 0) {
        $message = "ขนาดป้ายต้องมากกว่า 0";
        $message_type = 'danger';
    }
    if (empty($message) && $width > 1.20) {
        $message = "ความกว้างป้ายต้องไม่เกิน 1.20 เมตร (ท่านระบุ {$width} ม.)";
        $message_type = 'danger';
    }
    if (empty($message) && $height > 2.40) {
        $message = "ความสูงป้ายต้องไม่เกิน 2.40 เมตร (ท่านระบุ {$height} ม.)";
        $message_type = 'danger';
    }

    // ตรวจสอบจำนวน ≤2
    if ($quantity > 2) {
        $message = "จำนวนป้ายต้องไม่เกิน 2 ผืน/แผ่น ต่อ 1 คำร้อง";
        $message_type = 'danger';
    }

    // ระยะเวลา: คำนวณจากวันเริ่ม-วันสิ้นสุด (ไม่จำกัดจำนวนวัน เจ้าหน้าที่จะพิจารณาเอง)
    $install_date = $_POST['install_date'];
    $end_date = $_POST['end_date'];
    $d1 = new DateTime($install_date);
    $d2 = new DateTime($end_date);
    $today = new DateTime('today');
    $duration_days = (int) $d1->diff($d2)->days;
    if ($d1 < $today) {
        $message = "วันที่เริ่มติดตั้งต้องไม่เป็นวันย้อนหลัง";
        $message_type = 'danger';
    }
    if (empty($message) && $duration_days < 1) {
        $message = "วันสิ้นสุดต้องมากกว่าวันเริ่มติดตั้ง";
        $message_type = 'danger';
    }

    // พิกัด (Primary)
    $lat = empty($_POST['lat']) ? NULL : (float) $_POST['lat'];
    $lng = empty($_POST['lng']) ? NULL : (float) $_POST['lng'];

    if (is_null($lat) || is_null($lng)) {
        $message = "กรุณาปักหมุดตำแหน่งหลักบนแผนที่";
        $message_type = 'danger';
    }

    if (!empty($message)) {
        // หยุดไม่ให้ดำเนินการต่อถ้ามี error
    } else {
        // 2. คำนวณค่าธรรมเนียม (200 บาท/ป้าย ทุกประเภท)
        $fee = 200 * $quantity;

        // 3. Insert ลง DB
        $conn->begin_transaction();
        try {
            // ตรวจสอบคอลัมน์ใหม่ว่ามีหรือยัง (เผื่อ script update schema ยังไม่รัน)
            // แต่เราสมมติว่ารันแล้วตาม Plan
            $sql = "INSERT INTO sign_requests 
            (user_id, applicant_name, applicant_address, email, sign_type, width, height, quantity, road_name, location_lat, location_lng, fee, status, duration_days, install_date, end_date, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issssddisddiisss",
                $user_id,
                $applicant_name,
                $applicant_address,
                $email,
                $sign_type,
                $width,
                $height,
                $quantity,
                $road_name,
                $lat,
                $lng,
                $fee,
                $duration_days,
                $install_date,
                $end_date,
                $description
            );
            $stmt->execute();
            $request_id = $conn->insert_id;

            // 3.5 กำหนดเลขที่คำร้องแบบทางการ
            $request_no = generateNextRequestNumber($conn);
            $stmt_rn = $conn->prepare("UPDATE sign_requests SET request_no = ? WHERE id = ?");
            $stmt_rn->bind_param("si", $request_no, $request_id);
            $stmt_rn->execute();

            // 4. จัดการไฟล์
            $uploaded_files = [
                'file_sign_plan' => 'แบบป้าย/รูปภาพโฆษณา', // รวมแผนผังและรูป
                'file_id_card' => 'สำเนาบัตรประชาชน',
                'file_house_reg' => 'สำเนาทะเบียนบ้าน',
                'file_land_doc' => 'หนังสือยินยอมเจ้าของที่/สัญญาเช่า',
                'file_other' => 'เอกสารอื่นๆ'
            ];

            // สร้างโฟลเดอร์เก็บไฟล์
            $real_upload_dir = "../uploads/{$request_id}/";
            if (!file_exists($real_upload_dir)) {
                mkdir($real_upload_dir, 0755, true);
            }

            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $max_file_size = 10 * 1024 * 1024; // 10MB

            foreach ($uploaded_files as $input_name => $doc_type_name) {
                if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == UPLOAD_ERR_OK) {
                    // Validate MIME type using finfo (real check)
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $real_mime = $finfo->file($_FILES[$input_name]['tmp_name']);
                    if (!in_array($real_mime, $allowed_mimes)) {
                        throw new Exception("ไฟล์ {$doc_type_name} ไม่ใช่ประเภทที่อนุญาต (รองรับ JPG, PNG, GIF, PDF)");
                    }
                    // Validate file size
                    if ($_FILES[$input_name]['size'] > $max_file_size) {
                        throw new Exception("ไฟล์ {$doc_type_name} มีขนาดเกิน 10MB");
                    }

                    $file_name = time() . '_' . basename($_FILES[$input_name]['name']);
                    $target_path = $real_upload_dir . $file_name;
                    $db_path = "/uploads/{$request_id}/" . $file_name; // Keep leading slash for DB compatibility with .. fix

                    if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_path)) {
                        $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path) VALUES (?, ?, ?)";
                        $stmt_doc = $conn->prepare($sql_doc);
                        $stmt_doc->bind_param("iss", $request_id, $doc_type_name, $db_path);
                        $stmt_doc->execute();
                    }
                }
            }

            $conn->commit();

            // บันทึก Log
            logRequestAction($conn, $request_id, 'created', 'ยื่นคำร้องใหม่', $user_id, 'ประเภท: ' . $sign_type);

            // แสดง SweetAlert และ Redirect
            echo '<!DOCTYPE html>
            <html lang="th">
            <head>
                <meta charset="UTF-8">
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const Toast=Swal.mixin({toast:true,position:"top-end",showConfirmButton:false,timer:1500,timerProgressBar:true,didOpen:(t)=>{t.onmouseenter=Swal.stopTimer;t.onmouseleave=Swal.resumeTimer}});
                        Toast.fire({
                            icon: "success",
                            title: "ยื่นคำร้องสำเร็จ"
                        }).then(() => {
                            window.location.href = "my_request.php";
                        });
                    });
                </script>
            </body>
            </html>';
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง";
            $message_type = 'danger';
        }
    }
}

// ดึงป้ายที่อนุมัติแล้ว (ยังไม่หมดอายุ) สำหรับแสดงบนแผนที่
$approved_signs = [];
$result_signs = $conn->query("SELECT id, location_lat, location_lng, sign_type, road_name, width, height, quantity, end_date
    FROM sign_requests
    WHERE status = 'approved' AND location_lat IS NOT NULL AND location_lng IS NOT NULL
      AND (end_date IS NULL OR end_date >= CURDATE())
    ORDER BY id DESC");
if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        $approved_signs[] = [
            'lat' => (float)$row['location_lat'],
            'lng' => (float)$row['location_lng'],
            'type' => $row['sign_type'],
            'road' => $row['road_name'] ?? '-',
            'size' => $row['width'] . 'x' . $row['height'] . ' ม.',
            'qty' => (int)$row['quantity'],
            'expire' => $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : '-'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยื่นคำร้องขออนุญาตโฆษณา</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        body {
            background-color: #f5f5f5;
        }

        .paper-form {
            background: white;
            padding: 50px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto 30px;
            border-radius: 4px;
            font-family: 'Sarabun', sans-serif;
            position: relative;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-header h3 {
            font-weight: bold;
            color: #000;
        }

        .writing-place {
            text-align: right;
            margin-bottom: 10px;
        }

        .form-line {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: 15px;
            /* font-size: 16px; REMOVED */
            line-height: 1.8;
        }

        .form-line label {
            margin-right: 10px;
            white-space: nowrap;
        }

        .form-input-line {
            border: none;
            border-bottom: 1px dotted #000;
            padding: 0 5px;
            outline: none;
            background: transparent;
            text-align: center;
            color: #004085;
            font-weight: 600;
        }

        .form-input-line:focus {
            border-bottom: 1px solid #0d6efd;
            background-color: #f0f8ff;
        }

        .w-50px {
            width: 50px;
        }

        .w-100px {
            width: 100px;
        }

        .w-150px {
            width: 150px;
        }

        .w-200px {
            width: 200px;
        }

        .w-300px {
            width: 300px;
        }

        .w-full {
            flex-grow: 1;
        }

        /* Map Styles */
        #selectMap {
            height: 350px;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            text-decoration: underline;
        }

        .upload-box {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #fafafa;
        }

        .btn-action {
            padding: 10px 42px;
            border-radius: 20px;
            /* font-size: 16px; REMOVED */
            font-weight: 500;
            transition: all 0.25s ease;
            min-width: 160px;
        }

        /* ปุ่มยกเลิก */
        .btn-cancel {
            background: transparent;
            color: #6c757d;
            border: 2px solid #6c757d;
        }

        .btn-cancel:hover {
            background: #6c757d;
            color: #fff;
            transform: translateY(-2px);
        }

        /* ปุ่มยื่นคำร้อง */
        .btn-submit-main {
            background: #000;
            color: #fff;
            border: 2px solid #000;
        }

        .btn-submit-main:hover {
            background: #222;
            border-color: #222;
            transform: translateY(-2px);
        }

        .file-preview { max-width: 120px; max-height: 90px; border-radius: 4px; border: 1px solid #ddd; margin-top: 6px; }
        .fee-summary { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 12px 18px; margin-top: 10px; font-size: 15px; }
        .fee-summary .fee-total { font-size: 22px; font-weight: 700; color: #2e7d32; }
    </style>
</head>

<body>

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <div class="paper-form fade-in-up">

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="writing-place">
                    เขียนที่ <span class="fw-bold">เทศบาลเมืองศิลา</span>
                </div>
                <!-- วันที่ปัจจุบัน -->
                <div class="writing-place">
                    วันที่ <input type="text" class="form-input-line w-150px" value="<?= date('d/m/Y') ?>" readonly>
                </div>

                <div class="form-header mt-4">
                    <h3>คำร้องขออนุญาตติดตั้งโฆษณา</h3>
                </div>

                <div class="form-line">
                    <label>เรื่อง</label> <span>ขออนุญาตติดตั้งป้ายชั่วคราว</span>
                </div>
                <div class="form-line">
                    <label>เรียน</label> <span>เจ้าพนักงานท้องถิ่น</span>
                </div>

                <!-- ข้อมูลผู้ขออนุญาต (Entity) -->
                <div class="form-line mt-4">
                    <label>1. ผู้ขออนุญาตชื่อ (บุคคล/นิติบุคคล)</label>
                    <input type="text" name="applicant_name" class="form-input-line w-full"
                        value="<?= $me['title_name'] . $me['first_name'] . ' ' . $me['last_name'] ?>" required
                        placeholder="ระบุชื่อบริษัท ห้างหุ้นส่วน หรือบุคคลธรรมดา">
                </div>
                <div class="form-line">
                    <label>อยู่บ้านเลขที่/ที่ตั้งสำนักงาน</label>
                    <input type="text" name="applicant_address" class="form-input-line w-full"
                        value="<?= $me['address'] ?>" required placeholder="ระบุที่อยู่ครบถ้วน">
                </div>
                <div class="form-line">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-input-line w-200px" value="<?= $me['phone'] ?>"
                        required>
                </div>
                <div class="form-line">
                    <label>อีเมล</label>
                    <input type="email" name="email" class="form-input-line w-full" value="<?= $me['email'] ?? '' ?>"
                        required placeholder="example@mail.com">
                </div>

                <div class="form-line mt-4">
                    <span class="ms-4">ขอยื่นคำร้องต่อเจ้าพนักงานท้องถิ่น หรือพนักงานเจ้าหน้าที่ ขออนุญาตทำการโฆษณา
                        โดยปิดทิ้งหรือโปรยแผ่นประกาศหรือใบปลิว ภายในเขตเทศบาลเมืองศิลา ดังรายละเอียดต่อไปนี้:</span>
                </div>

                <!-- รายละเอียดป้าย -->
                <div class="section-title">รายละเอียดการโฆษณา</div>

                <div class="form-line">
                    <label>ประเภทป้าย/สื่อโฆษณา</label>
                    <select name="sign_type" class="form-input-line w-300px" required style="cursor:pointer;">
                        <option value="">-- เลือกประเภทป้าย --</option>
                        <option value="ไวนิล">ไวนิล</option>
                        <option value="ผ้าใบ">ผ้าใบ</option>
                        <option value="ไม้อัด">ไม้อัด</option>
                    </select>
                </div>

                <div class="form-line">
                    <label>ขนาดป้าย กว้าง</label>
                    <input type="number" step="0.01" name="width" id="width" class="form-input-line w-100px" required max="1.2" min="0.01">
                    <label>เมตร x ยาว/สูง</label>
                    <input type="number" step="0.01" name="height" id="height" class="form-input-line w-100px" required max="2.4" min="0.01">
                    <label>เมตร</label>
                    <span id="areaInfo" class="ms-2 badge bg-secondary">พื้นที่: 0 ตร.ม.</span>
                </div>
                <div class="text-muted small ms-4 mb-2"><i class="bi bi-info-circle"></i> ขนาดสูงสุด: กว้างไม่เกิน 1.20 ม. × สูงไม่เกิน 2.40 ม. ทุกประเภท</div>

                <div class="form-line">
                    <label>จำนวน</label>
                    <input type="number" name="quantity" id="quantity" class="form-input-line w-100px" required min="1" max="2"
                        value="1">
                    <label>ผืน/แผ่น (สูงสุด 2ผืน/แผ่น ต่อ 1 คำร้อง)</label>
                </div>

                <div class="fee-summary" id="feeSummary">
                    <i class="bi bi-cash-stack"></i> ค่าธรรมเนียม: 200 บาท x <span id="feeQty">1</span> ป้าย = <span class="fee-total" id="feeTotal">200</span> บาท
                </div>

                <div class="form-line mt-3">
                    <label>ข้อความโฆษณา (โดยสังเขป)</label>
                    <input type="text" name="description" class="form-input-line w-full" required
                        placeholder="เช่น โปรโมชั่นยาง 3 แถม 1">
                </div>

                <!-- สถานที่ติดตั้ง -->
                <div class="section-title mt-3">ตำแหน่งที่ติดตั้ง</div>
                <div class="form-line">
                    <label>จะติดตั้ง ณ ถนน/สถานที่</label>
                    <input type="text" name="road_name" class="form-input-line w-full" required
                        placeholder="ระบุชื่อถนน หรือสถานที่ติดตั้งทั้งหมด">
                </div>

                <!-- Map -->
                <div class="mb-3">
                    <label class="form-label small text-muted">ปักหมุดตำแหน่งหลัก (เพื่อการอ้างอิงพิกัด GPS)</label>
                    <div id="selectMap"></div>
                    <div class="d-flex gap-2 mt-2 align-items-center flex-wrap">
                        <div class="col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Lat</span>
                                <input type="number" step="any" class="form-control" name="lat" id="lat"
                                    placeholder="ละติจูด" required>
                            </div>
                        </div>
                        <div class="flex-fill" style="min-width: 150px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Lng</span>
                                <input type="number" step="any" class="form-control" name="lng" id="lng"
                                    placeholder="ลองจิจูด" required>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <span id="coordDisplay" class="badge bg-secondary d-none"></span>
                            <span id="roadHint" class="badge bg-secondary w-100">รอระบุพิกัด</span>
                        </div>
                    </div>
                </div>

                <!-- ระยะเวลา -->
                <div class="section-title mt-3">ระยะเวลาที่ขออนุญาต</div>
                <div class="form-line">
                    <label>วันที่เริ่มติดตั้ง</label>
                    <input type="date" name="install_date" id="install_date" class="form-input-line" required min="<?= date('Y-m-d') ?>">
                    <label class="ms-3">ถึงวันที่</label>
                    <input type="date" name="end_date" id="end_date" class="form-input-line" required min="<?= date('Y-m-d') ?>">
                    <span id="durationInfo" class="ms-2 badge bg-secondary"></span>
                </div>
                <div class="text-muted small ms-4 mb-2"><i class="bi bi-info-circle"></i> แนะนำระยะเวลาไม่เกิน 30 วัน | ควรยื่นก่อนวันติดตั้งจริงอย่างน้อย 7 วัน (เจ้าหน้าที่จะพิจารณาตามความเหมาะสม)</div>

                <!-- เอกสารแนบ -->
                <div class="section-title mt-4">เอกสารหลักฐานแนบ</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">1. แบบป้าย/รูปภาพโฆษณา *</label>
                        <input type="file" name="file_sign_plan" class="form-control form-control-sm" required accept="image/*,.pdf" onchange="previewFile(this,'preview1')">
                        <img id="preview1" class="file-preview d-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">2. สำเนาบัตรประชาชน *</label>
                        <input type="file" name="file_id_card" class="form-control form-control-sm" required accept="image/*,.pdf" onchange="previewFile(this,'preview2')">
                        <img id="preview2" class="file-preview d-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">3. สำเนาทะเบียนบ้าน *</label>
                        <input type="file" name="file_house_reg" class="form-control form-control-sm" required accept="image/*,.pdf" onchange="previewFile(this,'preview2b')">
                        <img id="preview2b" class="file-preview d-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">4. หนังสือยินยอมเจ้าของที่ (ถ้าตั้งในที่เอกชน)</label>
                        <input type="file" name="file_land_doc" class="form-control form-control-sm" accept="image/*,.pdf" onchange="previewFile(this,'preview3')">
                        <img id="preview3" class="file-preview d-none">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">5. เอกสารอื่นๆ (ถ้ามี)</label>
                        <input type="file" name="file_other" class="form-control form-control-sm" accept="image/*,.pdf" onchange="previewFile(this,'preview4')">
                        <img id="preview4" class="file-preview d-none">
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-12 text-center">
                        <div class="mt-4 mb-4 p-3 border rounded bg-light text-start">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="" id="agreementCheck" required>
                                <label class="form-check-label" for="agreementCheck"
                                    style="text-align: justify; line-height: 1.8;">
                                    ข้าพเจ้าได้ระบุสถานที่ที่จะติดตั้งป้าย ปิด ทิ้ง โปรย แผ่นประกาศใบปลิว
                                    สถานที่ใกล้เคียงพร้อมตัวอย่างของสื่อโฆษณามาด้วยแล้ว
                                    และขอรับรองว่าเมื่อครบกำหนดเวลาในหนังสือขออนุญาตแล้วจะเก็บ ปลดถอน ขูด ลบ หรือล้าง
                                    ป้าย
                                    ปิดทิ้ง โปรย แผ่นประกาศใบปลิว
                                    ออกจากบริเวณดังกล่าว ถ้าเกินกำหนดระยะเวลาแล้ว ข้าพเจ้าไม่ทำการรื้อถอน
                                    ทำให้เทศบาลต้องทำการรื้อถอนเอง
                                    ข้าพเจ้ายินดีชำระค่าปรับ หรือค่ารื้อถอน ป้ายละ ๒๐๐ บาท (สองร้อยบาทถ้วน)
                                    และหากป้ายของข้าพเจ้าทำความเสียหายแก่บุคคลหรือทรัพย์สินของผู้อื่น
                                    ข้าพเจ้าจะเป็นผู้รับผิดชอบแต่เพียงผู้เดียว
                                    โดยข้าพเจ้าขอบันทึกข้อตกลงเพื่อยืนยันแนวทางปฏิบัติของข้าพเจ้า
                                </label>
                            </div>
                        </div>
                        <a href="index.php" class="btn btn-action-cancel me-3">
                            ยกเลิก
                        </a>

                        <button type="submit" name="submit" class="btn btn-action-confirm">
                            ยื่นคำร้อง
                        </button>

                    </div>
                </div>

            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <script>
        // === Preview รูปก่อน Upload ===
        function previewFile(input, previewId) {
            var preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                var file = input.files[0];
                if (file.type.startsWith('image/')) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.classList.add('d-none');
                }
            } else {
                preview.classList.add('d-none');
            }
        }

        // Global variable for boundary data
        var municipalBoundary = null;

        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('selectMap').setView([16.485, 102.835], 13);

            var baseStyle = L.tileLayer('https://api.maptiler.com/maps/base-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
                maxZoom: 20
            }).addTo(map);

            var datavizStyle = L.tileLayer('https://api.maptiler.com/maps/dataviz-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
                maxZoom: 20
            });

            var baseLayers = {
                "แผนที่หลัก": baseStyle,
                "แผนที่ Dataviz": datavizStyle
            };
            var layerControl = L.control.layers(baseLayers, {}, { collapsed: true }).addTo(map);

            // === Map Search (Geocoder) ===
            L.Control.geocoder({
                defaultMarkType: 'L.marker',
                placeholder: 'ค้นหาสถานที่...',
                errorMessage: 'ไม่พบสถานที่',
                collapsed: true,
                geocoder: L.Control.Geocoder.nominatim({ geocodingQueryParams: { countrycodes: 'th', viewbox: '102.7,16.4,102.95,16.55', bounded: 1 } })
            }).on('markgeocode', function(e) {
                var latlng = e.geocode.center;
                map.setView(latlng, 16);
                placeMarker(latlng);
                if (checkBoundary(latlng.lat, latlng.lng)) {
                    var hint = document.getElementById('roadHint');
                    if (hint) { hint.textContent = "เลือกจากการค้นหา"; hint.className = "badge bg-success"; }
                }
            }).addTo(map);

            var marker;

            // Load Boundary (Area Click Handler)
            fetch('../data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    municipalBoundary = data; // Store globally
                    var boundaryLayer = L.geoJSON(data, {
                        style: { color: 'blue', weight: 2, fillOpacity: 0.05 },
                        onEachFeature: function (feature, layer) {
                            layer.on('click', function (e) {
                                placeMarker(e.latlng);
                                // Stop propagation so map click doesn't trigger "Outside" alert
                                L.DomEvent.stopPropagation(e);
                                var hint = document.getElementById('roadHint');
                                if (hint) { hint.textContent = "เลือกพิกัดในเขตพื้นที่แล้ว"; hint.className = "badge bg-success"; }
                            });
                        }
                    }).addTo(map);
                    layerControl.addOverlay(boundaryLayer, "ขอบเขตเทศบาล");
                });

            // === Approved Signs Layer ===
            var approvedData = <?php echo json_encode($approved_signs); ?>;
            if (approvedData.length > 0) {
                var approvedGroup = L.layerGroup();
                var greenIcon = L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
                });
                approvedData.forEach(function(s) {
                    L.marker([s.lat, s.lng], { icon: greenIcon })
                        .bindPopup('<b>' + s.type + '</b><br>ถนน: ' + s.road + '<br>ขนาด: ' + s.size + '<br>จำนวน: ' + s.qty + '<br>หมดอายุ: ' + s.expire)
                        .addTo(approvedGroup);
                });
                layerControl.addOverlay(approvedGroup, "ป้ายที่อนุมัติแล้ว (" + approvedData.length + ")");
            }

            function placeMarker(latlng) {
                if (marker) {
                    marker.setLatLng(latlng);
                } else {
                    marker = L.marker(latlng).addTo(map);
                }
                updateInput(latlng);
            }

            function updateInput(latlng) {
                document.getElementById('lat').value = latlng.lat.toFixed(6);
                document.getElementById('lng').value = latlng.lng.toFixed(6);
            }

            // Ray-casting algorithm for Point in Polygon
            function isPointInPolygon(point, vs) {
                var x = point[0], y = point[1];
                var inside = false;
                for (var i = 0, j = vs.length - 1; i < vs.length; j = i++) {
                    var xi = vs[i][0], yi = vs[i][1];
                    var xj = vs[j][0], yj = vs[j][1];
                    var intersect = ((yi > y) != (yj > y))
                        && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
                    if (intersect) inside = !inside;
                }
                return inside;
            };

            function checkBoundary(lat, lng) {
                if (!municipalBoundary) return true;
                var isInside = false;
                municipalBoundary.features.forEach(function (feature) {
                    if (feature.geometry.type === 'Polygon') {
                        if (isPointInPolygon([lng, lat], feature.geometry.coordinates[0])) {
                            isInside = true;
                        }
                    } else if (feature.geometry.type === 'MultiPolygon') {
                        feature.geometry.coordinates.forEach(function (polygon) {
                            if (isPointInPolygon([lng, lat], polygon[0])) {
                                isInside = true;
                            }
                        });
                    }
                });
                return isInside;
            }

            // Sync Inputs to Map
            function updateMapFromInput() {
                var lat = parseFloat(document.getElementById('lat').value);
                var lng = parseFloat(document.getElementById('lng').value);

                if (!isNaN(lat) && !isNaN(lng)) {
                    var latlng = new L.LatLng(lat, lng);
                    placeMarker(latlng);
                    map.panTo(latlng);

                    if (!checkBoundary(lat, lng)) {
                        var hint = document.getElementById('roadHint');
                        if (hint) { hint.textContent = "อยู่นอกเขตเทศบาล"; hint.className = "badge bg-danger"; }
                        Toast.fire({ icon: 'warning', title: 'พิกัดอยู่นอกเขตเทศบาล' });
                    } else {
                        var hint = document.getElementById('roadHint');
                        if (hint) { hint.textContent = "กำหนดพิกัดเอง"; hint.className = "badge bg-info text-dark"; }
                    }
                }
            }

            document.getElementById('lat').addEventListener('change', updateMapFromInput);

            document.getElementById('lng').addEventListener('change', updateMapFromInput);

            // Road Layer
            fetch('../data/road_sila.geojson')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var roadLayer = L.geoJSON(data, {
                        style: { color: '#f59e0b', weight: 3 },
                        onEachFeature: function (feature, layer) {
                            layer.on('click', function (e) {
                                placeMarker(e.latlng);
                                L.DomEvent.stopPropagation(e);
                                var hint = document.getElementById('roadHint');
                                if (hint) { hint.textContent = "เลือกพิกัดบนเส้นถนนแล้ว"; hint.className = "badge bg-success"; }
                            });
                        }
                    }).addTo(map);
                    layerControl.addOverlay(roadLayer, "เส้นถนน");
                });

            // Map Click (Outside Boundary)
            map.on('click', function () {
                var hint = document.getElementById('roadHint');
                if (hint) { hint.textContent = "อยู่นอกเขตเทศบาล (กรุณาคลิกในขอบเขตสีน้ำเงิน)"; hint.className = "badge bg-danger"; }
                Toast.fire({ icon: 'warning', title: 'จุดที่เลือกอยู่นอกเขตเทศบาล' });
            });

            // === คำนวณพื้นที่ป้าย realtime ===
            function calcArea() {
                var w = parseFloat(document.getElementById('width').value) || 0;
                var h = parseFloat(document.getElementById('height').value) || 0;
                var area = w * h;
                var badge = document.getElementById('areaInfo');
                badge.textContent = 'พื้นที่: ' + area.toFixed(2) + ' ตร.ม.';
                var overSize = (w > 1.2 || h > 2.4);
                badge.className = overSize ? 'ms-2 badge bg-danger' : 'ms-2 badge bg-success';
                if (overSize && (w > 0 || h > 0)) {
                    Toast.fire({ icon: 'warning', title: 'ขนาดเกินกำหนด (ไม่เกิน 1.20×2.40 ม.)' });
                }
            }
            document.getElementById('width').addEventListener('input', calcArea);
            document.getElementById('height').addEventListener('input', calcArea);

            // === Auto คำนวณค่าธรรมเนียม ===
            function calcFee() {
                var qty = parseInt(document.getElementById('quantity').value) || 1;
                if (qty < 1) qty = 1;
                if (qty > 2) qty = 2;
                document.getElementById('feeQty').textContent = qty;
                document.getElementById('feeTotal').textContent = (200 * qty).toLocaleString();
            }

            // === ตรวจสอบระยะเวลา (แสดงจำนวนวัน ไม่จำกัด) ===
            function checkDuration() {
                var installDate = document.getElementById('install_date').value;
                var endDate = document.getElementById('end_date').value;
                var badge = document.getElementById('durationInfo');
                if (!installDate || !endDate) { badge.textContent = ''; return; }

                var d1 = new Date(installDate);
                var d2 = new Date(endDate);
                var diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));

                if (diff < 1) {
                    badge.textContent = 'วันสิ้นสุดต้องมากกว่าวันเริ่ม';
                    badge.className = 'ms-2 badge bg-danger';
                    return;
                }

                if (diff > 30) {
                    badge.textContent = 'ระยะเวลา: ' + diff + ' วัน (เกินระยะเวลาที่แนะนำ อาจไม่ได้รับอนุมัติ)';
                    badge.className = 'ms-2 badge bg-warning text-dark';
                } else {
                    badge.textContent = 'ระยะเวลา: ' + diff + ' วัน';
                    badge.className = 'ms-2 badge bg-success';
                }
            }

            // === Date: install_date เปลี่ยน → อัปเดต min ของ end_date ===
            document.getElementById('install_date').addEventListener('change', function() {
                var endInput = document.getElementById('end_date');
                if (this.value) {
                    endInput.min = this.value;
                    if (endInput.value && endInput.value < this.value) {
                        endInput.value = '';
                    }
                }
                checkDuration();
            });
            document.getElementById('end_date').addEventListener('change', checkDuration);

            // === แจ้งเตือนจำนวนเกิน 2 + คำนวณค่าธรรมเนียม ===
            document.getElementById('quantity').addEventListener('input', function() {
                var qty = parseInt(this.value) || 0;
                if (qty > 2) {
                    Toast.fire({ icon: 'warning', title: 'สูงสุด 2 ผืน/แผ่น ต่อ 1 คำร้อง' });
                    this.value = 2;
                }
                calcFee();
            });
        });
    </script>
    <?php include '../includes/scripts.php'; ?>
</body>

</html>