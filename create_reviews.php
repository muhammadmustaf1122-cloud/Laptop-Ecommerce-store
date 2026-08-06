<?php
// Debug / setup script disabled for production security.
http_response_code(403);
die('Access Denied: Setup script disabled in production.');
?>
