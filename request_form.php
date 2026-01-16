<?php
// สมมติว่าไฟล์ request_form.php อยู่ในรูทของ Projectป้าย/
require './includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    // 1. รับค่าและทำความสะอาด
    $user_id = $_SESSION['user_id'];
    $sign_type = trim($_POST['sign_type']);
    $width = (float) $_POST['width'];
    $height = (float) $_POST['height'];
    // ตรวจสอบ Lat/Lng ต้องถูกส่งมาจากการเลือกบนแผนที่
    $lat = empty($_POST['lat']) ? NULL : (float) $_POST['lat'];
    $lng = empty($_POST['lng']) ? NULL : (float) $_POST['lng'];
    $duration_days = (int) $_POST['duration_days'];
    $description = trim($_POST['description']);

    // ตรวจสอบว่ามีการเลือกพิกัดแล้ว
    if (is_null($lat) || is_null($lng)) {
        $message = "กรุณาเลือกตำแหน่งติดตั้งป้ายบนแผนที่";
        $message_type = 'danger';
    } else {
        // 2. คำนวณค่าธรรมเนียม
        $area = $width * $height;
        $fee = ($area >= 50) ? 400 : 200;

        // 3. เตรียมและเรียกใช้ SQL เพื่อ INSERT คำขอหลัก
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO sign_requests 
            (user_id, sign_type, width, height, location_lat, location_lng, fee, status, duration_days, description) 
            VALUES (?,?,?,?,?,?,?, 'waiting_payment', ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isddddiis",
                $user_id,
                $sign_type,
                $width,
                $height,
                $lat,
                $lng,
                $fee,
                $duration_days,
                $description
            );
            $stmt->execute();
            $request_id = $conn->insert_id;

            // *** 4. จัดการการอัปโหลดไฟล์ ***
            $uploaded_files = [
                'file_id_card' => 'สำเนาบัตรประชาชน',
                'file_land_doc' => 'สำเนาเอกสารที่ดิน/ยินยอม',
                'file_sign_plan' => 'แผนผังบริเวณ/แบบป้าย',
                'file_engineer_cert' => 'เอกสารรับรองวิศวกร',
            ];

            foreach ($uploaded_files as $input_name => $doc_type_name) {
                if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == UPLOAD_ERR_OK) {
                    // *** จำลองการบันทึก Path ลงฐานข้อมูล sign_documents ***
                    $temp_path = "/uploads/{$request_id}/" . basename($_FILES[$input_name]['name']);

                    // สร้างโฟลเดอร์จริง (ถ้าต้องการให้สมบูรณ์)
                    $real_upload_dir = "./uploads/{$request_id}/";
                    if (!file_exists($real_upload_dir)) {
                        mkdir($real_upload_dir, 0777, true);
                    }
                    move_uploaded_file($_FILES[$input_name]['tmp_name'], $real_upload_dir . basename($_FILES[$input_name]['name']));

                    $sql_doc = "INSERT INTO sign_documents (request_id, doc_type, file_path) VALUES (?, ?, ?)";
                    $stmt_doc = $conn->prepare($sql_doc);
                    $stmt_doc->bind_param("iss", $request_id, $doc_type_name, $temp_path);
                    $stmt_doc->execute();
                }
            }

            $conn->commit();

            // Redirect ไปหน้าจ่ายเงินทันที
            header("Location: payment.php?id=" . $request_id);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = "เกิดข้อผิดพลาดในการยื่นคำขอและอัปโหลดเอกสาร: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยื่นคำขอใหม่</title>
    <?php include './includes/header.php'; ?>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="/Project2026/assets/css/style.css">

    <style>
        #selectMap {
            height: 450px;
            width: 100%;
            border-radius: 8px;
            margin-top: 10px;
            position: relative;
        }

        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        .gps-button {
            background: white;
            border: 2px solid #0d6efd;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            font-size: 14px;
            font-weight: 600;
            color: #0d6efd;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gps-button:hover {
            background: #0d6efd;
            color: white;
        }

        .gps-button:active {
            transform: scale(0.95);
        }

        .coordinates-display {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .coordinates-display .coord-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .coordinates-display .coord-values {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .coordinates-display .coord-item {
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .coordinates-display .coord-item label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
            display: block;
        }

        .coordinates-display .coord-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #0d6efd;
            font-family: 'Courier New', monospace;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <?php include './includes/sidebar.php'; ?>

    <div class="content">
        <div class="card p-4 fade-in-up">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">📝 แบบฟอร์มขออนุญาตติดตั้งป้าย</h2>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> mb-4"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">

                <h4 class="mb-3 text-primary">1. รายละเอียดป้ายและสถานที่ติดตั้ง</h4>
                <hr>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="sign_type" class="form-label">ประเภทป้าย *</label>
                        <input type="text" name="sign_type" id="sign_type" class="form-control"
                            placeholder="เช่น ป้ายโฆษณา, ป้ายประชาสัมพันธ์" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="duration_days" class="form-label">ระยะเวลาติดตั้ง (วัน) *</label>
                        <input type="number" name="duration_days" id="duration_days" class="form-control"
                            placeholder="ไม่เกิน 60 วันสำหรับการค้า" required min="1">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="width" class="form-label">ความกว้าง (เมตร) *</label>
                        <input type="number" step="0.01" name="width" id="width" class="form-control"
                            placeholder="กว้าง" required min="0.01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="height" class="form-label">ความยาว/สูง (เมตร) *</label>
                        <input type="number" step="0.01" name="height" id="height" class="form-control"
                            placeholder="ยาว/สูง" required min="0.01">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">ตำแหน่งติดตั้ง (เลือกบนแผนที่) *</label>
                        <div style="position: relative;">
                            <div id="selectMap"></div>
                            <div class="map-controls">
                                <button type="button" class="gps-button" id="useGpsBtn">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                        <path
                                            d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                                    </svg>
                                    ใช้ตำแหน่ง GPS
                                </button>
                            </div>
                        </div>

                        <!-- แสดงพิกัด Lat/Long -->
                        <div class="coordinates-display">
                            <div class="coord-label">📍 พิกัดที่เลือก:</div>
                            <div class="coord-values">
                                <div class="coord-item">
                                    <label>ละติจูด (Latitude)</label>
                                    <div class="value" id="displayLat">16.48500</div>
                                </div>
                                <div class="coord-item">
                                    <label>ลองจิจูด (Longitude)</label>
                                    <div class="value" id="displayLng">102.83500</div>
                                </div>
                            </div>
                        </div>

                        <p class="small text-muted mt-2">
                            💡 คลิกบนแผนที่, ลากหมุด หรือกดปุ่ม "ใช้ตำแหน่ง GPS" เพื่อกำหนดตำแหน่งป้าย
                        </p>
                    </div>

                    <input type="hidden" name="lat" id="lat" required>
                    <input type="hidden" name="lng" id="lng" required>

                    <div class="col-md-12 mb-4">
                        <label for="description" class="form-label">รายละเอียด/ข้อความโฆษณาโดยสังเขป *</label>
                        <textarea name="description" id="description" class="form-control" rows="3"
                            placeholder="ตัวอย่างข้อความหรือรูปภาพที่จะโฆษณา" required></textarea>
                    </div>
                </div>

                <h4 class="mb-3 mt-4 text-success">2. เอกสารหลักฐานประกอบคำขอ</h4>
                <hr>
                <p class="small text-muted">กรุณาอัปโหลดเอกสารที่จำเป็น (.pdf, .jpg, .png)</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="file_id_card" class="form-label">สำเนาบัตรประชาชน/ทะเบียนบ้าน *</label>
                        <input class="form-control" type="file" id="file_id_card" name="file_id_card" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="file_land_doc" class="form-label">สำเนาโฉนดที่ดิน / หนังสือยินยอมเจ้าของที่
                            *</label>
                        <input class="form-control" type="file" id="file_land_doc" name="file_land_doc" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="file_sign_plan" class="form-label">แผนผังบริเวณที่ติดตั้ง และแบบป้าย *</label>
                        <input class="form-control" type="file" id="file_sign_plan" name="file_sign_plan" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="file_engineer_cert" class="form-label">เอกสารรับรองจากวิศวกร
                            (ถ้ามี/สำหรับป้ายใหญ่)</label>
                        <input class="form-control" type="file" id="file_engineer_cert" name="file_engineer_cert">
                    </div>
                </div>

                <div class="alert alert-warning small mt-4">
                    <strong>หลักเกณฑ์เบื้องต้น:</strong> ป้ายต้องมั่นคงแข็งแรง, ห้ามบังสัญญาณจราจรหรือทัศนียภาพ
                    และต้องรื้อถอนเมื่อหมดอายุอนุญาต.
                </div>

                <div class="col-md-12 mt-4 text-center ">
                    <a href="users/index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
                    </a>
                    <button type="submit" name="submit" class="btn btn-secondary ms-2"> ยื่นคำขออนุญาต</button>

                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // 1. กำหนดพิกัดเริ่มต้น (ใจกลางพื้นที่ให้บริการ เช่น ทม.ศิลา)
            const initialLat = 16.485;
            const initialLng = 102.835;
            const initialZoom = 13;

            // 2. สร้างแผนที่
            var map = L.map('selectMap').setView([initialLat, initialLng], initialZoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            // 3. สร้างหมุดเริ่มต้นและกำหนดให้ลากได้
            var marker = L.marker([initialLat, initialLng], {
                draggable: true
            }).addTo(map);

            // ตัวแปรเก็บข้อมูล Polygon
            var boundaryLayer;
            var silaPolygons = [];

            // โหลด GeoJSON ขอบเขต
            fetch('data/sila.geojson')
                .then(response => response.json())
                .then(data => {
                    // แสดงขอบเขตบนแผนที่
                    boundaryLayer = L.geoJSON(data, {
                        style: {
                            color: 'blue',
                            weight: 2,
                            opacity: 0.6,
                            fillOpacity: 0.05
                        }
                    }).addTo(map);

                    // แปลง GeoJSON เป็น Array ของ Polygon เพื่อใช้เช็คพิกัด
                    data.features.forEach(feature => {
                        if (feature.geometry.type === 'Polygon') {
                            silaPolygons.push(feature.geometry.coordinates[0]); // [0] เพราะ GeoJSON Polygon ซ้อน Array
                        } else if (feature.geometry.type === 'MultiPolygon') {
                            feature.geometry.coordinates.forEach(polygon => {
                                silaPolygons.push(polygon[0]);
                            });
                        }
                    });
                })
                .catch(err => console.error('Error loading GeoJSON:', err));

            // ฟังก์ชันตรวจสอบว่าจุดอยู่ใน Polygon หรือไม่ (Ray-Casting Algorithm)
            function isPointInPolygon(lat, lng, polygon) {
                var x = lng, y = lat;
                var inside = false;
                for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                    var xi = polygon[i][0], yi = polygon[i][1];
                    var xj = polygon[j][0], yj = polygon[j][1];

                    var intersect = ((yi > y) != (yj > y)) &&
                        (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
                    if (intersect) inside = !inside;
                }
                return inside;
            }

            // ฟังก์ชันตรวจสอบว่าอยู่ในเขตพื้นที่บริการหรือไม่
            function checkBoundary(lat, lng) {
                // ถ้ายังโหลด Polygon ไม่เสร็จ ให้ผ่านไปก่อน (หรือจะ Block ก็ได้)
                if (silaPolygons.length === 0) return true;

                let isInside = false;
                for (let poly of silaPolygons) {
                    if (isPointInPolygon(lat, lng, poly)) {
                        isInside = true;
                        break;
                    }
                }
                return isInside;
            }

            // ฟังก์ชันคืนค่าหมุดไปยังตำแหน่งเดิม
            let lastValidLat = initialLat;
            let lastValidLng = initialLng;

            // ฟังก์ชันอัปเดตพิกัดทั้งในฟอร์มและการแสดงผล
            function updateCoordinates(lat, lng, isUserAction = false) {
                // ตรวจสอบขอบเขตเฉพาะเมื่อเกิดจากการกระทำของผู้ใช้
                if (isUserAction && silaPolygons.length > 0) {
                    if (!checkBoundary(lat, lng)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'ไม่อนุญาต',
                            text: 'ไม่สามารถเลือกตำแหน่งนี้ได้ กรุณาปักหมุดภายในเขตพื้นที่รับผิดชอบ (ทม.ศิลา) เท่านั้น',
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'ตกลง'
                        });

                        // คืนค่าตำแหน่งหมุด
                        marker.setLatLng([lastValidLat, lastValidLng]);

                        // รีเซ็ตแผนที่กลับไปที่เดิม (ถ้าต้องการ)
                        // map.panTo([lastValidLat, lastValidLng]); 
                        return;
                    }
                }

                // ถ้าผ่าน หรือไม่ใช่ User Action ให้บันทึกเป็น Last Valid
                lastValidLat = lat;
                lastValidLng = lng;

                const latFixed = lat.toFixed(5);
                const lngFixed = lng.toFixed(5);

                // อัปเดต hidden fields
                document.getElementById('lat').value = latFixed;
                document.getElementById('lng').value = lngFixed;

                // อัปเดตการแสดงผล
                document.getElementById('displayLat').textContent = latFixed;
                document.getElementById('displayLng').textContent = lngFixed;
            }

            // ตั้งค่าพิกัดเริ่มต้น
            updateCoordinates(initialLat, initialLng);

            // 4. ฟังก์ชันอัปเดตพิกัดเมื่อมีการลากหมุด
            marker.on('dragend', function (e) {
                var coords = e.target.getLatLng();
                updateCoordinates(coords.lat, coords.lng, true);
            });

            // 5. ฟังก์ชันอัปเดตพิกัดเมื่อมีการคลิกบนแผนที่
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                updateCoordinates(e.latlng.lat, e.latlng.lng, true);
            });

            // 6. ฟังก์ชันใช้ GPS
            document.getElementById('useGpsBtn').addEventListener('click', function () {
                const button = this;
                const originalText = button.innerHTML;

                // แสดงสถานะกำลังโหลด
                button.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="spinner"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2" fill="none"/></svg> กำลังค้นหา...';
                button.disabled = true;

                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        function (position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;

                            // เช็คก่อนย้าย
                            if (silaPolygons.length > 0 && !checkBoundary(lat, lng)) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'อยู่นอกพื้นที่',
                                    text: 'ตำแหน่งปัจจุบันของคุณอยู่นอกเขตพื้นที่รับผิดชอบ (ทม.ศิลา)',
                                    confirmButtonText: 'ตกลง'
                                });
                                button.innerHTML = originalText;
                                button.disabled = false;
                                return;
                            }

                            // ย้ายแผนที่และหมุดไปยังตำแหน่ง GPS
                            map.setView([lat, lng], 16);
                            marker.setLatLng([lat, lng]);
                            updateCoordinates(lat, lng, true);

                            // คืนค่าปุ่มเป็นปกติ
                            button.innerHTML = originalText;
                            button.disabled = false;

                            // แจ้งเตือนสำเร็จ
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: 'ได้พิกัดจาก GPS แล้ว!',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        },
                        function (error) {
                            console.error('GPS Error:', error);
                            let errorMsg = 'ไม่สามารถใช้ GPS ได้';

                            switch (error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMsg = 'กรุณาอนุญาตให้เข้าถึงตำแหน่งในเบราว์เซอร์';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMsg = 'ไม่สามารถหาตำแหน่งได้ในขณะนี้';
                                    break;
                                case error.TIMEOUT:
                                    errorMsg = 'หมดเวลาในการค้นหาตำแหน่ง';
                                    break;
                            }

                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: errorMsg,
                                confirmButtonText: 'ตกลง'
                            });
                            button.innerHTML = originalText;
                            button.disabled = false;
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไม่รองรับ',
                        text: 'เบราว์เซอร์ของคุณไม่รองรับ GPS',
                        confirmButtonText: 'ตกลง'
                    });
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            });

            // แก้ปัญหาแผนที่ไม่โหลดเต็มที่
            setTimeout(function () {
                map.invalidateSize();
            }, 400);

        });

        // เพิ่ม CSS สำหรับ spinner animation
        const style = document.createElement('style');
        style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spinner {
        animation: spin 1s linear infinite;
    }
`;
        document.head.appendChild(style);
    </script>
    <?php include './includes/scripts.php'; ?>
</body>

</html>