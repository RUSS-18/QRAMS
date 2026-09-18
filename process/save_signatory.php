<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

include '../config/db.php';

$eventId = (int)($_POST['event_id'] ?? 0);
$name    = trim($_POST['signatory_name'] ?? '');

if (!$eventId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing event ID.']);
    exit;
}

// Optional: upload signature image
$signaturePath = null;

if (!empty($_FILES['signature_image']['name']) 
    && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['signature_image'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
        echo json_encode(['status' => 'error', 'message' => 'Image must be PNG, JPG, or GIF.']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Image must be under 2MB.']);
        exit;
    }

    // Make sure folder exists
    $uploadDir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'sig_event' . $eventId . '_' . time() . '.' . $ext;
    $target   = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not save the image.']);
        exit;
    }

    // Delete the old signature file if it exists
    $stmt = $conn->prepare("SELECT signatory_signature FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists(__DIR__ . '/../' . $old)) {
        @unlink(__DIR__ . '/../' . $old);
    }

    $signaturePath = 'uploads/signatures/' . $filename;
}

// Update database
if ($signaturePath !== null) {
    $stmt = $conn->prepare("
        UPDATE events 
        SET signatory_name = ?, signatory_signature = ? 
        WHERE id = ?
    ");
    $stmt->execute([$name, $signaturePath, $eventId]);
} else {
    $stmt = $conn->prepare("
        UPDATE events 
        SET signatory_name = ? 
        WHERE id = ?
    ");
    $stmt->execute([$name, $eventId]);
}

echo json_encode([
    'status'          => 'success',
    'message'         => 'Signatory saved.',
    'signature_path'  => $signaturePath
]);