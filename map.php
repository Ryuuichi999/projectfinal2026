<?php
// สมมติว่าไฟล์ map.php อยู่ในรูทของ Projectป้าย/
require './includes/db.php';

// User GIS map - require login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

// กำหนดบทบาทและผู้ใช้ปัจจุบัน
$role = $_SESSION['role'] ?? 'guest';
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// ดึงข้อมูลคำร้องที่มีพิกัดและสถานะ เพื่อแสดงบนแผนที่
// แสดงเฉพาะคำร้องของ user เอง ที่ได้รับอนุมัติแล้ว และยังไม่หมดอายุ
$approved_signs = [];
$stmt = $conn->prepare("SELECT id, request_no, location_lat, location_lng, sign_type, road_name, status, end_date 
                         FROM sign_requests 
                         WHERE user_id = ? 
                         AND status = 'approved' 
                         AND location_lat IS NOT NULL AND location_lng IS NOT NULL");
$stmt->bind_param("i", $userId);

$stmt->execute();
$result_signs = $stmt->get_result();
if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        // กรองป้ายที่หมดอายุแล้วออก
        if (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d'))
            continue;
        $approved_signs[] = [
            'id' => (int) $row['id'],
            'request_no' => htmlspecialchars($row['request_no'] ?: ('req' . $row['id'])),
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type']),
            'road' => htmlspecialchars($row['road_name'] ?? ''),
            'status' => htmlspecialchars($row['status'])

        ];
    }
}

