<?php
session_start();
require '../includes/db.php';
require '../includes/email_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employee')) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: request_list.php");
    exit;
}

$request_id = (int) $_GET['id'];

$checkCol = $conn->query("SHOW COLUMNS FROM sign_requests LIKE 'decision_note'");
if ($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE sign_requests ADD COLUMN decision_note TEXT NULL AFTER status");
}

$checkStatus = $conn->query("SHOW COLUMNS FROM sign_requests LIKE 'status'");
if ($checkStatus && $row = $checkStatus->fetch_assoc()) {
    $type = $row['Type'];
    $needAlter = (strpos($type, "reviewing") === false) || (strpos($type, "need_documents") === false);
    if ($needAlter) {
        $conn->query("ALTER TABLE sign_requests MODIFY COLUMN status ENUM('pending','reviewing','need_documents','waiting_payment','waiting_receipt','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
}

$success = null;
$error = null;

$sql = "SELECT * FROM sign_requests WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "ไม่พบข้อมูลคำขอ";
    exit;
}
$request = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'start_review') {
        if ($request['status'] === 'pending') {
            $stmt2 = $conn->prepare("UPDATE sign_requests SET status = 'reviewing' WHERE id = ?");
            $stmt2->bind_param("i", $request_id);
            if ($stmt2->execute()) {
                send_status_notification($request_id, $conn);
                $success = "เริ่มตรวจสอบคำขอแล้ว";
                $request['status'] = 'reviewing';
            } else {
                $error = "ไม่สามารถอัปเดตสถานะ: " . $conn->error;
            }
        } else {
            $error = "สถานะปัจจุบันไม่ใช่รอพิจารณา";
        }
    } elseif ($action === 'request_docs') {
        $note = trim($_POST['note'] ?? '');
        $stmt2 = $conn->prepare("UPDATE sign_requests SET status = 'need_documents', decision_note = ? WHERE id = ?");
        $stmt2->bind_param("si", $note, $request_id);
        if ($stmt2->execute()) {
            send_status_notification($request_id, $conn);
            $success = "ส่งคำขอเอกสารเพิ่มเติมแล้ว";
            $request['status'] = 'need_documents';
            $request['decision_note'] = $note;
        } else {
            $error = "ไม่สามารถบันทึกคำขอเอกสารเพิ่ม: " . $conn->error;
        }
    } elseif ($action === 'reject') {
        $note = trim($_POST['note'] ?? '');
        $stmt2 = $conn->prepare("UPDATE sign_requests SET status = 'rejected', decision_note = ? WHERE id = ?");
        $stmt2->bind_param("si", $note, $request_id);
        if ($stmt2->execute()) {
            send_status_notification($request_id, $conn);
            $success = "ปฏิเสธคำขอเรียบร้อย";
            $request['status'] = 'rejected';
            $request['decision_note'] = $note;
        } else {
            $error = "ไม่สามารถบันทึกการปฏิเสธ: " . $conn->error;
        }
    }
}

$sql_docs = "SELECT * FROM sign_documents WHERE request_id = ?";
$stmt_docs = $conn->prepare($sql_docs);
$stmt_docs->bind_param("i", $request_id);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();

