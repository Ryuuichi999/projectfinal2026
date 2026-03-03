<?php
require './includes/db.php';

// Public GIS map - เฉพาะป้ายที่อนุมัติแล้ว (ไม่แสดงข้อมูลส่วนบุคคล)

$approved_signs = [];
$approved_rows = [];

// ดึงเฉพาะคำขอที่อนุมัติแล้ว + มีพิกัด
$result_signs = $conn->query("SELECT r.id, r.location_lat, r.location_lng, r.sign_type, r.permit_no, r.road_name, r.width, r.height, r.quantity, r.duration_days, r.permit_date, r.created_at
    FROM sign_requests r
    WHERE r.status = 'approved' AND r.location_lat IS NOT NULL AND r.location_lng IS NOT NULL
    ORDER BY r.id DESC");

if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        // กรองป้ายที่หมดอายุแล้วออก
        $base = !empty($row['permit_date']) ? $row['permit_date'] : ($row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : null);
        $dur = (int)($row['duration_days'] ?? 0);
        if ($base && $dur > 0) {
            $expire = date('Y-m-d', strtotime($base . " + {$dur} days"));
            if ($expire < date('Y-m-d')) continue;
        }
        $approved_signs[] = [
            'id' => (int) $row['id'],
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type']),
            'permit_no' => htmlspecialchars($row['permit_no'] ?? '-'),
            'road' => htmlspecialchars($row['road_name'] ?? '-'),
            'size' => $row['width'] . 'x' . $row['height'] . ' ม.',
            'qty' => (int) $row['quantity'],
            'duration' => (int) ($row['duration_days'] ?? 0),
            'permit_date' => $row['permit_date'] ? date('d/m/Y', strtotime($row['permit_date'])) : '-'
        ];
        $approved_rows[] = end($approved_signs);
    }
}