$approved_rows = [];
$stmt_rows = $conn->prepare("SELECT r.id, r.request_no, r.sign_type, r.road_name, r.description, r.duration_days, r.end_date, r.permit_no
                             FROM sign_requests r 
                             WHERE r.user_id = ? 
                             AND r.status = 'approved'
                             AND r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                             ORDER BY r.id DESC LIMIT 1000");

$stmt_rows->bind_param("i", $userId);
$stmt_rows->execute();
$res_rows = $stmt_rows->get_result();
if ($res_rows && $res_rows->num_rows > 0) {
    while ($row = $res_rows->fetch_assoc()) {
        // กรองป้ายที่หมดอายุแล้วออก
        if (!empty($row['end_date']) && $row['end_date'] < date('Y-m-d'))
            continue;
        $expire_str = !empty($row['end_date']) ? date('d/m/Y', strtotime($row['end_date'])) : '';
        $approved_rows[] = [
            'id' => (int) $row['id'],
            'request_no' => htmlspecialchars($row['request_no'] ?: ('req' . $row['id'])),
            'type' => htmlspecialchars($row['sign_type']),
            'road' => htmlspecialchars($row['road_name'] ?? ''),
            'desc' => htmlspecialchars($row['description'] ?? ''),
            'duration' => (int) ($row['duration_days'] ?? 0),
            'permit_no' => htmlspecialchars($row['permit_no'] ?? '-'),

            'expire' => $expire_str
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แผนที่ GIS</title>

    <?php include './includes/header.php'; ?>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        #mapid {
            height: 480px;
            width: 100%;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }

        .fixed-card {
            height: 480px;
            display: flex;
            flex-direction: column;
        }

        .fixed-card-body {
            flex: 1 1 auto;
            overflow: hidden;
        }

        .table-wrap {
            height: 400px;
            overflow: auto;
            margin-top: 6px;
        }

        .table-page {
            height: 100%;
            overflow-y: auto;
        }

        .map-container {
            position: relative;
        }

        .table {
            min-width: 540px;
            font-size: 11px;
        }

        .table th,
        .table td {
            padding: .2rem .45rem;
        }

        .table-type {
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-desc {
            max-width: 160px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-name {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .full-height-card {
            min-height: calc(100vh - 140px);
        }
    </style>
</head>

<body>

    <?php include './includes/user_navbar.php'; ?>

    <div class="container-fluid px-md-5 fade-in-up mt-4">
        <div class="card p-4 fade-in-up full-height-card">
            <h2 class="mb-2">🗺️ แผนที่ข้อมูลพื้นที่ (GIS)</h2>
            <p class="text-muted mb-4">แสดงตำแหน่งป้ายของคุณที่ได้รับอนุมัติแล้ว ในเขต ทม.ศิลา</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="map-container">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background-color: #16a34a; color: white;">🪧
                                ป้ายที่อนุมัติแล้ว</span>
                            <small class="text-muted">แสดงเฉพาะคำร้องของคุณที่ได้รับอนุมัติและยังไม่หมดอายุ</small>
                        </div>
                        <div id="mapid"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card fixed-card">
                        <div class="p-2 border-bottom">
                            <h6 class="mb-0">รายการคำร้องบนแผนที่</h6>
                            <div class="d-flex align-items-center gap-2">
                                <div class="ms-auto d-flex align-items-center gap-2">
                                    <label class="text-muted">ค้นหา</label>
                                    <input id="searchInput" type="text" class="form-control form-control-sm"
                                        placeholder="ประเภท/ถนน">
                                </div>
                            </div>
                        </div>
                        <div class="fixed-card-body p-0">
                            <div class="table-wrap">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>เลขคำร้อง</th>
                                            <th>ประเภท</th>
                                            <th>ถนน</th>
                                            <th>ระยะเวลา</th>
                                            <th>หมดอายุ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody" class="table-page"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="p-2 border-top d-flex align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <select id="pageSize" class="form-select form-select-sm" style="width:70px;">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                </select>
                                <div id="pageInfo" class="small text-muted"></div>
                                <div class="btn-group">
                                    <button id="prevBtn" class="btn btn-outline-secondary btn-sm"><i
                                            class="bi bi-chevron-left"></i></button>
                                    <button id="nextBtn" class="btn btn-outline-secondary btn-sm"><i
                                            class="bi bi-chevron-right"></i></button>
                                </div>
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
                // โค้ดจะทำงานได้เมื่อ DOM และ Leaflet JS โหลดเสร็จแล้ว

                const initialLat = 16.485;
                const initialLng = 102.835;
                const initialZoom = 12;

                // ตรวจสอบว่า Leaflet โหลดสำเร็จหรือไม่
                if (typeof L === 'undefined') {
                    console.error("Leaflet library (L) failed to load.");
                    document.getElementById('mapid').innerHTML = '<div class="alert alert-danger">ไม่สามารถโหลดแผนที่ได้ โปรดตรวจสอบการเชื่อมต่ออินเทอร์เน็ตหรือ CDN Links.</div>';
                    return;
                }

                var mymap = L.map('mapid', { zoomControl: true }).setView([initialLat, initialLng], initialZoom);

                var baseStyle = L.tileLayer('https://api.maptiler.com/maps/base-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                    maxZoom: 20,
                    attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>'
                }).addTo(mymap);

                var datavizStyle = L.tileLayer('https://api.maptiler.com/maps/dataviz-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                    maxZoom: 20,
                    attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>'
                });

                // *** 4. การแสดงตำแหน่งป้ายที่อนุมัติแล้วจากฐานข้อมูล ***
                var approvedSigns = <?php echo json_encode($approved_signs); ?>;
                var approvedList = <?php echo json_encode($approved_rows); ?>;

                var markers = L.markerClusterGroup();
                var heat = L.heatLayer(approvedSigns.map(function (s) { return [s.lat, s.lng, 0.6]; }), { radius: 20, blur: 15 });
                var baseLayers = {
                    "แผนที่หลัก": baseStyle,
                    "แผนที่ Dataviz": datavizStyle
                };
                var overlays = { "แผนที่ความร้อน": heat, "หมุดตำแหน่งป้าย": markers };
                var layerControl = L.control.layers(baseLayers, overlays, { collapsed: true, position: 'topright' }).addTo(mymap);
                approvedSigns.forEach(function (sign) {
                    if (sign.lat && sign.lng) {
                        var markerColor = '#16a34a'; // สีเขียว (อนุมัติแล้ว)

                        var customIcon = L.divIcon({
                            className: 'custom-marker',
                            html: '<div style="background-color: ' + markerColor + '; width: 24px; height: 24px; border-radius: 4px; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; font-size: 12px;">🪧</div>',
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        });

                        var m = L.marker([sign.lat, sign.lng], { icon: customIcon })
                            .bindPopup("<b>เลขคำร้อง " + sign.request_no + "</b><br><b>ประเภทป้าย:</b> " + sign.type + "<br><b>ถนน:</b> " + (sign.road || '-') + "<br><b>สถานะ:</b> ✅ อนุมัติแล้ว");
                        markers.addLayer(m);
                    }
                });
                markers.addTo(mymap);

                // หากมีป้ายที่อนุมัติแล้วอย่างน้อย 1 ป้าย ให้ปรับมุมมองแผนที่ไปยังป้ายนั้น
                if (approvedSigns.length > 0) {
                    mymap.setView([approvedSigns[0].lat, approvedSigns[0].lng], 13);
                }

                // // ตัวอย่าง Marker ตำแหน่ง ทม.ศิลา
                // L.marker([16.480, 102.830])
                //     .addTo(mymap)
                //     .bindPopup("เทศบาลเมืองศิลา (ทม.ศิลา)");

                // *** 5. การแสดงขอบเขตพื้นที่จาก GeoJSON ***

                // กำหนดพาธไปยังไฟล์ GeoJSON ของคุณ (เนื่องจาก map.php อยู่ในรูท และไฟล์อยู่ใน data/)
                const geojsonPath = 'data/sila.geojson';
                var boundaryLayer = null;
                heat.addTo(mymap);

                fetch(geojsonPath)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Failed to load GeoJSON: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(geojson_data => {
                        boundaryLayer = L.geoJSON(geojson_data, {
                            style: function (feature) {
                                return {
                                    weight: 3,             // ความหนาของเส้นขอบ
                                    opacity: 1,
                                    color: '#dc2626',     // สีแดงสำหรับเขตเทศบาล
                                    fillOpacity: 0.1,      // ความโปร่งใสของสีเติม
                                    fillColor: '#dc2626'   // สีเติมแดงอ่อนๆ
                                };
                            },
                            onEachFeature: function (feature, layer) {
                                // เพิ่ม Popup เพื่อแสดงข้อมูล (เช่น ชื่อ) เมื่อคลิก
                                if (feature.properties && feature.properties.T_NAME_T) {
                                    layer.bindPopup("<b>ขอบเขต:</b> " + feature.properties.T_NAME_T);
                                } else if (feature.properties && feature.properties.T_NAME_E) {
                                    layer.bindPopup("<b>Boundary:</b> " + feature.properties.T_NAME_E);
                                }
                            }
                        }).addTo(mymap);
                        layerControl.addOverlay(boundaryLayer, "ขอบเขตเทศบาล");

                        console.log("GeoJSON loaded successfully:", geojson_data);

                        // **ตัวเลือก:** หากคุณต้องการให้แผนที่ซูมไปยังขอบเขตของ GeoJSON อัตโนมัติ
                        // L.geoJSON(geojson_data).addTo(mymap).getBounds().isValid() && mymap.fitBounds(L.geoJSON(geojson_data).getBounds());

                    })
                    .catch(error => {
                        console.error("Error loading GeoJSON data:", error);
                    });

                var roadLayer = null;
                fetch('data/road_sila.geojson')
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error(response.statusText);
                        }
                        return response.json();
                    })
                    .then(function (roads) {
                        roadLayer = L.geoJSON(roads, {
                            style: { color: '#f59e0b', weight: 3 }
                        }).addTo(mymap);
                        layerControl.addOverlay(roadLayer, "เส้นทางถนน");
                    })
                    .catch(function (err) {
                        console.error(err);
                    });

                mymap.on('zoomend', function () {
                    var z = mymap.getZoom();
                    if (z < 13) {
                        if (!mymap.hasLayer(heat)) heat.addTo(mymap);
                    } else {
                        if (mymap.hasLayer(heat)) mymap.removeLayer(heat);
                    }
                });

                // ใช้ปุ่มเลเยอร์มาตรฐานของ Leaflet (collapsed) แทน offcanvas

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
                        return (r.request_no || '').toLowerCase().includes(q)
                            || (r.type || '').toLowerCase().includes(q)
                            || (r.road || '').toLowerCase().includes(q)
                            || (r.desc || '').toLowerCase().includes(q)
                            || (r.permit_no || '').toLowerCase().includes(q)
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
                        var d = (r.duration || 0) + " วัน";
                        return "<tr>"
                            + "<td>" + (r.request_no || ('req' + r.id)) + "</td>"
                            + "<td class='table-type'>" + r.type + "</td>"
                            + "<td class='table-desc'>" + (r.road || '-') + "</td>"
                            + "<td>" + d + "</td>"
                            + "<td>" + (r.expire || '-') + "</td>"

                            + "</tr>";
                    }).join('');
                    pageInfo.textContent = "หน้า " + page + " / " + totalPages + " • ทั้งหมด " + rows.length + " รายการ";
                    prevBtn.disabled = page <= 1;
                    nextBtn.disabled = page >= totalPages;
                }
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

        <?php include './includes/scripts.php'; ?>
</body>

</html>