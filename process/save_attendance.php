<?php
include '../config/db.php';

header('Content-Type: application/json');

$userId  = (int)($_POST['user_id']  ?? $_GET['user_id']  ?? 0);
$eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);

$latitude  = $_POST['latitude']  ?? null;
$longitude = $_POST['longitude'] ?? null;
$accuracy  = $_POST['accuracy']  ?? null;

if (!$userId || !$eventId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid QR code.']);
    exit;
}

if ($latitude === null || $longitude === null) {
    echo json_encode(['status' => 'error', 'message' => 'Location is required.']);
    exit;
}

// ------------------------------------------------------------
// FETCH EVENT (dates, filters, geofence)
// ------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT event_name, start_date, end_date,
           allowed_departments, allowed_year_levels,
           venue_lat, venue_lng, radius_meters
    FROM events WHERE id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo json_encode(['status' => 'error', 'message' => 'Event not found.']);
    exit;
}

// ------------------------------------------------------------
// CHECK EVENT ACTIVE (today must be between start and end)
// ------------------------------------------------------------
$today = date('Y-m-d');
if ($today < $event['start_date'] || $today > $event['end_date']) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'This event is not currently active. Active period: '
                     . date('M j', strtotime($event['start_date'])) . ' – '
                     . date('M j, Y', strtotime($event['end_date']))
    ]);
    exit;
}

// ------------------------------------------------------------
// ELIGIBILITY CHECK (department + year level)
// ------------------------------------------------------------
$stmt = $conn->prepare("SELECT name, email, department, year_level FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

$depts = json_decode($event['allowed_departments'] ?? '[]', true) ?: [];
$years = json_decode($event['allowed_year_levels'] ?? '[]', true) ?: [];

if (!empty($depts) && !in_array($user['department'], $depts)) {
    echo json_encode([
        'status'  => 'not_eligible',
        'message' => 'Your department (' . ($user['department'] ?: 'N/A') . ') is not eligible for this event.'
    ]);
    exit;
}

if (!empty($years) && !in_array($user['year_level'], $years)) {
    echo json_encode([
        'status'  => 'not_eligible',
        'message' => 'Your year level (' . ($user['year_level'] ?: 'N/A') . ') is not eligible for this event.'
    ]);
    exit;
}

// ------------------------------------------------------------
// GEOFENCE CHECK
// ------------------------------------------------------------
if (!empty($event['venue_lat']) && !empty($event['venue_lng'])) {
    $earthRadius = 6371000;
    $dLat = deg2rad($event['venue_lat'] - $latitude);
    $dLng = deg2rad($event['venue_lng'] - $longitude);
    $a = sin($dLat / 2) ** 2 +
         cos(deg2rad($latitude)) * cos(deg2rad($event['venue_lat'])) *
         sin($dLng / 2) ** 2;
    $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    $radius   = (int)($event['radius_meters'] ?? 100);

    if ($distance > $radius) {
        echo json_encode([
            'status'   => 'out_of_range',
            'message'  => 'You are ' . round($distance) . 'm away. Must be within ' . $radius . 'm.',
            'distance' => round($distance, 2)
        ]);
        exit;
    }
}

// ------------------------------------------------------------
// CHECK-IN vs CHECK-OUT DECISION
// ------------------------------------------------------------
$stmt = $conn->prepare("
    SELECT id, time_in, time_out FROM attendance
    WHERE user_id = ? AND event_id = ? AND attendance_date = ?
");
$stmt->execute([$userId, $eventId, $today]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    // ---------- CHECK-IN ----------
    $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, event_id, attendance_date, time_in,
                                latitude, longitude, accuracy)
        VALUES (?, ?, ?, NOW(), ?, ?, ?)
    ");
    $stmt->execute([$userId, $eventId, $today, $latitude, $longitude, $accuracy]);

    echo json_encode([
        'status'      => 'check_in',
        'message'     => 'Checked in successfully!',
        'name'        => $user['name'],
        'event'       => $event['event_name'],
        'time'        => date('Y-m-d H:i:s'),
        'attendance_date' => $today,
        'user_id'     => $userId
    ]);
    exit;
}

if ($existing['time_out'] === null) {
    // ---------- CHECK-OUT ----------
    $stmt = $conn->prepare("
        UPDATE attendance
        SET time_out = NOW(), out_latitude = ?, out_longitude = ?, out_accuracy = ?
        WHERE id = ?
    ");
    $stmt->execute([$latitude, $longitude, $accuracy, $existing['id']]);

    $duration = strtotime('now') - strtotime($existing['time_in']);
    $hours    = floor($duration / 3600);
    $minutes  = floor(($duration % 3600) / 60);

    echo json_encode([
        'status'      => 'check_out',
        'message'     => "Checked out successfully! Duration: {$hours}h {$minutes}m",
        'name'        => $user['name'],
        'event'       => $event['event_name'],
        'time_in'     => $existing['time_in'],
        'time_out'    => date('Y-m-d H:i:s'),
        'duration'    => "{$hours}h {$minutes}m",
        'user_id'     => $userId
    ]);
    exit;
}

// Already checked in AND out
echo json_encode([
    'status'  => 'duplicate',
    'message' => 'You already completed your attendance for today (in: '
                 . date('g:i A', strtotime($existing['time_in'])) . ', out: '
                 . date('g:i A', strtotime($existing['time_out'])) . ').'
]);