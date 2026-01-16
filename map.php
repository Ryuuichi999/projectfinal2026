<?php
// สมมติว่าไฟล์ map.php อยู่ในรูทของ Projectป้าย/
require './includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'user' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: login.php");
    exit;
}

// *** ดึงข้อมูลป้ายที่อนุมัติแล้ว ***
$approved_signs = [];
$sql_signs = "SELECT location_lat, location_lng, sign_type FROM sign_requests WHERE status = 'approved' AND location_lat IS NOT NULL AND location_lng IS NOT NULL";
$result_signs = $conn->query($sql_signs);

if ($result_signs && $result_signs->num_rows > 0) {
    while ($row = $result_signs->fetch_assoc()) {
        $approved_signs[] = [
            'lat' => (float) $row['location_lat'],
            'lng' => (float) $row['location_lng'],
            'type' => htmlspecialchars($row['sign_type'])
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
            height: 600px;
            width: 100%;
            border-radius: 14px;
        }
    </style>
</head>

<body>

    <?php include './includes/sidebar.php'; ?>

    <div class="content">
        <div class="card p-4 fade-in-up">
            <h2 class="mb-2">🗺️ แผนที่ข้อมูลพื้นที่ (GIS)</h2>
            <p class="text-muted mb-4">แสดงขอบเขต ตำแหน่งป้ายที่ได้รับอนุมัติ และเส้นทางถนนในเขต ทม.ศิลา</p>

            <div id="mapid"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

            var mymap = L.map('mapid').setView([initialLat, initialLng], initialZoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(mymap);

            // *** 4. การแสดงตำแหน่งป้ายที่อนุมัติแล้วจากฐานข้อมูล ***
            var approvedSigns = <?php echo json_encode($approved_signs); ?>;

            approvedSigns.forEach(function (sign) {
                if (sign.lat && sign.lng) {
                    L.marker([sign.lat, sign.lng])
                        .addTo(mymap)
                        .bindPopup("<b>ประเภทป้าย:</b> " + sign.type);
                }
            });

            // หากมีป้ายที่อนุมัติแล้วอย่างน้อย 1 ป้าย ให้ปรับมุมมองแผนที่ไปยังป้ายนั้น
            if (approvedSigns.length > 0) {
                mymap.panTo([approvedSigns[0].lat, approvedSigns[0].lng]);
            }

            // // ตัวอย่าง Marker ตำแหน่ง ทม.ศิลา
            // L.marker([16.480, 102.830])
            //     .addTo(mymap)
            //     .bindPopup("เทศบาลเมืองศิลา (ทม.ศิลา)");

            // *** 5. การแสดงขอบเขตพื้นที่จาก GeoJSON ***

            // กำหนดพาธไปยังไฟล์ GeoJSON ของคุณ (เนื่องจาก map.php อยู่ในรูท และไฟล์อยู่ใน data/)
            const geojsonPath = 'data/sila.geojson';

            fetch(geojsonPath)
                .then(response => {
                    if (!response.ok) {

                        throw new Error(`Failed to load GeoJSON: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(geojson_data => {
                    L.geoJSON(geojson_data, {
                        style: function (feature) {
                            return {
                                weight: 3,             // ความหนาของเส้นขอบ
                                opacity: 1,
                                color: 'blue',         // สีเส้นขอบ
                                fillOpacity: 0       // ความโปร่งใสของสีเติม
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

                    console.log("GeoJSON loaded successfully:", geojson_data);

                    // **ตัวเลือก:** หากคุณต้องการให้แผนที่ซูมไปยังขอบเขตของ GeoJSON อัตโนมัติ
                    // L.geoJSON(geojson_data).addTo(mymap).getBounds().isValid() && mymap.fitBounds(L.geoJSON(geojson_data).getBounds());

                })
                .catch(error => {
                    console.error("Error loading GeoJSON data:", error);
                });

        });
    </script>

    </script>
    <?php include './includes/scripts.php'; ?>
</body>

</html>