function get_status_badge($status)
{
    switch ($status) {
        case 'pending':
            return "<span class='badge bg-warning text-dark'>⏳ รอพิจารณา</span>";
        case 'reviewing':
            return "<span class='badge bg-primary'>🔎 กำลังพิจารณา</span>";
        case 'need_documents':
            return "<span class='badge bg-info'>📑 ขอเอกสารเพิ่ม</span>";
        case 'waiting_payment':
            return "<span class='badge bg-danger'>⚠️ รอชำระเงิน</span>";
        case 'waiting_receipt':
            return "<span class='badge bg-info'>🧾 รอออกใบเสร็จ</span>";
        case 'approved':
            return "<span class='badge bg-success'>✅ อนุมัติแล้ว</span>";
        case 'rejected':
            return "<span class='badge bg-secondary'>❌ ไม่อนุมัติ</span>";
        default:
            return "<span class='badge bg-light text-dark'>$status</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายละเอียดคำขอ #<?= $request['id'] ?></title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">📋 รายละเอียดคำขอ #<?= $request['id'] ?></h2>
                <a href="request_list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> กลับหน้ารายการ</a>
            </div>
            <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                <?php if ($request['status'] === 'pending'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="start_review">
                        <button class="btn btn-outline-primary"><i class="bi bi-search"></i> เริ่มตรวจสอบ</button>
                    </form>
                <?php endif; ?>
                <?php if (in_array($request['status'], ['pending', 'reviewing', 'need_documents'])): ?>
                    <a href="approve_form.php?id=<?= $request['id'] ?>" class="btn btn-success"><i
                            class="bi bi-check-circle"></i> ยืนยัน/อนุมัติ</a>
                <?php endif; ?>
                <?php if (!in_array($request['status'], ['approved', 'rejected'])): ?>
                    <button class="btn btn-warning" type="button" data-bs-toggle="modal"
                        data-bs-target="#requestDocsModal"><i class="bi bi-file-earmark-plus"></i> ขอเอกสารเพิ่ม</button>
                    <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal"><i
                            class="bi bi-x-circle"></i> ปฏิเสธ</button>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: '<?= $success ?>', timer: 1800, showConfirmButton: false });
                    });
                </script>
            <?php endif; ?>
            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '<?= $error ?>' });
                    });
                </script>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card p-4 mb-4">
                        <h4 class="text-primary mb-3">ข้อมูลป้าย</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-label text-muted">สถานะตรวจสอบ</div>
                                <div class="detail-value"><?= get_status_badge($request['status']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label text-muted">วันที่ยื่นคำขอ</div>
                                <div class="detail-value"><?= date('d/m/Y H:i', strtotime($request['created_at'])) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label text-muted">ประเภทป้าย</div>
                                <div class="detail-value"><?= htmlspecialchars($request['sign_type']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label text-muted">ขนาด (กว้าง x ยาว/สูง)</div>
                                <div class="detail-value">
                                    <?= $request['width'] ?> x <?= $request['height'] ?> เมตร
                                    <span class="text-muted">(<?= $request['width'] * $request['height'] ?>
                                        ตร.ม.)</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label text-muted">ค่าธรรมเนียมประเมิน</div>
                                <div class="detail-value text-success fw-bold"><?= number_format($request['fee']) ?> บาท
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label text-muted">ระยะเวลาติดตั้ง</div>
                                <div class="detail-value"><?= $request['duration_days'] ?> วัน</div>
                            </div>
                            <div class="col-12">
                                <div class="detail-label text-muted">รายละเอียด/ข้อความ</div>
                                <div class="p-3 bg-light rounded mt-1">
                                    <?= nl2br(htmlspecialchars($request['description'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($request['decision_note'])): ?>
                                <div class="col-12">
                                    <div class="detail-label text-muted">บันทึกการตัดสินใจ</div>
                                    <div class="p-3 bg-warning-subtle rounded mt-1">
                                        <?= nl2br(htmlspecialchars($request['decision_note'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card p-4">
                        <h4 class="text-primary mb-3">📍 สถานที่ติดตั้ง</h4>
                        <div id="map" style="height:300px;width:100%;border-radius:8px;border:1px solid #ddd;"></div>
                        <div class="mt-2 text-muted small">
                            พิกัด: <?= $request['location_lat'] ?>, <?= $request['location_lng'] ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card p-4">
                        <h4 class="text-success mb-3">📁 เอกสารแนบ</h4>
                        <?php if ($result_docs->num_rows > 0): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php while ($doc = $result_docs->fetch_assoc()): ?>
                                    <div class="doc-item">
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($doc['doc_type']) ?></div>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                                            class="btn btn-outline-primary btn-sm w-100">ดูเอกสาร</a>
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

    <?php include '../includes/scripts.php'; ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var lat = <?= $request['location_lat'] ?: 'null' ?>;
            var lng = <?= $request['location_lng'] ?: 'null' ?>;
            if (lat !== null && lng !== null) {
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
                L.marker([lat, lng]).addTo(map).bindPopup("จุดที่ติดตั้งป้าย").openPopup();
            }
        });
    </script>

    <div class="modal fade" id="requestDocsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ขอเอกสารเพิ่มเติม</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="request_docs">
                    <div class="mb-2">
                        <label class="form-label">ระบุเอกสาร/เหตุผล</label>
                        <textarea name="note" class="form-control" rows="3"
                            placeholder="เช่น โปรดแนบสำเนาบัตรประชาชนเพิ่มเติม หรือเอกสารยินยอมเจ้าของที่"
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">ส่งคำขอ</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ปฏิเสธคำขอ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-2">
                        <label class="form-label">เหตุผลการปฏิเสธ</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="โปรดระบุเหตุผลอย่างชัดเจน"
                            required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">บันทึกการปฏิเสธ</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>