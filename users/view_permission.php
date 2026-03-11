<?php
require '../includes/auth.php';
require '../includes/db.php';
require '../includes/thaibaht.php';

if (!isset($_GET['id'])) {
    $_SESSION['flash_error'] = 'ไม่พบข้อมูล';
    header('Location: my_request.php');
    exit;
}

$request_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

$sql = "SELECT r.*, u.citizen_id, u.title_name, u.first_name, u.last_name, u.address as user_address
        FROM sign_requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

// ตรวจสิทธิ์: เจ้าของคำร้อง หรือ admin/employee เท่านั้น
if (!$request || ($request['user_id'] != $user_id && !in_array($role, ['admin', 'employee']))) {
    $_SESSION['flash_error'] = 'ไม่มีสิทธิ์เข้าถึง';
    header('Location: my_request.php');
    exit;
}

if (!in_array($request['status'], ['approved', 'expired'])) {
    $_SESSION['flash_error'] = 'เอกสารไม่พร้อมใช้งาน';
    header('Location: my_request.php');
    exit;
}

function getThaiDate($date) {
    if (!$date) return "....................";
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp) + 543;
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    $std  = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($std,$thai,$d)." $m ".str_replace($std,$thai,$y);
}

function toThaiNum($number) {
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    $std  = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($std, $thai, $number);
}

require_once '../includes/settings_helper.php';
$signer_name = !empty($request['permit_signer_name'])
    ? $request['permit_signer_name']
    : getSetting($conn, 'permit_signer_name', '................................................................');
$signer_pos = !empty($request['permit_signer_position'])
    ? $request['permit_signer_position']
    : getSetting($conn, 'permit_signer_position', 'นายกเทศมนตรีเมืองศิลา');
$p_sig_path = getSetting($conn, 'permit_signature_path', '');

$permit_start = !empty($request['install_date']) ? $request['install_date'] : (!empty($request['permit_date']) ? $request['permit_date'] : $request['created_at']);
$permit_end   = !empty($request['end_date']) ? $request['end_date'] : date('Y-m-d', strtotime($permit_start . ' + ' . ($request['duration_days'] - 1) . ' days'));
$safe_permit_no = str_replace('/', '-', $request['permit_no']);

