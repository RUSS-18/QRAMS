<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

$isSuperAdmin = ($_SESSION['admin_role'] ?? 'super_admin') === 'super_admin';
$adminId      = (int)$_SESSION['admin'];

$eventId = (int)($_GET['event_id'] ?? 0);
$date    = $_GET['date'] ?? '';
$dept    = $_GET['department'] ?? '';
$year    = $_GET['year_level'] ?? '';

$sql = "
    SELECT a.attendance_date, a.time_in, a.time_out,
           u.name, u.department, u.year_level, u.email,
           e.event_name,
           adm.full_name AS scanner_name, adm.username AS scanner_username
    FROM attendance a
    JOIN users  u ON u.id = a.user_id
    JOIN events e ON e.id = a.event_id
    LEFT JOIN admins adm ON adm.id = a.scanned_by
    WHERE 1=1
";
$params = [];

if (!$isSuperAdmin) {
    $sql .= " AND a.event_id IN (SELECT event_id FROM event_facilitators WHERE admin_id = ?)";
    $params[] = $adminId;
}

if ($eventId) { $sql .= " AND a.event_id = ?";        $params[] = $eventId; }
if ($date)    { $sql .= " AND a.attendance_date = ?"; $params[] = $date; }
if ($dept)    { $sql .= " AND u.department = ?";      $params[] = $dept; }
if ($year)    { $sql .= " AND u.year_level = ?";      $params[] = $year; }

$sql .= " ORDER BY a.attendance_date DESC, a.time_in DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (ob_get_level()) ob_end_clean();
error_reporting(E_ALL & ~E_DEPRECATED);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$escape = '\\';

fputcsv($output, [
    '#', 'Date', 'Name', 'Department', 'Year Level', 'Email',
    'Event', 'Time In', 'Time Out', 'Duration', 'Scanned By'
], ',', '"', $escape);

$i = 1;
foreach ($rows as $r) {
    $duration = '';
    if (!empty($r['time_out'])) {
        $mins = round((strtotime($r['time_out']) - strtotime($r['time_in'])) / 60);
        $h = floor($mins / 60);
        $m = $mins % 60;
        $duration = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    $scanner = $r['scanner_name'] ?: ($r['scanner_username'] ?: '');

    fputcsv($output, [
        $i++,
        $r['attendance_date'],
        $r['name'],
        $r['department'] ?? '',
        $r['year_level'] ?? '',
        $r['email'] ?? '',
        $r['event_name'],
        date('g:i A', strtotime($r['time_in'])),
        $r['time_out'] ? date('g:i A', strtotime($r['time_out'])) : 'Still in',
        $duration,
        $scanner
    ], ',', '"', $escape);
}

fclose($output);
exit;