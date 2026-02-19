<?php
require '../includes/db.php';
require_once '../includes/status_helper.php';
require_once '../includes/log_helper.php';

// อนุญาตให้เข้าถึงได้ถ้ามี Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: my_request.php");
    exit;
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ดึงข้อมูลคำขอ
if ($role === 'admin' || $role === 'employee') {
    $sql = "SELECT r.*, u.title_name, u.first_name, u.last_name, u.phone, u.email 
            FROM sign_requests r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
} else {
    $sql = "SELECT r.*, u.title_name, u.first_name, u.last_name, u.phone, u.email 
            FROM sign_requests r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = ? AND r.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $request_id, $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลคำขอ หรือคุณไม่มีสิทธิ์เข้าถึง";
    exit;
}

$request = $result->fetch_assoc();

// ดึงข้อมูลเอกสารแนบ
$sql_docs = "SELECT * FROM sign_documents WHERE request_id = ?";
$stmt_docs = $conn->prepare($sql_docs);
$stmt_docs->bind_param("i", $request_id);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();

// ดึง Timeline Logs
$timeline_logs = getRequestLogs($conn, $request_id);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำขอ #<?= $request['id'] ?></title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #333;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Card Styles */
        .info-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #edf2f7;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .info-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .info-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: #1a202c;
        }

        .info-card-body {
            padding: 24px;
        }

        /* Detail Grid */
        .detail-item {
            margin-bottom: 16px;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #718096;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
            word-break: break-word;
        }

        /* Fee Section */
        .fee-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #edf2f7;
            padding: 24px;
            margin-bottom: 24px;
        }

        .fee-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
            margin: 12px 0;
        }

        .status-box {
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .status-box-title {
            color: #92400e;
            font-weight: 600;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-box-desc {
            color: #b45309;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Documents */
        .doc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .doc-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background 0.2s;
        }

        .doc-item:hover {
            background: #f1f5f9;
        }

        .doc-icon {
            width: 40px;
            height: 40px;
            background: #e2e8f0;
            color: #64748b;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 12px;
        }

        .doc-info {
            flex-grow: 1;
            min-width: 0;
        }

        .doc-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-status {
            font-size: 0.75rem;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Map */
        #map {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        /* Timeline Custom */
        .tl-item {
            position: relative;
            padding-left: 28px;
            padding-bottom: 20px;
            border-left: 2px solid #e2e8f0;
            margin-left: 10px;
        }

        .tl-item:last-child {
            border-left: 0;
            padding-bottom: 0;
        }

        .tl-dot {
            position: absolute;
            left: -7px;
            top: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #cbd5e1;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #cbd5e1;
        }

        .tl-content {
            position: relative;
            top: -5px;
        }

        .tl-time {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .tl-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
        }

        .tl-desc {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 2px;
        }

        /* Back Button */
        .btn-back {
            color: #64748b;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #334155;
        }
    </style>
</head>

<body>

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-3 mb-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <a href="<?= (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'employee'])) ? '../employee/request_list.php' : 'my_request.php' ?>"
                    class="btn-back mb-2 d-inline-flex align-items-center">
                    <i class="bi bi-chevron-left me-1"></i> ย้อนกลับ
                </a>
                <h1 class="page-title mb-1">รายละเอียดคำร้อง</h1>
                <div class="page-subtitle">เลขที่คำขอ
                    #<?= $request['id'] ?>/<?= date('y', strtotime($request['created_at'])) + 43 ?></div>
            </div>
            <div class="d-flex flex-column align-items-end" style="margin-top: 32px;">
                <div class="mb-1"><?= get_status_badge($request['status']) ?></div>
                <div class="text-muted small">
                    <i class="bi bi-clock"></i> ยื่นเมื่อ <?= date('d/m/Y', strtotime($request['created_at'])) ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- ═══ Left Column: Main Info ═══ -->
            <div class="col-lg-8">

                <!-- 1. Applicant Info (ข้อมูลผู้ขออนุญาต) -->
                <div class="info-card">
                    <div class="info-card-header">
                        <h5 class="info-card-title">ข้อมูลผู้ขออนุญาต</h5>
                    </div>
                    <div class="info-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">ชื่อร้าน/บริษัท / ผู้ขออนุญาต</div>
                                    <div class="detail-value">
                                        <?= !empty($request['applicant_name']) ? htmlspecialchars($request['applicant_name']) : $request['first_name'] . ' ' . $request['last_name'] ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">ผู้ติดต่อ</div>
                                    <div class="detail-value">
                                        <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">โทรศัพท์</div>
                                    <div class="detail-value"><?= htmlspecialchars($request['phone'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">อีเมล</div>
                                    <div class="detail-value"><?= htmlspecialchars($request['email'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="detail-item mb-0">
                                    <div class="detail-label">ที่อยู่</div>
                                    <div class="detail-value">
                                        <?= !empty($request['applicant_address']) ? nl2br(htmlspecialchars($request['applicant_address'])) : htmlspecialchars($request['address'] ?? '-') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Sign Info (ข้อมูลป้ายโฆษณา) -->
                <div class="info-card">
                    <div class="info-card-header">
                        <h5 class="info-card-title">ข้อมูลป้ายโฆษณา</h5>
                    </div>
                    <div class="info-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">ประเภทป้าย</div>
                                    <div class="detail-value"><?= htmlspecialchars($request['sign_type']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">ขนาด</div>
                                    <div class="detail-value"><?= $request['width'] ?> x <?= $request['height'] ?> เมตร
                                        (<?= $request['width'] * $request['height'] ?> ตร.ม.)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">จำนวน</div>
                                    <div class="detail-value"><?= $request['quantity'] ?> ป้าย</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <div class="detail-label">ระยะเวลาติดตั้ง</div>
                                    <div class="detail-value text-primary">
                                        <?= date('d M Y', strtotime($request['created_at'])) ?> -
                                        <?= date('d M Y', strtotime($request['created_at'] . " + {$request['duration_days']} days")) ?>
                                        (<?= $request['duration_days'] ?> วัน)
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="detail-item">
                                    <div class="detail-label">ข้อความ/รายละเอียดป้าย</div>
                                    <div class="detail-value"><?= nl2br(htmlspecialchars($request['description'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <hr class="my-3 text-muted opacity-25">
                                <div class="detail-label mb-2">สถานที่ติดตั้ง</div>
                                <div class="mb-2 fw-semibold"><i class="bi bi-geo-alt-fill text-danger me-1"></i> พิกัด:
                                    <?= $request['location_lat'] ?>, <?= $request['location_lng'] ?>
                                </div>
                                <div id="map"></div>
                                <div class="mt-2 text-end">
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $request['location_lat'] ?>,<?= $request['location_lng'] ?>"
                                        target="_blank" class="small text-decoration-none text-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> เปิดใน Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ Right Column: Sidebar ═══ -->
            <div class="col-lg-4">

                <!-- 1. Fee (ค่าธรรมเนียม) -->
                <div class="fee-card">
                    <h5 class="fw-bold text-dark mb-0">ค่าธรรมเนียม</h5>
                    <div class="fee-amount">฿<?= number_format($request['fee']) ?></div>
                    <div class="text-muted small">ค่าธรรมเนียมป้ายโฆษณา (<?= $request['quantity'] ?> ป้าย)</div>

                    <!-- Action Button (Payment) -->
                    <?php if ($request['status'] === 'waiting_payment'): ?>
                        <a href="../payment.php?id=<?= $request['id'] ?>"
                            class="btn btn-primary w-100 mt-3 py-2 fw-bold shadow-sm">
                            <i class="bi bi-qr-code me-2"></i> ชำระเงิน
                        </a>
                    <?php endif; ?>

                    <!-- Yellow Info Box -->
                    <div class="status-box">
                        <div class="status-box-title">
                            <?php if ($request['status'] == 'pending'): ?>
                                <i class="bi bi-clock-history"></i> รอตรวจสอบคำร้อง
                            <?php elseif ($request['status'] == 'approved'): ?>
                                <i class="bi bi-check-circle-fill text-success"></i> อนุมัติแล้ว
                            <?php else: ?>
                                <i class="bi bi-info-circle-fill"></i> สถานะปัจจุบัน
                            <?php endif; ?>
                        </div>
                        <div class="status-box-desc">
                            <?php
                            if ($request['status'] == 'pending')
                                echo "เจ้าหน้าที่กำลังตรวจสอบข้อมูลคำร้องของคุณ จะใช้เวลา 1-2 วันทำการ";
                            elseif ($request['status'] == 'waiting_payment')
                                echo "กรุณาชำระค่าธรรมเนียมเพื่อดำเนินการออกใบอนุญาต";
                            elseif ($request['status'] == 'approved')
                                echo "ใบอนุญาตได้รับการอนุมัติแล้ว คุณสามารถดาวน์โหลดเอกสารได้ด้านล่าง";
                            elseif ($request['status'] == 'rejected')
                                echo "คำร้องถูกปฏิเสธเนื่องจาก: " . $request['decision_note'];
                            elseif ($request['status'] == 'need_documents')
                                echo "กรุณาแนบเอกสารเพิ่มเติม: " . $request['decision_note'];
                            else
                                echo "อยู่ระหว่างการดำเนินการ";
                            ?>
                        </div>
                    </div>
                </div>

                <!-- 2. Timeline (Moved Up) -->
                <div class="info-card">
                    <div class="info-card-header">
                        <h5 class="info-card-title">ประวัติการดำเนินการ</h5>
                    </div>
                    <div class="info-card-body">
                        <?php if (!empty($timeline_logs)): ?>
                            <div class="mt-2">
                                <?php foreach ($timeline_logs as $log):
                                    $icon_data = getTimelineIcon($log['action']);
                                    ?>
                                    <div class="tl-item">
                                        <div class="tl-dot" style="background: <?= $icon_data['color'] ?>; border-color: #fff;">
                                        </div>
                                        <div class="tl-content">
                                            <div class="tl-title"><?= htmlspecialchars($log['action_label']) ?></div>
                                            <div class="tl-time"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></div>
                                            <?php if ($log['note']): ?>
                                                <div class="tl-desc"><?= htmlspecialchars($log['note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">ยังไม่มีประวัติ</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Documents (Moved Down) -->
                <div class="info-card">
                    <div class="info-card-header d-flex justify-content-between align-items-center">
                        <h5 class="info-card-title">เอกสารแนบ</h5>
                        <?php if ($request['status'] === 'need_documents'): ?>
                            <a href="request_edit.php?id=<?= $request['id'] ?>"
                                class="badge bg-warning text-dark text-decoration-none"><i class="bi bi-plus-lg"></i>
                                เพิ่ม</a>
                        <?php endif; ?>
                    </div>
                    <div class="info-card-body p-3">
                        <?php if ($result_docs->num_rows > 0): ?>
                            <ul class="doc-list">
                                <?php while ($doc = $result_docs->fetch_assoc()): ?>
                                    <li>
                                        <a href="../<?= ltrim($doc['file_path'], '/') ?>" target="_blank"
                                            class="text-decoration-none">
                                            <div class="doc-item">
                                                <div class="doc-icon"><i class="bi bi-file-earmark-image"></i></div>
                                                <div class="doc-info">
                                                    <div class="doc-name"><?= htmlspecialchars($doc['doc_type']) ?></div>
                                                    <div class="doc-status"><i class="bi bi-check-circle-fill"></i> อัปโหลดแล้ว
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center text-muted small py-3">ไม่มีเอกสารแนบ</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Map Script -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var lat = <?= $request['location_lat'] ?>;
            var lng = <?= $request['location_lng'] ?>;

            var map = L.map('map').setView([lat, lng], 15);

            // Layers
            var openStreetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            });

            <?php if (defined('MAPTILER_API_KEY')): ?>
                var maptilerHybrid = L.tileLayer('https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=<?= MAPTILER_API_KEY ?>', {
                    attribution: '&copy; MapTiler'
                });
                var maptilerStreets = L.tileLayer('https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=<?= MAPTILER_API_KEY ?>', {
                    attribution: '&copy; MapTiler'
                });
            <?php endif; ?>

            // Add default layer
            <?php if (defined('MAPTILER_API_KEY')): ?>
                maptilerStreets.addTo(map);
            <?php else: ?>
                openStreetMap.addTo(map);
            <?php endif; ?>

            // Layer Control
            var baseMaps = {
                "OpenStreetMap": openStreetMap,
                <?php if (defined('MAPTILER_API_KEY')): ?>
                            "MapTiler Streets": maptilerStreets,
                    "MapTiler Hybrid": maptilerHybrid
                <?php endif; ?>
            };

            var overlayMaps = {};
            var layerControl = L.control.layers(baseMaps, overlayMaps).addTo(map);

            // Boundary (Sila)
            fetch('../data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    var boundaryLayer = L.geoJSON(data, {
                        style: { color: 'blue', weight: 2, fillOpacity: 0 }
                    }).addTo(map);
                    layerControl.addOverlay(boundaryLayer, "เขตเทศบาล");
                })
                .catch(e => console.log('GeoJSON error:', e));

            // Road Layer
            fetch('../data/road_sila.geojson')
                .then(res => res.json())
                .then(data => {
                    var roadLayer = L.geoJSON(data, {
                        style: { color: '#f59e0b', weight: 3 }
                    }).addTo(map);
                    layerControl.addOverlay(roadLayer, "ถนน");
                })
                .catch(e => console.log('Road JSON error:', e));

            // Marker
            L.marker([lat, lng]).addTo(map)
                .bindPopup("<b>จุดที่ติดตั้งป้าย</b>")
                .openPopup();
        });
    </script>

    <?php include '../includes/scripts.php'; ?>

</body>

</html>