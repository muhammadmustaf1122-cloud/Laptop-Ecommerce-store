<?php
include '../login/auth.php';
// Debug file disabled for production security.
http_response_code(403);
die('Access Denied: Debug script disabled in production.');
?>
