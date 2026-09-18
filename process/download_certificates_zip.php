<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

set_error_handler(function ($severity, $message, $file, $line) {
    error_log("[QRAMS-ZIP] $message in $file on line $line");
    return true;
});

$requiredFiles = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../lib/certificate.php',
    __DIR__ . '/../lib/fpdf19/fpdf.php',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        die("Server configuration error: missing " . basename($file));
    }
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die("PHP Zip extension is not enabled.");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/certificate.php';

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId <= 0) { http_response_code(400); die("Invalid event ID."); }

try {
    $stmt = $conn->prepare("
        SELECT event_name, start_date, end_date, signatory_name, signatory_signature
        FROM events WHERE id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) { http_response_code(404); die("Event not found."); }
} catch (PDOException $e) {
    error_log("[QRAMS-ZIP] " . $e->getMessage());
    http_response_code(500); die("Database error.");
}

$signaturePath = '';
if (!empty($event['signatory_signature'])) {
    $candidate = realpath(__DIR__ . '/../' . $event['signatory_signature']);
    if ($candidate && is_file($candidate) && is_readable($candidate)) {
        $signaturePath = $candidate;
    }
}

try {
    $stmt = $conn->prepare("
        SELECT u.name FROM attendance a
        JOIN users u ON u.id = a.user_id
        WHERE a.event_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$eventId]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[QRAMS-ZIP] " . $e->getMessage());
    http_response_code(500); die("Database error.");
}

if (empty($attendees)) { http_response_code(404); die("No attendees for this event."); }

$tempDir = sys_get_temp_dir();
$zipPath = $tempDir . '/certificates_event' . $eventId . '_' . time() . '.zip';
$zip = new ZipArchive();
$openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($openResult !== true) {
    error_log("[QRAMS-ZIP] Failed to open: $openResult");
    http_response_code(500); die("Could not create the ZIP.");
}

$generated = 0;
$usedNames = [];

foreach ($attendees as $a) {
    $name = trim($a['name'] ?? '');
    if ($name === '') continue;

    try {
        $pdfBytes = generateCertificate(
            $name,
            $event['event_name'],
            $event['start_date'],
            $event['end_date'],
            $event['signatory_name'] ?? '',
            $signaturePath
        );

        if (empty($pdfBytes) || strpos($pdfBytes, '%PDF') !== 0) continue;

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
        $safe = trim($safe, '_') ?: 'attendee';
        $filename = "Certificate_{$safe}.pdf";
        $counter = 1;
        while (in_array($filename, $usedNames)) {
            $filename = "Certificate_{$safe}_{$counter}.pdf";
            $counter++;
        }
        $usedNames[] = $filename;

        $zip->addFromString($filename, $pdfBytes);
        $generated++;
    } catch (Throwable $e) {
        error_log("[QRAMS-ZIP] $name: " . $e->getMessage());
    }
}

$zip->close();

if ($generated === 0) {
    @unlink($zipPath);
    http_response_code(500);
    die("No certificates could be generated.");
}

if (ob_get_level()) ob_end_clean();

$safeEventName = preg_replace('/\W+/', '_', $event['event_name']);
$downloadName = 'Certificates_' . trim($safeEventName, '_') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');

$handle = fopen($zipPath, 'rb');
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);

@unlink($zipPath);
exit;