// เตรียม base64 ของลายเซ็นเพื่อใช้ใน PDF (หลีกเลี่ยงปัญหา CORS)
$sig_base64 = '';
if ($p_sig_path && file_exists("../" . $p_sig_path)) {
    $sig_data = file_get_contents("../" . $p_sig_path);
    $sig_mime = mime_content_type("../" . $p_sig_path);
    $sig_base64 = 'data:' . $sig_mime . ';base64,' . base64_encode($sig_data);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หนังสืออนุญาต (แบบ ร.ส. ๒)</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #e0e0e0;
            color: #000;
        }

        /* ===== PAGE ===== */
        .page {
            width: 794px;
            min-height: 1123px;
            padding: 57px 94px 76px 94px;
            margin: 30px auto;
            background: white;
            box-shadow: 0 0 8px rgba(0,0,0,0.15);
            position: relative;
            font-size: 14pt;
            line-height: 1.85;
        }

        @media print {
            body { background: white; }
            .page { box-shadow: none; margin: 0; }
            .no-print { display: none !important; }
        }

        /* ===== HEADER ===== */
        .form-code {
            position: absolute;
            top: 57px;
            right: 94px;
            font-size: 12pt;
        }
        .header-garuda { text-align: center; }
        .garuda-img { width: 100px; height: auto; }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        .doc-number {
            text-align: right;
            margin-top: 6px;
            margin-bottom: 26px;
        }

        /* ===== ITEMS ===== */
        .content { margin-top: 0; }
        .item-block { margin-bottom: 14px; }

        .item-row {
            display: flex;
            align-items: flex-start;
        }
        .item-num {
            flex-shrink: 0;
            width: 46px;
        }
        .item-body {
            flex: 1;
            text-align: justify;
        }
        .sub-line {
            margin-left: 46px;
            text-align: justify;
        }

        /* ===== SIGNATURE ===== */
        .signature-wrap {
            margin-top: 46px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-box {
            text-align: center;
            min-width: 240px;
        }
        .signature-box img {
            height: 68px;
            display: block;
            margin: 0 auto;
        }
        .sig-name  { margin-top: 4px; }
        .sig-pos   { margin-top: 4px; white-space: pre-wrap; }
        .sig-role  { margin-top: 2px; }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; padding:12px; display:flex; justify-content:center; gap:12px;">
    <button onclick="downloadPDF()"
        style="padding:10px 22px; font-size:14px; cursor:pointer; background:#28a745; color:white; border:none; border-radius:5px;">
        ⬇ ดาวน์โหลด PDF
    </button>
</div>

<div class="page" id="doc-page">

    <div class="form-code">แบบ ร.ส. ๒</div>

    <div class="header-garuda">
        <img src="../image/ตราครุฑ.png" class="garuda-img" alt="Garuda">
        <div class="doc-title">หนังสืออนุญาต</div>
    </div>

    <div class="doc-number">
        เลขที่ <?= toThaiNum(htmlspecialchars($request['permit_no'])) ?>
    </div>

    <div class="content">

        <!-- ข้อ ๑ -->
        <div class="item-block">
            <div class="item-row">
                <span class="item-num">๑.</span>
                <span class="item-body">
                    อนุญาตให้ <strong><?= htmlspecialchars($request['applicant_name']) ?></strong>
                    อยู่บ้านเลขที่ <strong><?= toThaiNum(htmlspecialchars($request['applicant_address'])) ?></strong>
                </span>
            </div>
        </div>

        <!-- ข้อ ๒ -->
        <div class="item-block">
            <div class="item-row">
                <span class="item-num">๒.</span>
                <span class="item-body">โฆษณาด้วยการปิด โปรย ติดตั้งแผ่นประกาศหรือแผ่นปลิว เพื่อการโฆษณา ได้ ณ ที่</span>
            </div>
            <div class="sub-line">ตำบล ศิลา&nbsp;&nbsp;อำเภอ เมืองขอนแก่น&nbsp;&nbsp;จังหวัด ขอนแก่น</div>
            <div class="sub-line">
                ข้อความ <strong><?= htmlspecialchars($request['description']) ?></strong>
                (<?= htmlspecialchars($request['road_name']) ?>)
                จำนวน <strong><?= toThaiNum($request['quantity']) ?></strong> ป้าย
            </div>
        </div>

        <!-- ข้อ ๓ -->
        <div class="item-block">
            <div class="item-row">
                <span class="item-num">๓.</span>
                <span class="item-body">
                    ตั้งแต่วันที่ <strong><?= getThaiDate($permit_start) ?></strong>
                    ถึง วันที่ <strong><?= getThaiDate($permit_end) ?></strong>
                </span>
            </div>
            <div class="sub-line">
                รวมกำหนดเวลาอนุญาต <strong><?= toThaiNum($request['duration_days']) ?></strong> วัน
            </div>
        </div>

        <!-- ข้อ ๔ -->
        <div class="item-block">
            <div class="item-row">
                <span class="item-num">๔.</span>
                <span class="item-body">
                    ได้รับค่าธรรมเนียม จำนวน
                    <strong><?= toThaiNum(number_format($request['fee'], 0)) ?></strong> บาท
                    (<?= ThaiBahtConversion($request['fee']) ?>)
                </span>
            </div>
        </div>

        <!-- ข้อ ๕ -->
        <div class="item-block">
            <div class="item-row">
                <span class="item-num">๕.</span>
                <span class="item-body">
                    หนังสืออนุญาตนี้ให้ไว้ ณ วันที่ <strong><?= getThaiDate($request['permit_date']) ?></strong>
                </span>
            </div>
        </div>

    </div><!-- /content -->

    <!-- ลายเซ็น ชิดขวา -->
    <div class="signature-wrap">
        <div class="signature-box">
            <?php if ($sig_base64): ?>
                <img src="<?= $sig_base64 ?>" alt="ลายเซ็น">
            <?php else: ?>
                <div style="height:68px;"></div>
            <?php endif; ?>
            <div class="sig-name">(<?= htmlspecialchars($signer_name) ?>)</div>
            <div class="sig-pos"><?= htmlspecialchars($signer_pos) ?></div>
            <div class="sig-role">หรือพนักงานเจ้าหน้าที่ผู้ออกหนังสืออนุญาต</div>
        </div>
    </div>

</div><!-- /page -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
async function downloadPDF() {
    await document.fonts.ready;

    const el = document.getElementById('doc-page');

    // ---- วิธีแก้ปัญหา offset เพี้ยน ----
    // ชั่วคราว: เลื่อน element ออกจาก flow ปกติ
    // แล้ว capture ที่ position (0,0) พอดี
    const originalStyle = {
        position: el.style.position,
        top:      el.style.top,
        left:     el.style.left,
        margin:   el.style.margin,
        zIndex:   el.style.zIndex
    };

    // reset margin/position ให้ html2canvas capture จาก top-left พอดี
    el.style.position = 'fixed';
    el.style.top      = '0';
    el.style.left     = '0';
    el.style.margin   = '0';
    el.style.zIndex   = '9999';

    // ซ่อน scrollbar ชั่วคราว
    document.body.style.overflow = 'hidden';

    const opt = {
        margin:      0,
        filename:    'permission_<?= $safe_permit_no ?>.pdf',
        image:       { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale:        2,
            useCORS:      true,
            allowTaint:   true,
            logging:      false,
            width:        794,
            height:       1123,
            windowWidth:  794,
            windowHeight: 1123,
            x:            0,
            y:            0,
            scrollX:      0,
            scrollY:      0
        },
        jsPDF: {
            unit:        'mm',
            format:      'a4',
            orientation: 'portrait',
            compress:    true
        }
    };

    try {
        await html2pdf().set(opt).from(el).save();
    } finally {
        // คืนค่า style เดิมทุกกรณี
        el.style.position = originalStyle.position;
        el.style.top      = originalStyle.top;
        el.style.left     = originalStyle.left;
        el.style.margin   = originalStyle.margin;
        el.style.zIndex   = originalStyle.zIndex;
        document.body.style.overflow = '';
    }
}
</script>

</body>
</html>