<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

// Validate ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: ../admin/users.php?error=invalid_id");
    exit;
}

// Delete the user (attendance rows cascade automatically via FK)
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

// Redirect back
header("Location: ../admin/users.php?deleted=1");
exit;