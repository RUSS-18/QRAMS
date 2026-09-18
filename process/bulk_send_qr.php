<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

// ------------------------------------------------------------
// LOAD DEPENDENCIES
// ------------------------------------------------------------
$paths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../config/mail.php',
    __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php',
    __DIR__ . '/../lib/PHPMailer/src/SMTP.php',
    __DIR__ . '/../lib/PHPMailer/src/Exception.php',
];

foreach ($paths as $p) {
    if (!file_exists($p)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing file: ' . basename($p)]);
        exit;
    }
    require_once $p;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ------------------------------------------------------------
// BUILD RECIPIENT LIST
// ------------------------------------------------------------
$userIds   = $_POST['user_ids'] ?? [];
$q         = trim($_POST['q'] ?? '');
$fRole     = trim($_POST['role'] ?? '');
$fDept     = trim($_POST['department'] ?? '');
$fYear     = trim($_POST['year_level'] ?? '');

if (!empty($userIds) && is_array($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $sql = "SELECT id, name, email, department, year_level FROM users WHERE id IN ($placeholders)";
    $params = array_map('intval', $userIds);
} else {
    $sql = "SELECT id, name, email, department, year_level FROM users WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if ($fRole !== '') { $sql .= " AND role = ?";       $params[] = $fRole; }
    if ($fDept !== '') { $sql .= " AND department = ?"; $params[] = $fDept; }
    if ($fYear !== '') { $sql .= " AND year_level = ?"; $params[] = $fYear; }
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recipients)) {
    echo json_encode(['status' => 'error', 'message' => 'No recipients found.']);
    exit;
}

// ------------------------------------------------------------
// SEND EMAIL TO EACH
// ------------------------------------------------------------
$results = [];
$cfg = require __DIR__ . '/../config/mail.php';

foreach ($recipients as $r) {
    if (empty($r['email'])) {
        $results[] = ['name' => $r['name'], 'status' => 'error', 'message' => 'No email'];
        logQr($conn, $r['id'], '', 'no_email');
        continue;
    }

    // Fetch QR image
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode((string)$r['id']);
    $qrBytes = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'QRAMS/1.0',
        ]);
        $qrBytes = curl_exec($ch);
        curl_close($ch);
    } else {
        $qrBytes = @file_get_contents($apiUrl);
    }

    if (!$qrBytes || strlen($qrBytes) < 100) {
        $results[] = ['name' => $r['name'], 'status' => 'error', 'message' => 'QR download failed'];
        logQr($conn, $r['id'], $r['email'], 'failed');
        continue;
    }

    // Send email
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'];
        $mail->Port       = $cfg['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($r['email'], $r['name']);

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $r['name']);
        $mail->addStringAttachment($qrBytes, 'QR_' . $safeName . '.png');

        $safeUserName = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
        $safeDept     = htmlspecialchars($r['department'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeYear     = htmlspecialchars($r['year_level'] ?? '', ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Your QR Code — QRAMS Attendance System';
        $mail->Body = "
            <p>Dear <strong>{$safeUserName}</strong>,</p>
            <p>Your personal QR code is attached to this email as a PNG image.</p>
            <p><strong>How to use:</strong></p>
            <ol>
                <li>Save the attached image to your phone (or print it).</li>
                <li>Present it at the event scanner to record your attendance.</li>
            </ol>
            <p style='color:#888; font-size:13px;'>
                Department: {$safeDept}<br>
                Year Level: {$safeYear}
            </p>
            <p>Best regards,<br>QRAMS Team</p>
        ";
        $mail->AltBody = "Dear {$r['name']}, your QR code is attached.";

        $mail->send();

        logQr($conn, $r['id'], $r['email'], 'sent');
        $results[] = ['name' => $r['name'], 'email' => $r['email'], 'status' => 'success'];

    } catch (Exception $e) {
        error_log('[bulk_send_qr] Mailer error for ' . $r['email'] . ': ' . $mail->ErrorInfo);
        logQr($conn, $r['id'], $r['email'], 'failed');
        $results[] = ['name' => $r['name'], 'status' => 'error', 'message' => 'SMTP error'];
    }
}

echo json_encode([
    'status'  => 'success',
    'results' => $results
]);

// ------------------------------------------------------------
// LOG
// ------------------------------------------------------------
function logQr(PDO $conn, int $userId, string $email, string $status): void {
    try {
        $stmt = $conn->prepare("
            INSERT INTO qr_sent (user_id, email, status, sent_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status  = VALUES(status),
                sent_at = NOW(),
                email   = VALUES(email)
        ");
        $stmt->execute([$userId, $email, $status]);
    } catch (PDOException $e) {
        error_log('[bulk_send_qr] logQr failed: ' . $e->getMessage());
    }
}