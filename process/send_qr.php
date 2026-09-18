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

$userId = (int)($_POST['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user ID.']);
    exit;
}

// ------------------------------------------------------------
// FETCH USER
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("SELECT name, email, department, year_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit;
}

if (empty($user['email'])) {
    logQr($conn, $userId, '', 'no_email');
    echo json_encode(['status' => 'error', 'message' => 'No email on file.']);
    exit;
}

// ------------------------------------------------------------
// FETCH QR IMAGE
// ------------------------------------------------------------
$apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' . urlencode((string)$userId);

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) $qrBytes = false;
} else {
    $qrBytes = @file_get_contents($apiUrl);
}

if (!$qrBytes || strlen($qrBytes) < 100) {
    logQr($conn, $userId, $user['email'], 'failed');
    echo json_encode(['status' => 'error', 'message' => 'Could not generate QR image.']);
    exit;
}

// ------------------------------------------------------------
// SEND EMAIL
// ------------------------------------------------------------
$mail = new PHPMailer(true);

try {
    $cfg = require __DIR__ . '/../config/mail.php';

    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = $cfg['encryption'];
    $mail->Port       = $cfg['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress($user['email'], $user['name']);

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $user['name']);
    $mail->addStringAttachment($qrBytes, 'QR_' . $safeName . '.png');

    $safeUserName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
    $safeDept     = htmlspecialchars($user['department'] ?? '', ENT_QUOTES, 'UTF-8');
    $safeYear     = htmlspecialchars($user['year_level'] ?? '', ENT_QUOTES, 'UTF-8');

    $mail->isHTML(true);
    $mail->Subject = 'Your QR Code — QRAMS Attendance System';
    $mail->Body = "
        <p>Dear <strong>{$safeUserName}</strong>,</p>
        <p>Your personal QR code for the QRAMS attendance system is attached to this email as a PNG image.</p>
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
    $mail->AltBody = "Dear {$user['name']}, your QR code for attendance is attached.";

    $mail->send();

    logQr($conn, $userId, $user['email'], 'sent');

    echo json_encode([
        'status'  => 'success',
        'message' => 'Sent to ' . $user['email']
    ]);

} catch (Exception $e) {
    error_log('[send_qr] Mailer error: ' . $mail->ErrorInfo);
    logQr($conn, $userId, $user['email'], 'failed');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Mailer error: ' . $mail->ErrorInfo
    ]);
}

// ------------------------------------------------------------
// LOG HELPER
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
        error_log('[send_qr] logQr failed: ' . $e->getMessage());
    }
}