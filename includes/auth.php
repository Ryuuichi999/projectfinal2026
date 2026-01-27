<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header("Location: ../login.php");
    exit;
}
?>