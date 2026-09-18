<?php
include '../config/db.php';

// DELETE
if (isset($_GET['delete_id'])) {
    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([(int)$_GET['delete_id']]);
    header("Location: ../admin/events.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventName = trim($_POST['event_name'] ?? '');
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';

    // Validate dates
    if (!$startDate || !$endDate) die("Start and end dates are required.");
    if (strtotime($endDate) < strtotime($startDate)) die("End date cannot be before start date.");

    // Capture multi-select filters as JSON
    $depts = $_POST['allowed_departments'] ?? [];
    $years = $_POST['allowed_year_levels'] ?? [];
    $deptsJson = json_encode(array_values(array_filter($depts)));
    $yearsJson = json_encode(array_values(array_filter($years)));

    // Location
    $lat    = ($_POST['venue_lat'] ?? '') === '' ? null : (float)$_POST['venue_lat'];
    $lng    = ($_POST['venue_lng'] ?? '') === '' ? null : (float)$_POST['venue_lng'];
    $radius = (int)($_POST['radius_meters'] ?? 100);

    if (!empty($_POST['id'])) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE events
            SET event_name = ?, start_date = ?, end_date = ?,
                allowed_departments = ?, allowed_year_levels = ?,
                venue_lat = ?, venue_lng = ?, radius_meters = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $eventName, $startDate, $endDate,
            $deptsJson, $yearsJson,
            $lat, $lng, $radius,
            (int)$_POST['id']
        ]);
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO events (event_name, start_date, end_date,
                                allowed_departments, allowed_year_levels,
                                venue_lat, venue_lng, radius_meters)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventName, $startDate, $endDate,
            $deptsJson, $yearsJson,
            $lat, $lng, $radius
        ]);
    }

    header("Location: ../admin/events.php");
    exit;
}
?>