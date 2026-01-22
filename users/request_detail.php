<?php
require '../includes/db.php';

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

// ฟังก์ชันแสดงสถานะ (เหมือนใน my_request.php)
function get_status_badge($status)
{
    switch ($status) {
        case 'pending':
            $class = 'warning';
            $text = '⏳ รอกำลังพิจารณา';
            break;
        case 'waiting_payment':
            $class = 'danger';
            $text = '⚠️ รอชำระเงิน';
            break;
        case 'approved':
            $class = 'success';
            $text = '✅ อนุมัติแล้ว';
            break;
        case 'rejected':
            $class = 'secondary';
            $text = '❌ ไม่อนุมัติ';
            break;
        default:
            $class = 'info';
            $text = $status;
    }
    return "<span class='badge bg-$class'>$text</span>";
}
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
            font-size: 1.1em;
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
    </style>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content">
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
                    <div class="card p-4 fade-in-up delay-200">
                        <h4 class="text-success mb-3">📁 เอกสารแนบ</h4>
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

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map)
                .bindPopup("<b>จุดที่ติดตั้งป้าย</b>")
                .openPopup();
        });
    </script>

</body>
<?php include '../includes/scripts.php'; ?>

</html>