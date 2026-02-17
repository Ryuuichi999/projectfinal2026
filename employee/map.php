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
    $stmt_rows = $conn->prepare("SELECT r.id, r.sign_type, r.receipt_date, r.description, r.duration_days, u.title_name, u.first_name, u.last_name, u.address, u.phone 
                                 FROM sign_requests r 
                                 JOIN users u ON r.user_id = u.id 
                                 WHERE r.user_id = ? AND r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                                 ORDER BY r.id DESC LIMIT 1000");
    $stmt_rows->bind_param("i", $userId);
    $stmt_rows->execute();
    $res_rows = $stmt_rows->get_result();
} else {
    $res_rows = $conn->query("SELECT r.id, r.sign_type, r.receipt_date, r.description, r.duration_days, u.title_name, u.first_name, u.last_name, u.address, u.phone 
                              FROM sign_requests r 
                              JOIN users u ON r.user_id = u.id 
                              WHERE r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
                              ORDER BY r.id DESC LIMIT 1000");
}
if ($res_rows && $res_rows->num_rows > 0) {
    while ($row = $res_rows->fetch_assoc()) {
        $approved_rows[] = [
            'id' => (int) $row['id'],
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
    <title>แผนที่ GIS</title>

    <?php include '../includes/header.php'; ?>

    <link rel="stylesheet" href="assets/css/style.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        .map-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        #mapid {
            height: 520px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .fixed-card {
            height: 520px;
            display: flex;
            flex-direction: column;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .fixed-card-body {
            flex: 1 1 auto;
            overflow: hidden;
        }

        .table-wrap {
            height: 100%;
            overflow: auto;
        }

        .table-page {
            height: 100%;
            overflow-y: auto;
        }

        .map-container {
            position: relative;
        }

        .table {
            min-width: 500px;
            font-size: 13px; /* Slightly smaller font */
        }

        .table th,
        .table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
        }

        .table th {
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.025em;
            background-color: #f8fafc;
        }

        .table-type {
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-desc {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-name {
            max-width: 110px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .full-height-card {
            min-height: calc(100vh - 120px);
            padding: 24px !important;
        }
        
        .badge {
            font-weight: 500;
            padding: 6px 10px;
            font-size: 12px;
        }
        
        /* Adjust layout for smaller scale at 100% */
        .content {
            padding: 20px 30px;
        }
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content fade-in-up">
        <div class="card fade-in-up full-height-card">
            <div class="map-section-header">
                <div>
                    <h2 class="mb-1" style="font-size: 1.5rem;">🗺️ แผนที่ข้อมูลพื้นที่ (GIS)</h2>
                    <p class="text-muted mb-0 small">แสดงขอบเขต ตำแหน่งป้าย และเส้นทางถนนในเขต ทม.ศิลา</p>
                </div>
                <div class="d-flex align-items-center gap-1 flex-wrap justify-content-end" style="max-width: 500px;">
                    <span class="badge" style="background-color: #16a34a; color: white;">อนุมัติแล้ว</span>
                    <span class="badge" style="background-color: #f59e0b; color: white;">รอดำเนินการ</span>
                    <span class="badge" style="background-color: #3b82f6; color: white;">รอชำระเงิน</span>
                    <span class="badge" style="background-color: #8b5cf6; color: white;">รอใบเสร็จ</span>
                    <span class="badge" style="background-color: #dc2626; color: white;">ไม่อนุมัติ</span>
                    <span class="badge" style="background-color: #6b7280; color: white;">ยกเลิก</span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div id="mapid"></div>
                </div>
                <div class="col-md-6">
                    <div class="card fixed-card p-0">
                        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-light" style="border-radius: 12px 12px 0 0;">
                            <h6 class="mb-0 fw-bold">รายการคำร้องบนแผนที่</h6>
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted mb-0">แสดง</label>
                                <select id="pageSize" class="form-select form-select-sm w-auto" style="font-size: 12px;">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                </select>
                            </div>
                        </div>
                        <div class="px-3 py-2 border-bottom bg-white">
                             <input id="searchInput" type="text" class="form-control form-control-sm"
                                        placeholder="🔍 ค้นหา ชื่อ/ที่อยู่/ประเภท..." style="font-size: 12px;">
                        </div>
                        <div class="fixed-card-body p-0">
                            <div class="table-wrap">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>เลขคำขอ</th>
                                            <th>ประเภท</th>
                                            <th>รายละเอียด</th>
                                            <th>ระยะเวลา</th>
                                            <th>ชื่อ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody" class="table-page"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="p-2 border-top d-flex justify-content-between align-items-center bg-light" style="border-radius: 0 0 12px 12px;">
                            <div id="pageInfo" class="text-muted" style="font-size: 11px;"></div>
                            <div class="btn-group">
                                <button id="prevBtn" class="btn btn-outline-secondary btn-sm" style="padding: 2px 8px;"><i class="bi bi-chevron-left"></i></button>
                                <button id="nextBtn" class="btn btn-outline-secondary btn-sm" style="padding: 2px 8px;"><i class="bi bi-chevron-right"></i></button>
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
            const initialLat = 16.485;
            const initialLng = 102.835;
            const initialZoom = 12;

            if (typeof L === 'undefined') {
                console.error("Leaflet library (L) failed to load.");
                document.getElementById('mapid').innerHTML = '<div class="alert alert-danger">ไม่สามารถโหลดแผนที่ได้</div>';
                return;
            }

            var mymap = L.map('mapid', { zoomControl: true }).setView([initialLat, initialLng], initialZoom);

            var baseStyle = L.tileLayer('https://api.maptiler.com/maps/base-v4/{z}/{x}/{y}.png?key=<?php echo MAPTILER_API_KEY; ?>', {
                maxZoom: 20,
                attribution: '&copy; MapTiler'
            }).addTo(mymap);

            var approvedSigns = <?php echo json_encode($approved_signs); ?>;
            var approvedList = <?php echo json_encode($approved_rows); ?>;

            var markers = L.markerClusterGroup();
            var heat = L.heatLayer(approvedSigns.map(function (s) { return [s.lat, s.lng, 0.6]; }), { radius: 20, blur: 15 });
            
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
                        html: '<div style="background-color: ' + markerColor + '; width: 22px; height: 22px; border-radius: 5px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-size: 10px;">🪧</div>',
                        iconSize: [22, 22],
                        iconAnchor: [11, 11]
                    });
                    
                    var m = L.marker([sign.lat, sign.lng], {icon: customIcon})
                        .bindPopup("<b>เลขคำขอ #" + sign.id + "</b><br>ประเภท: " + sign.type + "<br>สถานะ: " + statusText);
                    markers.addLayer(m);
                }
            });
            markers.addTo(mymap);

            if (approvedSigns.length > 0) {
                mymap.setView([approvedSigns[0].lat, approvedSigns[0].lng], 13);
            }

            fetch('data/sila.geojson')
                .then(res => res.json())
                .then(data => {
                    L.geoJSON(data, {
                        style: { weight: 2, opacity: 1, color: '#dc2626', fillOpacity: 0.05, fillColor: '#dc2626' }
                    }).addTo(mymap);
                });

            fetch('data/road_sila.geojson')
                .then(res => res.json())
                .then(roads => {
                    L.geoJSON(roads, { style: { color: '#f59e0b', weight: 2 } }).addTo(mymap);
                });

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
                    return "<tr>"
                        + "<td class='fw-bold'>#" + r.id + "</td>"
                        + "<td class='table-type'>" + r.type + "</td>"
                        + "<td class='table-desc'>" + r.desc + "</td>"
                        + "<td>" + (r.duration || 0) + " วัน</td>"
                        + "<td class='table-name'>" + r.name + "</td>"
                        + "</tr>";
                }).join('');
                pageInfo.textContent = "หน้า " + page + " / " + totalPages + " (รวม " + rows.length + ")";
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

    <?php include '../includes/scripts.php'; ?>
</body>

</html>
⚓,Complexity:2,Description: