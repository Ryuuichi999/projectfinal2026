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

// ดึงข้อมูลคำร้องที่มีพิกัด — ทุกสถานะยกเว้นยกเลิก
$all_signs = [];
$result_signs = $conn->query("SELECT id, request_no, location_lat, location_lng, sign_type, road_name, status, end_date 
    FROM sign_requests 
    WHERE status != 'cancelled_payment' 
    AND location_lat IS NOT NULL AND location_lng IS NOT NULL
    ORDER BY id DESC");
if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        $all_signs[] = [
            'id' => (int) $row['id'],
            'request_no' => htmlspecialchars($row['request_no'] ?: ('req' . $row['id'])),
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type']),
            'road' => htmlspecialchars($row['road_name'] ?? ''),
            'status' => $row['status']
        ];
    }
}

$all_rows = [];
$res_rows = $conn->query("SELECT r.id, r.request_no, r.sign_type, r.road_name, r.description, r.duration_days, r.end_date, r.permit_no, r.status, u.title_name, u.first_name, u.last_name 
                          FROM sign_requests r 
                          JOIN users u ON r.user_id = u.id 
                          WHERE r.status != 'cancelled_payment' 
                          AND r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                          ORDER BY r.id DESC LIMIT 1000");
if ($res_rows && $res_rows->num_rows > 0) {
    while ($row = $res_rows->fetch_assoc()) {
        $expire_str = !empty($row['end_date']) ? date('d/m/Y', strtotime($row['end_date'])) : '';
        $all_rows[] = [
            'id' => (int) $row['id'],
            'request_no' => htmlspecialchars($row['request_no'] ?: ('req' . $row['id'])),
            'type' => htmlspecialchars($row['sign_type']),
            'desc' => htmlspecialchars($row['description'] ?? ''),
            'duration' => (int) ($row['duration_days'] ?? 0),
            'name' => htmlspecialchars(($row['title_name'] ?? '') . $row['first_name'] . ' ' . $row['last_name']),
            'road' => htmlspecialchars($row['road_name'] ?? ''),
            'permit_no' => htmlspecialchars($row['permit_no'] ?? '-'),
            'expire' => $expire_str,
            'status' => $row['status']
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

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        .map-section-header {
            margin-bottom: 25px;
        }

        #mapid {
            height: 450px;
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
                <p class="text-muted mb-3">แสดงตำแหน่งคำร้องทุกสถานะในเขต ทม.ศิลา (ยกเว้นยกเลิก)</p>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div id="mapid"></div>
                </div>

                <div class="col-12 mt-5">
                    <div class="list-card overflow-hidden">
                        <div class="p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2 bg-light">
                            <h5 class="mb-0 fw-bold">📋 รายการคำร้องบนแผนที่</h5>
                            <div class="d-flex align-items-center gap-2">
                                <select id="statusFilter" class="form-select form-select-sm" style="width:180px;">
                                    <option value="">ทุกสถานะ</option>
                                    <option value="pending">รอพิจารณา</option>
                                    <option value="reviewing">กำลังพิจารณา</option>
                                    <option value="need_documents">ขอเอกสารเพิ่ม</option>
                                    <option value="waiting_payment">รอชำระเงิน</option>
                                    <option value="waiting_permit">รอออกใบอนุญาต</option>
                                    <option value="waiting_receipt">รอออกใบเสร็จ</option>
                                    <option value="approved">อนุมัติแล้ว</option>
                                    <option value="rejected">ไม่อนุมัติ</option>
                                    <option value="expired">หมดอายุ</option>
                                </select>
                                <div class="input-group input-group-sm" style="width: 250px;">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                    <input id="searchInput" type="text" class="form-control border-start-0"
                                        placeholder="ค้นหา ชื่อ/ที่อยู่/ประเภท...">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="fixed-card-body p-0">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>เลขคำร้อง</th>
                                        <th>ประเภทป้าย</th>
                                        <th>ถนน</th>
                                        <th>ชื่อผู้ขอ</th>
                                        <th>สถานะ</th>
                                        <th>หมดอายุ</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="p-3 border-top d-flex justify-content-between align-items-center bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <select id="pageSize" class="form-select form-select-sm" style="width:70px;">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                            </select>

                            <div id="pageInfo" class="text-muted small"></div>
                        </div>
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
            var initialLat = 16.485;
            var initialLng = 102.835;
            var initialZoom = 13;

            // สีตามสถานะ
            var statusColors = {
                'pending': '#1d4ed8',
                'reviewing': '#0369a1',
                'need_documents': '#b45309',
                'waiting_payment': '#dc2626',
                'waiting_permit': '#7c3aed',
                'waiting_receipt': '#0d9488',
                'approved': '#16a34a',
                'rejected': '#6b7280',
                'expired': '#374151'
            };

            var statusLabels = {
                'pending': 'รอพิจารณา',
                'reviewing': 'กำลังพิจารณา',
                'need_documents': 'ขอเอกสารเพิ่ม',
                'waiting_payment': 'รอชำระเงิน',
                'waiting_permit': 'รอออกใบอนุญาต',
                'waiting_receipt': 'รอออกใบเสร็จ',
                'approved': 'อนุมัติแล้ว',
                'rejected': 'ไม่อนุมัติ',
                'expired': 'หมดอายุ'
            };

            if (typeof L === 'undefined') {
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

            var allSigns = <?php echo json_encode($all_signs); ?>;
            var allList = <?php echo json_encode($all_rows); ?>;
            var markerDict = {};

            var markers = L.markerClusterGroup();
            var heatLayer = L.heatLayer(allSigns.map(function(s) { return [s.lat, s.lng, 0.6]; }), { radius: 20, blur: 15 });

            allSigns.forEach(function (sign) {
                if (!sign.lat || !sign.lng) return;
                var color = statusColors[sign.status] || '#6b7280';
                var label = statusLabels[sign.status] || sign.status;

                var customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color:' + color + ';width:28px;height:28px;border-radius:8px;border:3px solid white;box-shadow:0 3px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-size:14px;">🪧</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

                var popupHtml = '<div style="font-family:Sarabun,sans-serif;min-width:180px;">'
                    + '<div style="font-weight:700;font-size:0.95rem;margin-bottom:6px;">เลขคำร้อง ' + sign.request_no + '</div>'
                    + '<div style="font-size:0.85rem;display:flex;flex-direction:column;gap:3px;">'
                    + '<div><b>ประเภท:</b> ' + sign.type + '</div>'
                    + '<div><b>ถนน:</b> ' + (sign.road || '-') + '</div>'
                    + '<div><b>สถานะ:</b> <span style="background:' + color + ';color:white;padding:2px 8px;border-radius:4px;font-size:0.78rem;">' + label + '</span></div>'
                    + '</div></div>';

                var m = L.marker([sign.lat, sign.lng], { icon: customIcon }).bindPopup(popupHtml);
                markers.addLayer(m);
                markerDict[sign.id] = m;
            });
            markers.addTo(mymap);

            // Legend Control (ซ้ายล่าง)
            var legend = L.control({ position: 'bottomleft' });
            legend.onAdd = function () {
                var div = L.DomUtil.create('div', 'map-legend');
                div.style.background = 'white';
                div.style.padding = '12px 16px';
                div.style.borderRadius = '8px';
                div.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                div.style.fontSize = '0.8rem';
                div.style.lineHeight = '1.8';
                var html = '<div style="font-weight:700;margin-bottom:4px;">สัญลักษณ์สถานะ</div>';
                for (var key in statusLabels) {
                    html += '<div style="display:flex;align-items:center;gap:8px;">'
                        + '<div style="width:14px;height:14px;background:' + statusColors[key] + ';border-radius:4px;flex-shrink:0;"></div>'
                        + '<span>' + statusLabels[key] + '</span></div>';
                }
                html += '<hr style="margin:6px 0;">';
                html += '<div style="display:flex;align-items:center;gap:8px;"><div style="width:14px;height:3px;background:#dc2626;border-radius:2px;"></div><span>ขอบเขตเทศบาล</span></div>';
                html += '<div style="display:flex;align-items:center;gap:8px;"><div style="width:14px;height:3px;background:#f59e0b;border-radius:2px;"></div><span>เส้นทางถนน</span></div>';
                div.innerHTML = html;
                return div;
            };
            legend.addTo(mymap);

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

            if (allSigns.length > 0) {
                var bounds = L.latLngBounds(allSigns.map(function(s) { return [s.lat, s.lng]; }));
                mymap.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
            }

            // Load Boundaries
            fetch('../data/sila.geojson')
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    L.geoJSON(data, {
                        style: { weight: 3, opacity: 1, color: '#dc2626', fillOpacity: 0.05, fillColor: '#dc2626' }
                    }).addTo(boundaryLayer);
                });
            boundaryLayer.addTo(mymap);

            // Load Roads
            fetch('../data/road_sila.geojson')
                .then(function(res) { return res.json(); })
                .then(function(roads) {
                    L.geoJSON(roads, {
                        style: { color: '#f59e0b', weight: 4, opacity: 0.6 }
                    }).addTo(roadLayer);
                });
            roadLayer.addTo(mymap);

            // === Table with search, status filter & pagination ===
            var pageSizeEl = document.getElementById('pageSize');
            var searchEl = document.getElementById('searchInput');
            var statusFilterEl = document.getElementById('statusFilter');
            var tbody = document.getElementById('tableBody');
            var pageInfoEl = document.getElementById('pageInfo');
            var prevBtn = document.getElementById('prevBtn');
            var nextBtn = document.getElementById('nextBtn');
            var page = 1;

            function filtered() {
                var q = (searchEl.value || '').toLowerCase();
                var sf = statusFilterEl.value;
                return allList.filter(function (r) {
                    if (sf && r.status !== sf) return false;
                    if (!q) return true;
                    return (r.request_no || '').toLowerCase().includes(q)
                        || (r.type || '').toLowerCase().includes(q)
                        || (r.name || '').toLowerCase().includes(q)
                        || (r.road || '').toLowerCase().includes(q)
                        || (r.desc || '').toLowerCase().includes(q)
                        || (r.permit_no || '').toLowerCase().includes(q)
                        || (String(r.id)).includes(q);
                });
            }

            function getStatusBadge(status) {
                var color = statusColors[status] || '#6b7280';
                var label = statusLabels[status] || status;
                return '<span style="display:inline-flex;align-items:center;gap:4px;background:' + color + '20;color:' + color + ';font-size:.78rem;font-weight:600;padding:3px 10px;border-radius:6px;white-space:nowrap;">' + label + '</span>';
            }

            function render() {
                var size = parseInt(pageSizeEl.value, 10);
                var rows = filtered();
                var totalPages = Math.max(1, Math.ceil(rows.length / size));
                if (page > totalPages) page = totalPages;
                var start = (page - 1) * size;
                var slice = rows.slice(start, start + size);

                tbody.innerHTML = slice.map(function (r) {
                    return "<tr onclick='zoomToSign(" + r.id + ")'>"
                        + "<td class='fw-bold text-primary'>" + (r.request_no || ('req' + r.id)) + "</td>"
                        + "<td>" + r.type + "</td>"
                        + "<td>" + (r.road || '-') + "</td>"
                        + "<td>" + (r.name || '-') + "</td>"
                        + "<td>" + getStatusBadge(r.status) + "</td>"
                        + "<td>" + (r.expire || '-') + "</td>"
                        + "</tr>";
                }).join('');

                pageInfoEl.textContent = "กำลังแสดง " + (start + 1) + " ถึง " + Math.min(start + size, rows.length) + " จากทั้งหมด " + rows.length + " รายการ";
                prevBtn.disabled = page <= 1;
                nextBtn.disabled = page >= totalPages;
            }

           window.zoomToSign = function (id) {
    if (markerDict[id]) {
        var latlng = markerDict[id].getLatLng();

        mymap.flyTo(latlng, 16, { duration: 1.0 });

        setTimeout(function () {
            markers.zoomToShowLayer(markerDict[id], function () {
                markerDict[id].openPopup();
            });
        }, 350);
    }
};

            pageSizeEl.addEventListener('change', function () { page = 1; render(); });
            searchEl.addEventListener('input', function () { page = 1; render(); });
            statusFilterEl.addEventListener('change', function () { page = 1; render(); });
            prevBtn.addEventListener('click', function () { if (page > 1) { page--; render(); } });
            nextBtn.addEventListener('click', function () {
                var size = parseInt(pageSizeEl.value, 10);
                var totalPages = Math.max(1, Math.ceil(filtered().length / size));
                if (page < totalPages) { page++; render(); }
            });
            render();
        });
    </script>

    <?php include '../includes/scripts.php'; ?>
</body>

</html>