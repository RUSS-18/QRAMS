<?php
include '../config/db.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if($event_id <= 0) die(json_encode([]));

$stmt = $conn->prepare("
    SELECT users.name, events.event_name, attendance.scan_time 
    FROM attendance 
    JOIN users ON users.id = attendance.user_id 
    WHERE attendance.event_id = ? 
    ORDER BY attendance.scan_time DESC 
    LIMIT 10
");
$stmt->execute([$event_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
?>
