<?php
require './includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// ดึงข้อมูลผู้ใช้เพื่อ Pre-fill (ส่วนข้าพเจ้า)
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$me = $stmt_user->get_result()->fetch_assoc();

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    // 1. รับค่า
    $applicant_name = trim($_POST['applicant_name']);
    $applicant_address = trim($_POST['applicant_address']);
    $sign_type = trim($_POST['sign_type']);
    $width = (float) $_POST['width'];
    $height = (float) $_POST['height'];
    $quantity = (int) $_POST['quantity'];
    $road_name = trim($_POST['road_name']);
    $description = trim($_POST['description']);

    // วันที่และระยะเวลา
    $install_date = $_POST['install_date'];
    $end_date = $_POST['end_date'];
    // คำนวณระยะเวลา (วัน)
    $d1 = new DateTime($install_date);
    $d2 = new DateTime($end_date);
    $interval = $d1->diff($d2);
    $duration_days = $interval->days + 1; // รวมวันแรก

    // พิกัด (Primary)
    $lat = empty($_POST['lat']) ? NULL : (float) $_POST['lat'];
    $lng = empty($_POST['lng']) ? NULL : (float) $_POST['lng'];

    if (is_null($lat) || is_null($lng)) {
        $message = "กรุณาปักหมุดตำแหน่งหลักบนแผนที่";
        $message_type = 'danger';
    } else {
        // 2. คำนวณค่าธรรมเนียม
        $area = $width * $height; // ตร.ม. ต่อป้าย
        $rate = ($area >= 50) ? 400 : 200; // อัตราต่อป้าย
        $fee = $rate * $quantity; // ราคารวม

        // 3. Insert ลง DB
        $conn->begin_transaction();
        try {
            // ตรวจสอบคอลัมน์ใหม่ว่ามีหรือยัง (เผื่อ script update schema ยังไม่รัน)
            // แต่เราสมมติว่ารันแล้วตาม Plan
            $sql = "INSERT INTO sign_requests 
            (user_id, applicant_name, applicant_address, sign_type, width, height, quantity, road_name, location_lat, location_lng, fee, status, duration_days, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting_payment', ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isssddisddiis",
                $user_id,
                $applicant_name,
                $applicant_address,
                $sign_type,
                $width,
                $height,
                $quantity,
                $road_name,
                $lat,
                $lng,
                $fee,
                $duration_days,
                $description
            );
            $stmt->execute();
            $request_id = $conn->insert_id;

            // 4. จัดการไฟล์
            $uploaded_files = [
                'file_sign_plan' => 'แบบป้าย/รูปภาพโฆษณา', // รวมแผนผังและรูป
                'file_id_card' => 'สำเนาบัตรประชาชน',
                'file_land_doc' => 'หนังสือยินยอมเจ้าของที่/สัญญาเช่า',
                'file_other' => 'เอกสารอื่นๆ'
            ];

            // สร้างโฟลเดอร์เก็บไฟล์
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

            $conn->commit();
            header("Location: payment.php?id=" . $request_id); // ไปหน้าจ่ายเงิน (หรือหน้ารายการ)
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยื่นคำร้องขออนุญาตโฆษณา</title>
    <?php include './includes/header.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            background-color: #f5f5f5;
        }

        .paper-form {
            background: white;
            padding: 50px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 30px auto;
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
            font-size: 16px;
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

        .btn-submit {
            background-color: #000;
            color: white;
            padding: 10px 40px;
            border-radius: 30px;
            font-size: 18px;
            border: none;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background-color: #333;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>

    <?php include './includes/sidebar.php'; ?>

    <div class="content">
        <div class="paper-form fade-in-up">

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">

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

                <div class="form-line mt-4">
                    <span class="ms-4">ขอยื่นคำร้องต่อเจ้าพนักงานท้องถิ่น หรือพนักงานเจ้าหน้าที่ ขออนุญาตทำการโฆษณา
                        โดยปิดทิ้งหรือโปรยแผ่นประกาศหรือใบปลิว ภายในเขตเทศบาลเมืองศิลา ดังรายละเอียดต่อไปนี้:</span>
                </div>

                <!-- รายละเอียดป้าย -->
                <div class="section-title">รายละเอียดการโฆษณา</div>

                <div class="form-line">
                    <label>ประเภทป้าย/สื่อโฆษณา</label>
                    <input type="text" name="sign_type" class="form-input-line w-300px"
                        placeholder="เช่น ป้ายคัทเอาท์, ป้ายผ้าใบ" required>
                </div>

                <div class="form-line">
                    <label>ขนาดป้าย กว้าง</label>
                    <input type="number" step="0.01" name="width" id="width" class="form-input-line w-100px" required>
                    <label>เมตร x ยาว/สูง</label>
                    <input type="number" step="0.01" name="height" id="height" class="form-input-line w-100px" required>
                    <label>เมตร</label>
                </div>

                <div class="form-line">
                    <label>จำนวน</label>
                    <input type="number" name="quantity" id="quantity" class="form-input-line w-100px" required min="1"
                        value="1">
                    <label>ป้าย</label>
                </div>

                <div class="form-line">
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
                    <div class="d-flex justify-content-end mt-2">
                        <span id="coordDisplay" class="badge bg-secondary">ยังไม่ได้เลือกพิกัด</span>
                    </div>
                    <input type="hidden" name="lat" id="lat">
                    <input type="hidden" name="lng" id="lng">
                </div>

                <!-- ระยะเวลา -->
                <div class="section-title mt-3">ระยะเวลาที่ขออนุญาต</div>
                <div class="form-line">
                    <label>ตั้งแต่วันที่</label>
                    <input type="date" name="install_date" class="form-input-line" required>
                    <label>ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-input-line" required>
                </div>

                <!-- เอกสารแนบ -->
                <div class="section-title mt-4">เอกสารหลักฐานแนบ</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">1. แบบป้าย/รูปภาพโฆษณา *</label>
                        <input type="file" name="file_sign_plan" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">2. สำเนาบัตรประชาชนผู้ขออนุญาต *</label>
                        <input type="file" name="file_id_card" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">3. หนังสือยินยอมเจ้าของที่ (ถ้าตั้งในที่เอกชน)</label>
                        <input type="file" name="file_land_doc" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">4. เอกสารอื่นๆ (ถ้ามี)</label>
                        <input type="file" name="file_other" class="form-control form-control-sm">
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-12 text-center">
                        <p class="small text-muted mb-4">ข้าพเจ้าขอรับรองว่าข้อความข้างต้นเป็นความจริงทุกประการ</p>
                        <a href="users/index.php" class="btn btn-outline-secondary px-4 me-2">ยกเลิก</a>
                        <button type="submit" name="submit" class="btn btn-submit">ยื่นคำร้อง</button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('selectMap').setView([16.485, 102.835], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'OpenStreetMap',
                maxZoom: 19
            }).addTo(map);

            var marker;

            // Load Boundary
            fetch('data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { color: 'blue', weight: 2, fillOpacity: 0.05 }
                    }).addTo(map);
                });

            function onMapClick(e) {
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng, { draggable: true }).addTo(map);
                    marker.on('dragend', function (event) {
                        updateInput(event.target.getLatLng());
                    });
                }
                updateInput(e.latlng);
            }

            function updateInput(latlng) {
                document.getElementById('lat').value = latlng.lat;
                document.getElementById('lng').value = latlng.lng;
                document.getElementById('coordDisplay').textContent = "Lat: " + latlng.lat.toFixed(5) + ", Lng: " + latlng.lng.toFixed(5);
                document.getElementById('coordDisplay').className = "badge bg-success";
            }

            map.on('click', onMapClick);
        });
    </script>
    <?php include './includes/scripts.php'; ?>
</body>

</html>