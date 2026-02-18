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
    $sql = "SELECT * FROM sign_requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
} else {
    $sql = "SELECT * FROM sign_requests WHERE id = ? AND user_id = ?";
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
        .detail-label {
            font-weight: bold;
            color: #6c757d;
        }

        .detail-value {
            /* font-size: 1.1em; REMOVED */
            color: #000;
        }

        #map {
            height: 300px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .doc-item {
            border: 1px solid #efefef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }

        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -23px;
            top: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            z-index: 1;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 10px 14px;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
        }

        .timeline-content .time {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .timeline-content .actor {
            font-size: 0.8rem;
            color: #495057;
        }
    </style>
</head>

<body>

    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">📋 รายละเอียดคำขอ #<?= $request['id'] ?></h2>
                <?php
                $back_link = 'my_request.php';
                if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'employee')) {
                    $back_link = '../employee/request_list.php';
                }
                ?>
                <a href="<?= $back_link ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
                </a>
            </div>

            <div class="row">
                <!-- ข้อมูลทั่วไป -->
                <div class="col-md-8">
                    <div class="card p-4 mb-4 fade-in-up">
                        <h4 class="text-primary mb-3">ข้อมูลป้าย</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-label">สถานะตรวจสอบ</div>
                                <div class="detail-value"><?= get_status_badge($request['status']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">วันที่ยื่นคำขอ</div>
                                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">ประเภทป้าย</div>
                                <div class="detail-value"><?= htmlspecialchars($request['sign_type']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">ขนาด (กว้าง x ยาว/สูง)</div>
                                <div class="detail-value">
                                    <?= $request['width'] ?> x <?= $request['height'] ?> เมตร
                                    <span class="text-muted">(<?= $request['width'] * $request['height'] ?>
                                        ตร.ม.)</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">ค่าธรรมเนียมประเมิน</div>
                                <div class="detail-value text-success fw-bold"><?= number_format($request['fee']) ?> บาท
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">ระยะเวลาติดตั้ง</div>
                                <div class="detail-value"><?= $request['duration_days'] ?> วัน</div>
                            </div>
                            <div class="col-12">
                                <div class="detail-label">รายละเอียด/ข้อความ</div>
                                <div class="p-3 bg-light rounded mt-1">
                                    <?= nl2br(htmlspecialchars($request['description'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($request['decision_note'])): ?>
                                <div class="col-12">
                                    <div class="detail-label">บันทึกจากเจ้าหน้าที่</div>
                                    <div class="p-3 bg-warning-subtle rounded mt-1">
                                        <?= nl2br(htmlspecialchars($request['decision_note'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- แสดงแผนที่ -->
                    <div class="card p-4 fade-in-up delay-100">
                        <h4 class="text-primary mb-3">📍 สถานที่ติดตั้ง</h4>
                        <div id="map"></div>
                        <div class="mt-2 text-muted small">
                            พิกัด: <?= $request['location_lat'] ?>, <?= $request['location_lng'] ?>
                        </div>
                    </div>
                </div>

                <!-- ไฟล์เอกสาร -->
                <div class="col-md-4">
                    <!-- Timeline สถานะ -->
                    <div class="card p-4 fade-in-up mb-4">
                        <h4 class="text-primary mb-3">📅 ลำดับขั้นตอน</h4>
                        <?php if (!empty($timeline_logs)): ?>
                            <div class="timeline">
                                <?php foreach ($timeline_logs as $log):
                                    $icon_data = getTimelineIcon($log['action']);
                                    $actor_name = $log['first_name']
                                        ? ($log['title_name'] . $log['first_name'] . ' ' . $log['last_name'])
                                        : 'ระบบอัตโนมัติ';
                                    ?>
                                    <div class="timeline-item">
                                        <div class="timeline-dot" style="background: <?= $icon_data['color'] ?>; color: white;">
                                            <?= $icon_data['icon'] ?>
                                        </div>
                                        <div class="timeline-content" style="border-left-color: <?= $icon_data['color'] ?>;">
                                            <div class="fw-bold" style="font-size: 0.9rem;">
                                                <?= htmlspecialchars($log['action_label']) ?>
                                            </div>
                                            <div class="time">
                                                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                            </div>
                                            <div class="actor">
                                                โดย: <?= htmlspecialchars($actor_name) ?>
                                            </div>
                                            <?php if (!empty($log['note'])): ?>
                                                <div class="text-muted" style="font-size: 0.8rem; margin-top: 4px;">
                                                    <?= htmlspecialchars($log['note']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">ยังไม่มีประวัติ</p>
                        <?php endif; ?>
                    </div>

                    <!-- เอกสารราชการ (ใบอนุญาต/ใบเสร็จ) -->
                    <?php if ($request['status'] === 'approved'): ?>
                        <div class="card p-4 fade-in-up mb-4 border-success shadow-sm">
                            <div class="text-center mb-3">
                                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                                <h5 class="text-success mt-2">อนุมัติแล้ว</h5>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="view_permission.php?id=<?= $request['id'] ?>" target="_blank"
                                    class="btn btn-success">
                                    <i class="bi bi-file-earmark-check"></i> ดูหนังสืออนุญาต
                                </a>
                                <a href="view_sticker.php?id=<?= $request['id'] ?>" target="_blank" class="btn btn-warning">
                                    <i class="bi bi-sticky-fill"></i> พิมพ์สติ๊กเกอร์ติดป้าย
                                </a>
                                <a href="view_receipt.php?id=<?= $request['id'] ?>" target="_blank"
                                    class="btn btn-outline-success">
                                    <i class="bi bi-receipt"></i> ดูใบเสร็จรับเงิน
                                </a>
                                <hr>
                                <a href="renew_permit.php?id=<?= $request['id'] ?>" class="btn btn-outline-primary">
                                    🔄 ต่ออายุใบอนุญาต
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card p-4 fade-in-up delay-200">
                        <h4 class="text-success mb-3">📁 เอกสารแนบ</h4>
                        <?php if ($request['status'] === 'need_documents'): ?>
                            <a href="request_edit.php?id=<?= $request['id'] ?>" class="btn btn-warning w-100 mb-3">
                                ยื่นเอกสารเพิ่มเติม
                            </a>
                        <?php endif; ?>
                        <?php if ($result_docs->num_rows > 0): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php while ($doc = $result_docs->fetch_assoc()): ?>
                                    <div class="doc-item">
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($doc['doc_type']) ?></div>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                                            class="btn btn-outline-primary btn-sm w-100">
                                            ดูเอกสาร
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">ไม่มีเอกสารแนบ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var lat = <?= $request['location_lat'] ?>;
            var lng = <?= $request['location_lng'] ?>;

            var map = L.map('map').setView([lat, lng], 15);

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

            fetch('../data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { color: 'blue', weight: 2, fillOpacity: 0 }
                    }).addTo(map);
                });

            fetch('../data/road_sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { color: '#f59e0b', weight: 3 }
                    }).addTo(map);
                });

            L.marker([lat, lng]).addTo(map)
                .bindPopup("<b>จุดที่ติดตั้งป้าย</b>")
                .openPopup();
        });
    </script>

</body>
<?php include '../includes/scripts.php'; ?>

</html>