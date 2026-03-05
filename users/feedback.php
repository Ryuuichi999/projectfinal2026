<?php
require '../includes/db.php';
require_once '../includes/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// ─── submit feedback ───
if (isset($_POST['submit_feedback'])) {
    csrf_check();
    $rating = (int) $_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');
    $request_id = !empty($_POST['request_id']) ? (int) $_POST['request_id'] : null;

    if ($rating < 1 || $rating > 5) {
        $msg = 'กรุณาให้คะแนน 1-5 ดาว';
        $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, request_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $request_id, $rating, $comment);

        if ($stmt->execute()) {
            $msg = 'ขอบคุณสำหรับความคิดเห็นของคุณ! ';
            $msg_type = 'success';
        } else {
            $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            $msg_type = 'danger';
        }
    }
}

// ดึงคำร้องที่ approved (ถ้ามี) สำหรับ dropdown
$stmt_req = $conn->prepare("SELECT id, sign_type, created_at FROM sign_requests WHERE user_id = ? AND status = 'approved' ORDER BY id DESC");
$stmt_req->bind_param("i", $user_id);
$stmt_req->execute();
$requests_result = $stmt_req->get_result();

// สถิติรวม
$avg_result = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM feedback");
$avg_stats = $avg_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ประเมินความพึงพอใจ</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary:    #1a56db;
            --primary-dark:#1a4fa0;
            --primary-light:#3b82f6;
            --accent:     #0d6efd;
            --ivory:      #f8fafc;
            --white:      #ffffff;
            --border:     #e2e8f0;
            --text-main:  #1e293b;
            --text-muted: #64748b;
            --success:    #16a34a;
            --danger:     #dc2626;
            --success-bg: #f0fdf4;
            --danger-bg:  #fef2f2;
        }

        body {
            font-family: 'Sarabun', 'IBM Plex Sans Thai', sans-serif;
            background-color: #eef1f6;
            color: var(--text-main);
        }

        .feedback-wrapper {
            max-width: 680px;
            margin: 2.5rem auto 4rem;
            padding: 0 1rem;
            animation: fadeUp .45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .feedback-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            border-radius: 14px 14px 0 0;
            padding: 2.2rem 2.5rem 2rem;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .feedback-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0.1) 100%);
            pointer-events: none;
        }

        .feedback-header-icon {
            font-size: 2.5rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .feedback-title {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            position: relative;
            z-index: 1;
        }

        .feedback-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .feedback-content {
            background: var(--white);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 14px 14px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .star-rating {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 1.5rem 0 1rem;
        }

        .star-rating .star {
            font-size: 3rem;
            color: #e5e2db;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .star-rating .star:hover,
        .star-rating .star.active {
            color: #f1c40f;
            transform: scale(1.15);
            text-shadow: 0 4px 8px rgba(241, 196, 15, 0.3);
        }

        .rating-label-text {
            display: inline-block;
            padding: 0.4rem 1.2rem;
            border-radius: 20px;
            background: #f8f9fa;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .rating-label-text.active {
            background: #fffcf0;
            color: #d4ac0d;
            border: 1px solid #f9e79f;
        }

        .form-label {
            font-size: .85rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: .5rem;
            letter-spacing: .02em;
            display: block;
        }

        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.8rem 1rem;
            font-size: .95rem;
            font-family: 'Sarabun', sans-serif;
            color: var(--text-main);
            background-color: #fff;
            transition: all .2s;
            box-shadow: none;
            width: 100%;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            background-color: var(--white);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            outline: none;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn-action-confirm {
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 0.8rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
            width: 100%;
            margin-top: 1rem;
            box-shadow: 0 4px 10px rgba(26, 86, 219, 0.2);
        }

        .btn-action-confirm:hover:not(:disabled) {
            background: #0d47a1;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(26, 86, 219, 0.3);
        }

        .btn-action-confirm:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .stats-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 2rem;
            margin-top: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .stats-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .avg-display {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(26, 86, 219, 0.15);
        }

        .satisfaction-bar {
            height: 6px;
            border-radius: 3px;
            background: #e5e2db;
            overflow: hidden;
            margin: 1rem auto 0;
        }

        .satisfaction-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #f1c40f, #f39c12);
            transition: width 1s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            border-radius: 8px;
            padding: 1rem 1.2rem;
            font-size: .9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            border: none;
            border-left: 4px solid;
        }

        .alert-success {
            background: var(--success-bg);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-danger {
            background: var(--danger-bg);
            border-color: var(--danger);
            color: var(--danger);
        }

        .btn-back {
            color: #64748b;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-back:hover {
            background: #e2e8f0;
            color: #334155;
        }
    </style>
</head>

<body>
    <?php include '../includes/user_navbar.php'; ?>
    <div class="container feedback-wrapper">
        <a href="index.php" class="btn-back">
            <i class="bi bi-chevron-left me-1"></i> ย้อนกลับ
        </a>
        <div class="feedback-header">
            <i class="bi bi-star feedback-header-icon"></i>
            <h4 class="feedback-title">ประเมินความพึงพอใจ</h4>
            <p class="feedback-subtitle">กรุณาให้คะแนนการใช้บริการของเรา</p>
        </div>
        <div class="feedback-content">
            <?php if ($msg): ?>
                <div class="alert alert-<?= $msg_type ?>">
                    <?= $msg ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <?= csrf_field() ?>
                <!-- เลือกคำร้อง (ถ้ามี) -->
                <?php if ($requests_result->num_rows > 0): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">เกี่ยวกับคำร้อง (ไม่บังคับ)</label>
                        <select name="request_id" class="form-select">
                            <option value="">ประเมินภาพรวม</option>
                            <?php while ($req = $requests_result->fetch_assoc()): ?>
                                <option value="<?= $req['id'] ?>">
                                    #
                                    <?= $req['id'] ?> -
                                    <?= htmlspecialchars($req['sign_type']) ?>
                                    (
                                    <?= date('d/m/Y', strtotime($req['created_at'])) ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <!-- Star Rating -->
                <div class="star-rating" id="starRating">
                    <i class="bi bi-star star" data-value="1"></i>
                    <i class="bi bi-star star" data-value="2"></i>
                    <i class="bi bi-star star" data-value="3"></i>
                    <i class="bi bi-star star" data-value="4"></i>
                    <i class="bi bi-star star" data-value="5"></i>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0" required>
                <div class="text-center mb-3">
                    <small class="text-muted" id="ratingLabel">กรุณาเลือกคะแนน</small>
                </div>
                <!-- ข้อเสนอแนะ -->
                <div class="mb-3">
                    <label class="form-label">ข้อเสนอแนะ (ไม่บังคับ)</label>
                    <textarea name="comment" class="form-control" rows="3"
                        placeholder="แสดงความคิดเห็นหรือข้อเสนอแนะ..."></textarea>
                </div>

                <button type="submit" name="submit_feedback" class="btn btn-action-confirm w-100 fw-bold"
                    id="submitBtn" disabled>
                    📝 ส่งความคิดเห็น
                </button>
            </form>
        </div>

        <!-- สถิติภาพรวม -->
        <div class="stats-card">
            <h5 class="stats-title"><i class="bi bi-bar-chart-fill text-primary"></i> ความพึงพอใจภาพรวม</h5>
            <div class="avg-display">
                <?= $avg_stats['total'] > 0 ? number_format($avg_stats['avg_rating'], 1) : '-' ?>
            </div>
            <div class="text-muted" style="font-size: 0.95rem;">
                จาก <?= number_format($avg_stats['total']) ?> ความคิดเห็น
                <?php if ($avg_stats['total'] > 0): ?>
                    <div class="satisfaction-bar mx-auto" style="max-width: 250px;">
                        <div class="satisfaction-fill"
                            style="width:<?= ($avg_stats['avg_rating'] / 5) * 100 ?>%"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stars = document.querySelectorAll('#starRating .star');
            const ratingInput = document.getElementById('ratingInput');
            const ratingLabel = document.getElementById('ratingLabel');
            const submitBtn = document.getElementById('submitBtn');
            const labels = ['', 'ไม่พอใจ 😞', 'พอใจน้อย 😐', 'พอใจปานกลาง 🙂', 'พอใจ 😊', 'พอใจมาก 🤩'];

            function updateStars(val) {
                stars.forEach((s, i) => {
                    if (i < val) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill', 'active');
                    } else {
                        s.classList.remove('bi-star-fill', 'active');
                        s.classList.add('bi-star');
                    }
                });
            }

            stars.forEach(star => {
                star.addEventListener('click', function () {
                    const val = parseInt(this.dataset.value);
                    ratingInput.value = val;
                    ratingLabel.textContent = labels[val];
                    submitBtn.disabled = false;
                    updateStars(val);
                });

                star.addEventListener('mouseenter', function () {
                    const val = parseInt(this.dataset.value);
                    updateStars(val);
                });
            });

            document.getElementById('starRating').addEventListener('mouseleave', function () {
                const currentVal = parseInt(ratingInput.value);
                updateStars(currentVal);
            });
        });
    </script>
</body>

</html>