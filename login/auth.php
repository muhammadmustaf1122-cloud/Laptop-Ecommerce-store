<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && ($_SESSION['role'] ?? '') === 'admin';

if (!$is_admin) {
    session_regenerate_id(true);
    session_unset();
    session_destroy();
    header('Location: /laptop/login/login.php');
    exit();
}
?>