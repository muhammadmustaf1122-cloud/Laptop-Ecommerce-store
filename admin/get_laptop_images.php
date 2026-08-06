<?php
include '../login/auth.php';
include 'db.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode([]); exit; }

$stmt = $conn->prepare("SELECT id, image_url FROM laptop_images WHERE laptop_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$images = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
}
$stmt->close();

echo json_encode($images);

