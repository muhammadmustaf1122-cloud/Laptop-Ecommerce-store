<?php
include 'db.php';
$r = $conn->query('DESCRIBE activity_logs');
while($row = $r->fetch_assoc()) {
    echo implode(' | ', $row) . "\n";
}
$r2 = $conn->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 3');
echo "\n--- Sample rows ---\n";
while($row = $r2->fetch_assoc()) {
    echo implode(' | ', array_map(function($v){return substr((string)$v,0,80);}, $row)) . "\n";
}
