<?php
// สมมติว่าไฟล์ map.php อยู่ในรูทของ Projectป้าย/
require '../includes/db.php';

// Employee GIS map - require login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
    exit;
}

// กำหนดบทบาทและผู้ใช้ปัจจุบัน
$role = $_SESSION['role'] ?? 'guest';
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// ดึงข้อมูลคำร้องที่มีพิกัดและสถานะ เพื่อแสดงบนแผนที่
$approved_signs = [];
if ($role === 'user') {
    $stmt = $conn->prepare("SELECT id, location_lat, location_lng, sign_type, status FROM sign_requests WHERE user_id = ? AND location_lat IS NOT NULL AND location_lng IS NOT NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result_signs = $stmt->get_result();
} else {
    $result_signs = $conn->query("SELECT id, location_lat, location_lng, sign_type, status FROM sign_requests WHERE location_lat IS NOT NULL AND location_lng IS NOT NULL");
}
if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        $approved_signs[] = [
            'id' => (int) $row['id'],
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type']),
            'status' => htmlspecialchars($row['status'])
        ];
    }
}

$approved_rows = [];
if ($role === 'user') {
    $stmt_rows = $conn->prepare("SELECT r.id, r.location_lat, r.location_lng, r.sign_type, r.receipt_date, r.description, r.duration_days, u.title_name, u.first_name, u.last_name, u.address, u.phone 
                                 FROM sign_requests r 
                                 JOIN users u ON r.user_id = u.id 
                                 WHERE r.user_id = ? AND r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                                 ORDER BY r.id DESC LIMIT 1000");
    $stmt_rows->bind_param("i", $userId);
    $stmt_rows->execute();
    $res_rows = $stmt_rows->get_result();
} else {
    $res_rows = $conn->query("SELECT r.id, r.location_lat, r.location_lng, r.sign_type, r.receipt_date, r.description, r.duration_days, u.title_name, u.first_name, u.last_name, u.address, u.phone 
                              FROM sign_requests r 
                              JOIN users u ON r.user_id = u.id 
                              WHERE r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                              ORDER BY r.id DESC LIMIT 1000");
}
if ($res_rows && $res_rows->num_rows > 0) {
    while ($row = $res_rows->fetch_assoc()) {
        $approved_rows[] = [
            'id' => (int) $row['id'],
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type']),
            'desc' => htmlspecialchars($row['description'] ?? ''),
            'duration' => (int) ($row['duration_days'] ?? 0),
            'name' => htmlspecialchars(($row['title_name'] ?? '') . $row['first_name'] . ' ' . $row['last_name']),
            'address' => htmlspecialchars($row['address'] ?? ''),
            'phone' => htmlspecialchars($row['phone'] ?? ''),
            'date' => $row['receipt_date'] ? date('d/m/Y', strtotime($row['receipt_date'])) : ''
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แผนที่ GIS - เจ้าหน้าที่</title>

    <?php include '../includes/header.php'; ?>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        .map-section-header {
            margin-bottom: 25px;
        }

        #mapid {
            height: 600px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .list-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            background: #fff;
        }

        .table-wrap {
            max-height: 500px;
            overflow: auto;
        }

        .table {
            min-width: 600px;
            font-size: 13px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .table th {
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            font-size: 11px;
            background-color: #f8fafc;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr {
            cursor: pointer;
            transition: background 0.2s;
        }

        .table tbody tr:hover {
            background-color: #f0f7ff !important;
        }

        .full-height-card {
            min-height: calc(100vh - 100px);
            padding: 30px !important;
        }

        .badge-legend {
            font-weight: 500;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .content {
            padding: 25px 40px;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="card fade-in-up full-height-card">
            <div class="map-section-header">
                <h2 class="mb-1" style="font-size: 1.6rem;">🗺️ แผนที่ข้อมูลพื้นที่</h2>
                <p class="text-muted mb-3">แสดงขอบเขตตำแหน่งป้ายในเขต ทม.ศิลา</p>

                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <span class="badge-legend" style="background-color: #16a34a; color: white;">🪧 อนุมัติแล้ว</span>
                    <span class="badge-legend" style="background-color: #f59e0b; color: white;">🪧 รอดำเนินการ</span>
                    <span class="badge-legend" style="background-color: #3b82f6; color: white;">🪧 รอชำระเงิน</span>
                    <span class="badge-legend" style="background-color: #8b5cf6; color: white;">🪧 รอใบเสร็จ</span>
                    <span class="badge-legend" style="background-color: #dc2626; color: white;">🪧 ไม่อนุมัติ</span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div id="mapid"></div>
                </div>

                <div class="col-12 mt-5">
                    <div class="list-card overflow-hidden">
                        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light">
                            <h5 class="mb-0 fw-bold">📋 รายการคำร้องบนแผนที่ (คลิกที่รายการเพื่อดูตำแหน่ง)</h5>
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <label class="small text-muted mb-0">แสดง</label>
                                    <select id="pageSize" class="form-select form-select-sm w-auto">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                    </select>
                                </div>
                                <div class="input-group input-group-sm" style="width: 280px;">
                                    <span class="input-group-text bg-white border-end-0"><i
                                            class="bi bi-search"></i></span>
                                    <input id="searchInput" type="text" class="form-control border-start-0"
                                        placeholder="ค้นหา ชื่อ/ที่อยู่/ประเภท...">
                                </div>
                            </div>
                        </div>
                        <div class="fixed-card-body p-0">
                            <div class="table-wrap">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>เลขคำขอ</th>
                                            <th>ประเภทป้าย</th>
                                            <th>รายละเอียด</th>
                                            <th>ระยะเวลา</th>
                                            <th>ชื่อผู้ขอ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center bg-light">
                            <div id="pageInfo" class="text-muted small"></div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item"><button id="prevBtn" class="page-link"><i
                                                class="bi bi-chevron-left"></i> ย้อนกลับ</button></li>
                                    <li class="page-item"><button id="nextBtn" class="page-link">ถัดไป <i
                                                class="bi bi-chevron-right"></i></button></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const initialLat = 16.485;
            const initialLng = 102.835;
            const initialZoom = 13;

            if (typeof L === 'undefined') {
                console.error("Leaflet library failed to load.");
                document.getElementById('mapid').innerHTML = '<div class="alert alert-danger m-4">ไม่สามารถโหลดแผนที่ได้</div>';
                return;
            }

            var mymap = L.map('mapid', { zoomControl: true }).setView([initialLat, initialLng], initialZoom);

            // Base Layers
            var mainStyle = L.tileLayer('https://api.maptiler.com/maps/base-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                maxZoom: 20,
                attribution: '&copy; MapTiler'
            }).addTo(mymap);

            var satStyle = L.tileLayer('https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=<?php echo MAPTILER_API_KEY; ?>', {
                maxZoom: 20,
                attribution: '&copy; MapTiler'
            });

            var datavizStyle = L.tileLayer('https://api.maptiler.com/maps/dataviz-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                maxZoom: 20,
                attribution: '&copy; MapTiler'
            });

            var baseLayers = {
                "แผนที่หลัก": mainStyle,
                "แผนที่ดาวเทียม": satStyle,
                "แผนที่ Dataviz": datavizStyle
            };

            var approvedSigns = <?php echo json_encode($approved_signs); ?>;
            var approvedList = <?php echo json_encode($approved_rows); ?>;
            var markerDict = {}; // Store markers by ID for interactive zooming

            var markers = L.markerClusterGroup();
            var heatLayer = L.heatLayer(approvedSigns.map(s => [s.lat, s.lng, 0.6]), { radius: 20, blur: 15 });

            approvedSigns.forEach(function (sign) {
                if (sign.lat && sign.lng) {
                    var markerColor = '#16a34a';
                    var statusText = 'อนุมัติแล้ว';

                    if (sign.status === 'pending') { markerColor = '#f59e0b'; statusText = 'รอดำเนินการ'; }
                    else if (sign.status === 'waiting_payment') { markerColor = '#3b82f6'; statusText = 'รอชำระเงิน'; }
                    else if (sign.status === 'waiting_receipt') { markerColor = '#8b5cf6'; statusText = 'รอใบเสร็จ'; }
                    else if (sign.status === 'rejected') { markerColor = '#dc2626'; statusText = 'ไม่อนุมัติ'; }
                    else if (sign.status === 'cancelled') { markerColor = '#6b7280'; statusText = 'ยกเลิก'; }

                    var customIcon = L.divIcon({
                        className: 'custom-marker',
                        html: '<div style="background-color: ' + markerColor + '; width: 28px; height: 28px; border-radius: 8px; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;">🪧</div>',
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    });

                    var m = L.marker([sign.lat, sign.lng], { icon: customIcon })
                        .bindPopup("<b>เลขคำขอ #" + sign.id + "</b><br>ประเภท: " + sign.type + "<br>สถานะ: " + statusText);

                    markers.addLayer(m);
                    markerDict[sign.id] = m;
                }
            });
            markers.addTo(mymap);

            // Overlays
            var boundaryLayer = L.layerGroup();
            var roadLayer = L.layerGroup();

            var overlays = {
                "หมุดระบุตำแหน่ง": markers,
                "แผนที่ความร้อน (Heatmap)": heatLayer,
                "ขอบเขตเทศบาล": boundaryLayer,
                "เส้นทางถนน": roadLayer
            };
            L.control.layers(baseLayers, overlays, { collapsed: true, position: 'topright' }).addTo(mymap);

            if (approvedSigns.length > 0) {
                mymap.setView([approvedSigns[0].lat, approvedSigns[0].lng], 14);
            }

            // Load Boundaries
            fetch('../data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { weight: 3, opacity: 1, color: '#dc2626', fillOpacity: 0.05, fillColor: '#dc2626' }
                    }).addTo(boundaryLayer);
                });
            boundaryLayer.addTo(mymap);

            // Load Roads
            fetch('../data/road_sila.geojson')
                .then(res => res.json())
                .then(roads => {
                    L.geoJSON(roads, {
                        style: { color: '#f59e0b', weight: 4, opacity: 0.6 }
                    }).addTo(roadLayer);
                });
            roadLayer.addTo(mymap);

            // Table Rendering
            var pageSizeEl = document.getElementById('pageSize');
            var searchEl = document.getElementById('searchInput');
            var tbody = document.getElementById('tableBody');
            var pageInfo = document.getElementById('pageInfo');
            var prevBtn = document.getElementById('prevBtn');
            var nextBtn = document.getElementById('nextBtn');
            var page = 1;

            function filtered() {
                var q = (searchEl.value || '').toLowerCase();
                if (!q) return approvedList;
                return approvedList.filter(function (r) {
                    return (r.type || '').toLowerCase().includes(q)
                        || (r.name || '').toLowerCase().includes(q)
                        || (r.address || '').toLowerCase().includes(q)
                        || (r.desc || '').toLowerCase().includes(q)
                        || (String(r.id)).includes(q);
                });
            }

            function render() {
                var size = parseInt(pageSizeEl.value, 10);
                var rows = filtered();
                var totalPages = Math.max(1, Math.ceil(rows.length / size));
                if (page > totalPages) page = totalPages;
                var start = (page - 1) * size;
                var slice = rows.slice(start, start + size);

                tbody.innerHTML = slice.map(function (r) {
                    return "<tr onclick='zoomToMarker(" + r.id + ", " + r.lat + ", " + r.lng + ")'>"
                        + "<td class='fw-bold text-primary'>#" + r.id + "</td>"
                        + "<td>" + r.type + "</td>"
                        + "<td>" + (r.desc || '-') + "</td>"
                        + "<td>" + (r.duration || 0) + " วัน</td>"
                        + "<td>" + r.name + "</td>"
                        + "</tr>";
                }).join('');

                pageInfo.textContent = "กำลังแสดง " + (start + 1) + " ถึง " + Math.min(start + size, rows.length) + " จากทั้งหมด " + rows.length + " รายการ";
                prevBtn.disabled = page <= 1;
                nextBtn.disabled = page >= totalPages;
            }

            window.zoomToMarker = function (id, lat, lng) {
                if (!lat || !lng) return;

                // Fly to the coordinates
                mymap.flyTo([lat, lng], 18, {
                    animate: true,
                    duration: 1.5
                });

                // Open popup if marker exists
                if (markerDict[id]) {
                    // Marker clusters might need adjustment to show the individual marker
                    markers.zoomToShowLayer(markerDict[id], function () {
                        markerDict[id].openPopup();
                    });
                }
            };

            pageSizeEl.addEventListener('change', function () { page = 1; render(); });
            searchEl.addEventListener('input', function () { page = 1; render(); });
            prevBtn.addEventListener('click', function () { if (page > 1) { page--; render(); } });
            nextBtn.addEventListener('click', function () {
                var size = parseInt(pageSizeEl.value, 10);
                var rows = filtered();
                var totalPages = Math.max(1, Math.ceil(rows.length / size));
                if (page < totalPages) { page++; render(); }
            });
            render();
        });
    </script>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>
⚓,Complexity:2,Description: