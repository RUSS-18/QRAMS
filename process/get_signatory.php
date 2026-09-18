<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

include '../config/db.php';

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing event ID.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT signatory_name, signatory_signature 
    FROM events WHERE id = ?
");
$stmt->execute([$eventId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status'                => 'success',
    'signatory_name'        => $row['signatory_name']        ?? '',
    'signatory_signature'   => $row['signatory_signature']   ?? ''
]);