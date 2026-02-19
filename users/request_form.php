<?php
require '../includes/db.php';
require_once '../includes/log_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
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
        // 2. คำนวณค่าธรรมเนียม (แก้ไข: คิดเหมาป้ายละ 200 บาท ไม่คิดตามขนาด/วัน)
        $fee = 200 * $quantity;

        // 3. Insert ลง DB
        $conn->begin_transaction();
        try {
            // ตรวจสอบคอลัมน์ใหม่ว่ามีหรือยัง (เผื่อ script update schema ยังไม่รัน)
            // แต่เราสมมติว่ารันแล้วตาม Plan
            $sql = "INSERT INTO sign_requests 
            (user_id, applicant_name, applicant_address, email, sign_type, width, height, quantity, road_name, location_lat, location_lng, fee, status, duration_days, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issssddisddiis",
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
                        Swal.fire({
                            icon: "success",
                            title: "ยื่นคำร้องสำเร็จ",
                            text: "เจ้าหน้าที่จะดำเนินการตรวจสอบข้อมูลของท่าน",
                            showConfirmButton: false,
                            timer: 2000
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
    <?php include '../includes/header.php'; ?>
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
                    <label>อีเมล (สำหรับรับแจ้งเตือน)</label>
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
                    <div class="d-flex justify-content-end mt-2 gap-2">
                        <span id="coordDisplay" class="badge bg-secondary">ยังไม่ได้เลือกพิกัด</span>
                        <span id="roadHint" class="badge bg-danger">คลิกได้เฉพาะบนเส้นถนน</span>
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
    <script>
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
            L.control.layers(baseLayers, null, { collapsed: true }).addTo(map);

            var marker;

            // Load Boundary
            fetch('../data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { color: 'blue', weight: 2, fillOpacity: 0.05 }
                    }).addTo(map);
                });

            function placeMarker(latlng) {
                if (marker) {
                    marker.setLatLng(latlng);
                } else {
                    marker = L.marker(latlng).addTo(map);
                }
                updateInput(latlng);
            }

            function updateInput(latlng) {
                document.getElementById('lat').value = latlng.lat;
                document.getElementById('lng').value = latlng.lng;
                document.getElementById('coordDisplay').textContent = "Lat: " + latlng.lat.toFixed(5) + ", Lng: " + latlng.lng.toFixed(5);
                document.getElementById('coordDisplay').className = "badge bg-success";
                var hint = document.getElementById('roadHint');
                if (hint) { hint.textContent = "เลือกพิกัดบนเส้นถนนแล้ว"; hint.className = "badge bg-success"; }
            }

            fetch('../data/road_sila.geojson')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    L.geoJSON(data, {
                        style: { color: '#f59e0b', weight: 3 },
                        onEachFeature: function (feature, layer) {
                            layer.on('click', function (e) {
                                placeMarker(e.latlng);
                                var hint = document.getElementById('roadHint');
                                if (hint) { hint.textContent = "เลือกพิกัดบนเส้นถนนแล้ว"; hint.className = "badge bg-success"; }
                            });
                        }
                    }).addTo(map);
                });
            map.on('click', function () {
                var hint = document.getElementById('roadHint');
                if (hint) { hint.textContent = "คลิกได้เฉพาะบนเส้นถนน"; hint.className = "badge bg-danger"; }
            });
        });
    </script>
    <?php include '../includes/scripts.php'; ?>
</body>

</html>