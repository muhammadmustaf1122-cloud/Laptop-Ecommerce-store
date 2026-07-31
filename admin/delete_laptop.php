<?php
include '../login/auth.php';
include 'db.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $conn->prepare('DELETE FROM laptop_images WHERE laptop_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM laptop WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    header('Location: inventory.php?msg=deleted');
} else {
    header('Location: inventory.php');
}
exit();
?>