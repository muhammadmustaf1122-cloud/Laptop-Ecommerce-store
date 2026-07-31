<?php
include '../login/auth.php';
include 'db.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode([]); exit; }

$result = $conn->query("SELECT id, image_url FROM laptop_images WHERE laptop_id = $id ORDER BY id ASC");
$images = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}
echo json_encode($images);
