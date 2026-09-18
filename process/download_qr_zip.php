<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}

if (!class_exists('ZipArchive')) {
    die("PHP Zip extension is not enabled.");
}

include '../config/db.php';

// ------------------------------------------------------------
// BUILD QUERY
// ------------------------------------------------------------
$ids   = $_GET['ids'] ?? [];
$q     = trim($_GET['q'] ?? '');
$role  = trim($_GET['role'] ?? '');
$dept  = trim($_GET['department'] ?? '');
$year  = trim($_GET['year_level'] ?? '');

$sql = "SELECT id, name, department, year_level FROM users WHERE 1=1";
$params = [];

if (!empty($ids) && is_array($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql .= " AND id IN ($ph)";
    $params = array_map('intval', $ids);
} else {
    if ($q !== '') {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($role !== '') { $sql .= " AND role = ?";       $params[] = $role; }
    if ($dept !== '') { $sql .= " AND department = ?"; $params[] = $dept; }
    if ($year !== '') { $sql .= " AND year_level = ?"; $params[] = $year; }
}

$sql .= " ORDER BY department, year_level, name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) die("No participants match.");

// ------------------------------------------------------------
// BUILD ZIP
// ------------------------------------------------------------
$zipPath = sys_get_temp_dir() . '/qr_codes_' . time() . '.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$usedNames = [];

foreach ($users as $u) {
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode((string)$u['id']);
    $img = @file_get_contents($apiUrl);
    if (!$img || strlen($img) < 100) continue;

    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $u['name']);
    $safe = trim($safe, '_') ?: 'user' . $u['id'];
    $filename = 'QR_' . $safe . '_' . $u['id'] . '.png';

    $counter = 1;
    while (in_array($filename, $usedNames)) {
        $filename = 'QR_' . $safe . '_' . $u['id'] . '_' . $counter . '.png';
        $counter++;
    }
    $usedNames[] = $filename;

    $zip->addFromString($filename, $img);
}

$zip->close();

if (ob_get_level()) ob_end_clean();

$downloadName = 'QR_Codes_' . date('Ymd_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($zipPath));

readfile($zipPath);
@unlink($zipPath);
exit;