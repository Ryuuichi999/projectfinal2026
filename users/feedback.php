<?php
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// ─── submit feedback ───
if (isset($_POST['submit_feedback'])) {
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
            $msg = 'ขอบคุณสำหรับความคิดเห็นของคุณ! 🙏';
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
        .star-rating {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }

        .star-rating .star {
            font-size: 3rem;
            color: #dee2e6;
            cursor: pointer;
            transition: 0.2s;
        }

        .star-rating .star:hover,
        .star-rating .star.active {
            color: #ffc107;
            transform: scale(1.15);
        }

        .feedback-card {
            max-width: 600px;
            margin: 0 auto;
        }

        .avg-display {
            font-size: 3rem;
            font-weight: 700;
            color: #ffc107;
        }

        .satisfaction-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }

        .satisfaction-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease;
        }
    </style>
</head>

<body>
    <?php include '../includes/user_navbar.php'; ?>

    <div class="container fade-in-up mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Back Button -->
                <div class="mb-3">
                    <a href="index.php" class="btn-back d-inline-flex align-items-center"><i
                            class="bi bi-chevron-left me-1"></i> ย้อนกลับ</a>
                </div>

                <!-- ฟอร์มประเมิน -->
                <div class="card p-4 feedback-card mb-4">
                    <h4 class="text-center mb-3">⭐ ให้คะแนนความพึงพอใจ</h4>
                    <p class="text-center text-muted">กรุณาให้คะแนนการใช้บริการของเรา</p>

                    <?php if ($msg): ?>
                        <div class="alert alert-<?= $msg_type ?>">
                            <?= $msg ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
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
                            <span class="star" data-value="1">⭐</span>
                            <span class="star" data-value="2">⭐</span>
                            <span class="star" data-value="3">⭐</span>
                            <span class="star" data-value="4">⭐</span>
                            <span class="star" data-value="5">⭐</span>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="0" required>
                        <div class="text-center mb-3">
                            <small class="text-muted" id="ratingLabel">กรุณาเลือกคะแนน</small>
                        </div>

                        <!-- ข้อเสนอแนะ -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">ข้อเสนอแนะ (ไม่บังคับ)</label>
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
                <div class="card p-4 text-center">
                    <h5 class="text-primary">📊 ความพึงพอใจภาพรวม</h5>
                    <div class="avg-display">
                        <?= $avg_stats['total'] > 0 ? number_format($avg_stats['avg_rating'], 1) : '-' ?>
                    </div>
                    <div class="text-muted">
                        จาก
                        <?= number_format($avg_stats['total']) ?> ความคิดเห็น
                        <?php if ($avg_stats['total'] > 0): ?>
                            <div class="satisfaction-bar mt-2 mx-auto" style="max-width:200px;">
                                <div class="satisfaction-fill bg-warning"
                                    style="width:<?= ($avg_stats['avg_rating'] / 5) * 100 ?>%"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

            stars.forEach(star => {
                star.addEventListener('click', function () {
                    const val = parseInt(this.dataset.value);
                    ratingInput.value = val;
                    ratingLabel.textContent = labels[val];
                    submitBtn.disabled = false;

                    stars.forEach((s, i) => {
                        s.classList.toggle('active', i < val);
                    });
                });

                star.addEventListener('mouseenter', function () {
                    const val = parseInt(this.dataset.value);
                    stars.forEach((s, i) => {
                        s.style.color = i < val ? '#ffc107' : '#dee2e6';
                    });
                });
            });

            document.getElementById('starRating').addEventListener('mouseleave', function () {
                const currentVal = parseInt(ratingInput.value);
                stars.forEach((s, i) => {
                    s.style.color = i < currentVal ? '#ffc107' : '#dee2e6';
                });
            });
        });
    </script>
</body>

</html>