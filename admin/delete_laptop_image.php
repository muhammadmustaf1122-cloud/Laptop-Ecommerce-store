<?php
include '../login/auth.php';
include 'db.php';

header('Content-Type: application/json');

$img_id    = intval($_POST['img_id']    ?? 0);
$laptop_id = intval($_POST['laptop_id'] ?? 0);

if ($img_id <= 0 || $laptop_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Fetch the image record (verify it belongs to this laptop)
$row = $conn->query("SELECT image_url FROM laptop_images WHERE id = $img_id AND laptop_id = $laptop_id");
if (!$row || $row->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Image not found']);
    exit;
}
$img = $row->fetch_assoc();

// Delete the physical file if it's a local upload (not a URL)
if (!filter_var($img['image_url'], FILTER_VALIDATE_URL)) {
    $file_path = __DIR__ . '/../uploads/' . $img['image_url'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}

// Delete the DB record
$conn->query("DELETE FROM laptop_images WHERE id = $img_id AND laptop_id = $laptop_id");

echo json_encode(['success' => true]);
