<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}

include '../config/db.php';

// ------------------------------------------------------------
// 1. VALIDATE UPLOAD
// ------------------------------------------------------------
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['import_result'] = [
        'type'    => 'danger',
        'message' => 'No file was uploaded or the upload failed.'
    ];
    header("Location: ../admin/users.php");
    exit;
}

$file = $_FILES['csv_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'txt'])) {
    $_SESSION['import_result'] = [
        'type'    => 'danger',
        'message' => 'Only .csv files are accepted. In Excel, choose "Save As → CSV UTF-8".'
    ];
    header("Location: ../admin/users.php");
    exit;
}

// ------------------------------------------------------------
// 2. OPEN FILE + HANDLE BOM
// ------------------------------------------------------------
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    $_SESSION['import_result'] = [
        'type'    => 'danger',
        'message' => 'Could not open the uploaded file.'
    ];
    header("Location: ../admin/users.php");
    exit;
}

// Strip UTF-8 BOM if Excel added it
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

// ------------------------------------------------------------
// 3. PROCESS ROWS
// ------------------------------------------------------------
$imported = 0;
$skipped  = 0;
$errors   = [];
$rowNum   = 0;

$allowedRoles    = ['Student', 'Faculty', 'Guest'];
$allowedDepts    = ['CICS','CTED','CIT','Other'];
$allowedYears    = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty'];

$stmt = $conn->prepare("
    INSERT INTO users (name, role, department, year_level, email)
    VALUES (?, ?, ?, ?, ?)
");

$escape = '\\';
while (($row = fgetcsv($handle, 0, ',', '"', $escape)) !== false) {
    $rowNum++;
    if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) continue;
    if ($rowNum === 1 && strtolower(trim($row[0])) === 'name') continue;

    $name       = trim($row[0] ?? '');
    $role       = trim($row[1] ?? 'Student');
    $department = trim($row[2] ?? '');
    $yearLevel  = trim($row[3] ?? '');
    $email      = trim($row[4] ?? '');

    if ($name === '') { $skipped++; continue; }
    if (!in_array($role, $allowedRoles)) $role = 'Student';
    if ($department !== '' && !in_array($department, $allowedDepts)) $department = '';
    if ($yearLevel !== '' && !in_array($yearLevel, $allowedYears))  $yearLevel  = '';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

    $stmt->execute([
        $name, $role,
        $department ?: null,
        $yearLevel  ?: null,
        $email      ?: null
    ]);
    $imported++;
}
fclose($handle);

// ------------------------------------------------------------
// 4. BUILD RESULT MESSAGE
// ------------------------------------------------------------
$message = "✅ Imported: $imported participant(s).";
if ($skipped > 0) {
    $message .= " ⚠️ Skipped: $skipped.";
    if (count($errors) > 0 && count($errors) <= 5) {
        $message .= " (" . implode('; ', $errors) . ")";
    }
}

$_SESSION['import_result'] = [
    'type'    => $imported > 0 ? 'success' : 'warning',
    'message' => $message
];

header("Location: ../admin/users.php");
exit;