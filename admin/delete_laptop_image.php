<?php
include '../login/auth.php';
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
    exit;
}

$img_id    = intval($_POST['img_id']    ?? 0);
$laptop_id = intval($_POST['laptop_id'] ?? 0);

if ($img_id <= 0 || $laptop_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// Fetch the image record using prepared statement
$stmt = $conn->prepare("SELECT image_url FROM laptop_images WHERE id = ? AND laptop_id = ?");
$stmt->bind_param("ii", $img_id, $laptop_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Image not found']);
    exit;
}
$img = $res->fetch_assoc();
$stmt->close();

// Delete the physical file if it's a local upload (not a URL)
if (!filter_var($img['image_url'], FILTER_VALIDATE_URL)) {
    $file_path = __DIR__ . '/../uploads/' . basename($img['image_url']);
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}

// Delete the DB record using prepared statement
$del_stmt = $conn->prepare("DELETE FROM laptop_images WHERE id = ? AND laptop_id = ?");
$del_stmt->bind_param("ii", $img_id, $laptop_id);
$del_stmt->execute();
$del_stmt->close();

echo json_encode(['success' => true]);

