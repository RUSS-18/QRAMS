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
    echo json_encode(['status' => 'error', 'message' => 'Missing event.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
               u.id, u.name, u.email,
               u.department, u.year_level,
               (SELECT COUNT(*) FROM certificates_sent c
                WHERE c.user_id = u.id AND c.event_id = ? AND c.status = 'sent') AS sent
        FROM attendance a
        JOIN users u ON u.id = a.user_id
        WHERE a.event_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$eventId, $eventId]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'    => 'success',
        'attendees' => $attendees
    ]);

} catch (PDOException $e) {
    error_log('[get_attendees] ' . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Query error: ' . $e->getMessage()
    ]);
}