<?php
require 'includes/db.php';
require 'includes/thaibaht.php';

function showPermitError($title, $message) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?> - เทศบาลเมืองศิลา</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
        <style>body{font-family:'Sarabun',sans-serif;background:#f8fafc;min-height:100vh;display:flex;align-items:center;justify-content:center;}</style>
    </head>
    <body>
        <div class="text-center p-5">
            <div class="mb-4"><i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:4rem;"></i></div>
            <h3 class="fw-bold mb-3"><?= $title ?></h3>
            <p class="text-muted mb-4"><?= $message ?></p>
            <a href="index.php" class="btn btn-primary px-4">กลับสู่หน้าหลัก</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!isset($_GET['id'])) {
    showPermitError('ไม่พบข้อมูล', 'กรุณาระบุเลขที่ใบอนุญาตที่ต้องการตรวจสอบ');
}

$request_id = (int) $_GET['id'];
$sql = "SELECT r.*, u.title_name, u.first_name, u.last_name 
        FROM sign_requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    showPermitError('ไม่พบข้อมูลใบอนุญาต', 'ไม่พบข้อมูลใบอนุญาตในระบบ กรุณาตรวจสอบเลขที่อีกครั้ง');
}

// Calculate status color/text
$status_badge = '';
$is_valid = false;
if ($request['status'] == 'approved') {
    // Check expiry based on permit_date (official issue date)
    $expire_date = !empty($request['end_date']) ? $request['end_date'] : date('Y-m-d', strtotime((!empty($request['permit_date']) ? $request['permit_date'] : $request['created_at']) . ' + ' . $request['duration_days'] . ' days'));
    if (date('Y-m-d') <= $expire_date) {
        $status_badge = '<span class="badge bg-success fs-5"><i class="bi bi-check-circle-fill"></i> ใบอนุญาตถูกต้อง</span>';
        $is_valid = true;
    } else {
        $status_badge = '<span class="badge bg-danger fs-5"><i class="bi bi-x-circle-fill"></i> ใบอนุญาตหมดอายุ</span>';
    }
} else {
    $status_badge = '<span class="badge bg-secondary fs-5">สถานะ: ' . htmlspecialchars($request['status']) . '</span>';
}

$lat = $request['location_lat'] ?? '16.482780';
$lng = $request['location_lng'] ?? '102.812704';

$sila_geojson   = file_get_contents(__DIR__ . '/data/sila.geojson');
$road_geojson   = file_get_contents(__DIR__ . '/data/road_sila.geojson');

// PDPA: Mask ชื่อบางส่วนเพื่อคุ้มครองข้อมูลส่วนบุคคล
function maskName($name) {
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 1) return $name;
    if ($len == 2) return mb_substr($name, 0, 1, 'UTF-8') . '*';
    return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', $len - 2) . mb_substr($name, -1, 1, 'UTF-8');
}

function getThaiDate($date)
{
    if (!$date)
        return "-";
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[(int) date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    return "$d $m $y";
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบใบอนุญาต - เทศบาลเมืองศิลา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- MapTiler / Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8fafc;
        }

        .verification-card {
            max-width: 600px;
            margin: 2rem auto;
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #1a56db, #3b82f6);
            color: white;
            text-align: center;
            padding: 2rem 1rem;
        }

        .header-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-weight: 500;
        }

        .info-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        #map {
            height: 300px;
            width: 100%;
            border-radius: 12px;
            margin-top: 1.5rem;
        }

        .valid-pulse {
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
    </style>
</head>

<body>

    <div class="container px-3">
        <div class="card verification-card <?= $is_valid ? 'valid-pulse' : '' ?>">
            <div class="card-header">
                <div class="header-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4 class="mb-0">ตรวจสอบใบอนุญาต</h4>
                <p class="mb-0 opacity-75">เทศบาลเมืองศิลา จ.ขอนแก่น</p>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <?= $status_badge ?>
                    <div class="mt-2 text-muted small">
                        ตรวจสอบเมื่อ:
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-label">เลขที่ใบอนุญาต</span>
                    <span class="info-value">
                        <?= htmlspecialchars($request['permit_no'] ?: '-') ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ผู้ได้รับอนุญาต</span>
                    <span class="info-value">
                        <?= htmlspecialchars($request['title_name']) . maskName(htmlspecialchars($request['first_name'])) . ' ' . maskName(htmlspecialchars($request['last_name'])) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ประเภทป้าย</span>
                    <span class="info-value">
                        <?= htmlspecialchars($request['sign_type']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ขนาด</span>
                    <span class="info-value">
                        <?= $request['width'] ?> x
                        <?= $request['height'] ?> ม.
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">วันที่ได้รับอนุญาต</span>
                    <span class="info-value">
                        <?= getThaiDate($request['permit_date']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">สิ้นสุดวันที่</span>
                    <span class="info-value">
                        <?= getThaiDate(date('Y-m-d', strtotime($expire_date))) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">สถานที่ติดตั้ง</span>
                    <span class="info-value">
                        <?= htmlspecialchars($request['road_name']) ?>
                    </span>
                </div>

                <!-- Map -->
                <h6 class="mt-4 mb-2 fw-bold"><i class="bi bi-geo-alt-fill text-danger"></i> พิกัดติดตั้งจริง</h6>
                <div id="map"></div>

                <div class="text-center mt-4">
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $lat ?>,<?= $lng ?>" target="_blank"
                        class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-cursor-fill"></i> นำทางไปที่ป้าย
                    </a>
                    <a href="index.php" class="btn btn-link text-muted text-decoration-none small">กลับสู่หน้าหลัก</a>
                </div>
            </div>
            <div class="card-footer bg-light text-center py-3">
                <small class="text-muted">เอกสารนี้สร้างโดยระบบอัตโนมัติของเทศบาลเมืองศิลา</small>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('map').setView([<?= $lat ?>, <?= $lng ?>], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // เส้นขอบเขต ทม.ศิลา
            var silaData = <?= $sila_geojson ?>;
            L.geoJSON(silaData, {
                style: {
                    color: '#e74c3c',
                    weight: 2.5,
                    opacity: 0.9,
                    fillColor: '#e74c3c',
                    fillOpacity: 0.06
                }
            }).addTo(map);

            // เส้นถนนในเขต ทม.ศิลา
            var roadData = <?= $road_geojson ?>;
            L.geoJSON(roadData, {
                style: {
                    color: '#2980b9',
                    weight: 1.5,
                    opacity: 0.6
                }
            }).addTo(map);

            L.marker([<?= $lat ?>, <?= $lng ?>]).addTo(map)
                .bindPopup("<b>จุดติดตั้งป้าย</b><br><?= htmlspecialchars($request['road_name']) ?>")
                .openPopup();
        });
    </script>

</body>

</html>