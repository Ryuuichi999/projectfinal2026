<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'unauthorized';
    exit;
}

$role = $_POST['role'] ?? '';
$count = isset($_POST['count']) ? (int)$_POST['count'] : 0;

if ($role === 'user' && $_SESSION['role'] === 'user') {
    $_SESSION['notif_last_view_user'] = $count;
} elseif (in_array($role, ['employee', 'admin']) && in_array($_SESSION['role'], ['employee', 'admin'])) {
    $_SESSION['notif_last_view_emp'] = $count;
} else {
    http_response_code(400);
    echo 'invalid_role';
    exit;
}
echo 'ok';