$total_signs = count($approved_signs);
$unique_types = count(array_unique(array_column($approved_signs, 'type')));
$unique_roads = count(array_filter(array_unique(array_column($approved_signs, 'road')), function($r) { return $r !== '-'; }));

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผนที่ป้ายโฆษณา - เทศบาลเมืองศิลา</title>

    <?php include './includes/header.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <style>
        .page-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
            color: white;
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 24px;
        }
        .page-header h2 { font-weight: 700; margin-bottom: 4px; }
        .page-header p { opacity: 0.85; margin-bottom: 0; font-size: 0.95rem; }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: #1a202c; }
        .stat-label { font-size: 0.8rem; color: #64748b; }

        #mapid {
            height: 520px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .search-box {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            margin-bottom: 16px;
        }
        .search-box .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px 10px 40px;
            font-size: 0.95rem;
        }
        .search-box .form-control:focus {
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
            border-color: #2563eb;
        }
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .list-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .list-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .list-card-header h6 { margin: 0; font-weight: 700; color: #1a202c; }

        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .table { font-size: 0.85rem; margin-bottom: 0; }
        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            position: sticky;
            top: 0;
            z-index: 1;
            border-bottom: 2px solid #e2e8f0;
        }
        .table th, .table td { padding: 10px 14px; vertical-align: middle; }
        .table tbody tr { cursor: pointer; transition: background 0.15s; }
        .table tbody tr:hover { background: #f0f7ff; }

        .permit-badge {
            background: #f0fdf4;
            color: #15803d;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .road-cell {
            max-width: 140px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-footer {
            padding: 10px 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafbfc;
        }
        .table-footer .btn { border-radius: 8px; }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .no-results i { font-size: 2.5rem; margin-bottom: 8px; display: block; }
    </style>
</head>

<body>
    <?php include './includes/navbar.php'; ?>

    <div class="container-fluid px-md-4 px-lg-5 fade-in-up mt-4 mb-5">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2><i class="bi bi-geo-alt-fill me-2"></i>แผนที่ป้ายโฆษณา</h2>
                    <p>แสดงตำแหน่งป้ายโฆษณาที่ได้รับอนุญาตในเขตเทศบาลเมืองศิลา</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-white text-dark px-3 py-2 fw-semibold" style="font-size:0.85rem;">
                        <i class="bi bi-signpost-2 me-1 text-success"></i> <?= $total_signs ?> ป้ายที่อนุมัติ
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="bi bi-patch-check-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $total_signs ?></div>
                        <div class="stat-label">ป้ายที่อนุมัติในเขตเทศบาล</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fdf2f8; color:#db2777;"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <div class="stat-value">PDPA</div>
                        <div class="stat-label">คุ้มครองข้อมูลส่วนบุคคล</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div id="mapid"></div>
                <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                    <span class="small text-muted me-1"><i class="bi bi-circle-fill" style="color:#16a34a; font-size:8px;"></i> ป้ายที่อนุมัติ</span>
                    <span class="small text-muted me-1"><i class="bi bi-circle-fill" style="color:#dc2626; font-size:8px;"></i> ขอบเขตเทศบาล</span>
                    <span class="small text-muted"><i class="bi bi-circle-fill" style="color:#f59e0b; font-size:8px;"></i> เส้นทางถนน</span>
                </div>
            </div>

            <!-- Table Panel -->
            <div class="col-lg-5">
                <!-- Search -->
                <div class="search-box">
                    <div class="position-relative">
                        <i class="bi bi-search search-icon"></i>
                        <input id="searchInput" type="text" class="form-control"
                            placeholder="ค้นหาเลขคำขอ, ประเภทป้าย, ถนน...">
                    </div>
                </div>

                <!-- List -->
                <div class="list-card">
                    <div class="list-card-header">
                        <h6><i class="bi bi-list-ul me-2"></i>รายการป้ายที่อนุมัติ</h6>
                        <select id="pageSize" class="form-select form-select-sm" style="width:auto;">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                        </select>
                    </div>
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>คำร้อง</th>
                                    <th>ประเภทป้าย</th>
                                    <th>ถนน/สถานที่</th>
                                    <th>ขนาด</th>
                                    <th>ระยะเวลา</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody"></tbody>
                        </table>
                        <div id="noResults" class="no-results d-none">
                            <i class="bi bi-search"></i>
                            <div class="fw-semibold">ไม่พบข้อมูลที่ค้นหา</div>
                            <div class="small">ลองค้นหาด้วยคำอื่น</div>
                        </div>
                    </div>
                    <div class="table-footer">
                        <div id="pageInfo" class="small text-muted"></div>
                        <div class="btn-group">
                            <button id="prevBtn" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i></button>
                            <button id="nextBtn" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PDPA Notice -->
    <div class="text-center mt-4">
        <p class="text-muted small mb-0">
            <i class="bi bi-shield-check me-1"></i>
            ข้อมูลที่แสดงบนหน้านี้ไม่มีข้อมูลส่วนบุคคลตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 |
            <a href="/Project2026/privacy_policy.php" class="text-decoration-none">นโยบายความเป็นส่วนตัว</a>
        </p>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof L === 'undefined') {
            document.getElementById('mapid').innerHTML = '<div class="alert alert-danger m-3">ไม่สามารถโหลดแผนที่ได้</div>';
            return;
        }

        var mymap = L.map('mapid', { zoomControl: true }).setView([16.485, 102.835], 12);

        var baseStyle = L.tileLayer('https://api.maptiler.com/maps/base-v4/{z}/{x}/{y}.png?key=<?= MAPTILER_API_KEY ?>', {
            maxZoom: 20,
            attribution: '&copy; MapTiler &copy; OpenStreetMap'
        }).addTo(mymap);

        var datavizStyle = L.tileLayer('https://api.maptiler.com/maps/dataviz-v4/{z}/{x}/{y}.png?key=<?= MAPTILER_API_KEY ?>', {
            maxZoom: 20,
            attribution: '&copy; MapTiler &copy; OpenStreetMap'
        });

        var approvedSigns = <?= json_encode($approved_signs) ?>;
        var approvedList = <?= json_encode($approved_rows) ?>;

        var markers = L.markerClusterGroup();
        var heat = L.heatLayer(approvedSigns.map(function(s) { return [s.lat, s.lng, 0.6]; }), { radius: 20, blur: 15 });

        var baseLayers = { "แผนที่หลัก": baseStyle, "แผนที่ Dataviz": datavizStyle };
        var overlays = { "แผนที่ความร้อน": heat, "หมุดตำแหน่งป้าย": markers };
        var layerControl = L.control.layers(baseLayers, overlays, { collapsed: true, position: 'topright' }).addTo(mymap);

        // สร้างหมุด — เฉพาะป้ายที่อนุมัติ (สีเขียว)
        approvedSigns.forEach(function(sign) {
            if (!sign.lat || !sign.lng) return;
            var customIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div style="background:#16a34a;width:26px;height:26px;border-radius:6px;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;font-size:13px;">🪧</div>',
                iconSize: [26, 26], iconAnchor: [13, 13]
            });

            var popupHtml = '<div style="min-width:180px;font-family:Sarabun,sans-serif;">'
                + '<div style="font-weight:700;font-size:0.95rem;color:#1a202c;margin-bottom:6px;">คำขอ #' + sign.id + '</div>'
                + '<div style="display:flex;flex-direction:column;gap:3px;font-size:0.85rem;">'
                + '<div><span style="color:#64748b;">ประเภท:</span> <b>' + sign.type + '</b></div>'
                + '<div><span style="color:#64748b;">ขนาด:</span> ' + sign.size + '</div>'
                + '<div><span style="color:#64748b;">ถนน:</span> ' + sign.road + '</div>'
                + '</div></div>';

            var m = L.marker([sign.lat, sign.lng], { icon: customIcon }).bindPopup(popupHtml);
            markers.addLayer(m);
        });
        markers.addTo(mymap);
        heat.addTo(mymap);

        if (approvedSigns.length > 0) {
            var bounds = L.latLngBounds(approvedSigns.map(function(s) { return [s.lat, s.lng]; }));
            mymap.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
        }

        // GeoJSON layers
        fetch('data/sila.geojson')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var bl = L.geoJSON(data, {
                    style: { weight: 3, opacity: 1, color: '#dc2626', fillOpacity: 0.08, fillColor: '#dc2626' },
                    onEachFeature: function(f, l) {
                        if (f.properties && f.properties.T_NAME_T) l.bindPopup('<b>ขอบเขต:</b> ' + f.properties.T_NAME_T);
                    }
                }).addTo(mymap);
                layerControl.addOverlay(bl, "ขอบเขตเทศบาล");
            }).catch(function(e) { console.error('GeoJSON error:', e); });

        fetch('data/road_sila.geojson')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var rl = L.geoJSON(data, { style: { color: '#f59e0b', weight: 3 } }).addTo(mymap);
                layerControl.addOverlay(rl, "เส้นทางถนน");
            }).catch(function(e) { console.error('Road error:', e); });

        // Auto toggle heatmap by zoom
        mymap.on('zoomend', function() {
            if (mymap.getZoom() < 13) { if (!mymap.hasLayer(heat)) heat.addTo(mymap); }
            else { if (mymap.hasLayer(heat)) mymap.removeLayer(heat); }
        });

        // === Table with search & pagination ===
        var searchEl = document.getElementById('searchInput');
        var pageSizeEl = document.getElementById('pageSize');
        var tbody = document.getElementById('tableBody');
        var pageInfoEl = document.getElementById('pageInfo');
        var prevBtn = document.getElementById('prevBtn');
        var nextBtn = document.getElementById('nextBtn');
        var noResults = document.getElementById('noResults');
        var page = 1;

        function filtered() {
            var q = (searchEl.value || '').trim().toLowerCase();
            if (!q) return approvedList;
            return approvedList.filter(function(r) {
                return String(r.id).includes(q)
                    || (r.type || '').toLowerCase().includes(q)
                    || (r.road || '').toLowerCase().includes(q)
                    || (r.size || '').toLowerCase().includes(q);
            });
        }

        function render() {
            var size = parseInt(pageSizeEl.value, 10);
            var rows = filtered();
            var total = rows.length;
            var totalPages = Math.max(1, Math.ceil(total / size));
            if (page > totalPages) page = totalPages;

            if (total === 0) {
                tbody.innerHTML = '';
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
                var start = (page - 1) * size;
                var slice = rows.slice(start, start + size);
                tbody.innerHTML = slice.map(function(r) {
                    return '<tr onclick="flyTo(' + r.lat + ',' + r.lng + ',' + r.id + ')">'
                        + '<td class="fw-semibold text-primary">#' + r.id + '</td>'
                        + '<td>' + r.type + '</td>'
                        + '<td class="road-cell" title="' + r.road + '">' + r.road + '</td>'
                        + '<td class="text-nowrap">' + r.size + '</td>'
                        + '<td>' + (r.duration ? r.duration + ' วัน' : '-') + '</td>'
                        + '</tr>';
                }).join('');
            }

            pageInfoEl.textContent = total + ' รายการ • หน้า ' + page + '/' + totalPages;
            prevBtn.disabled = page <= 1;
            nextBtn.disabled = page >= totalPages;
        }

        pageSizeEl.addEventListener('change', function() { page = 1; render(); });
        searchEl.addEventListener('input', function() { page = 1; render(); });
        prevBtn.addEventListener('click', function() { if (page > 1) { page--; render(); } });
        nextBtn.addEventListener('click', function() {
            var totalPages = Math.max(1, Math.ceil(filtered().length / parseInt(pageSizeEl.value, 10)));
            if (page < totalPages) { page++; render(); }
        });

        render();

        // Click row to fly to marker
        window.flyTo = function(lat, lng, id) {
            mymap.flyTo([lat, lng], 16, { duration: 0.8 });
            markers.eachLayer(function(layer) {
                if (layer.getLatLng().lat === lat && layer.getLatLng().lng === lng) {
                    layer.openPopup();
                }
            });
        };
    });
    </script>

    <?php include './includes/scripts.php'; ?>
</body>

</html>