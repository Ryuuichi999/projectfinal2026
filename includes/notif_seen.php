<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_POST['role'] ?? '';
$count = isset($_POST['count']) ? (int)$_POST['count'] : 0;
if ($role === 'user') {
    $_SESSION['notif_last_view_user'] = $count;
} else {
    $_SESSION['notif_last_view_emp'] = $count;
}
echo 'ok';
