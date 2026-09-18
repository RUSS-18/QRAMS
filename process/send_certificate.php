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
    __DIR__ . '/../lib/certificate.php',
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
// VALIDATE INPUT
// ------------------------------------------------------------
$userId  = (int)($_POST['user_id']  ?? 0);
$eventId = (int)($_POST['event_id'] ?? 0);

if (!$userId || !$eventId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user or event.']);
    exit;
}

// ------------------------------------------------------------
// FETCH ATTENDEE + EVENT + SIGNATORY (using new schema columns)
// ------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT u.name, u.email,
               e.event_name, e.start_date, e.end_date,
               e.signatory_name, e.signatory_signature
        FROM attendance a
        JOIN users  u ON u.id = a.user_id
        JOIN events e ON e.id = a.event_id
        WHERE a.user_id = ? AND a.event_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[send_certificate] DB error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Attendee not found for this event.']);
    exit;
}

if (empty($row['email'])) {
    logSend($conn, $userId, $eventId, '', 'no_email');
    echo json_encode(['status' => 'error', 'message' => 'No email on file for this attendee.']);
    exit;
}

// ------------------------------------------------------------
// RESOLVE SIGNATURE IMAGE PATH
// ------------------------------------------------------------
$signaturePath = '';
if (!empty($row['signatory_signature'])) {
    $candidate = realpath(__DIR__ . '/../' . $row['signatory_signature']);
    if ($candidate && is_file($candidate) && is_readable($candidate)) {
        $signaturePath = $candidate;
    } else {
        error_log('[send_certificate] Signature missing: ' . $row['signatory_signature']);
    }
}

// ------------------------------------------------------------
// GENERATE PDF
// ------------------------------------------------------------
try {
    $pdfBytes = generateCertificate(
        $row['name'],
        $row['event_name'],
        $row['start_date'],
        $row['end_date'],
        $row['signatory_name'] ?? '',
        $signaturePath
    );

    if (empty($pdfBytes) || strpos($pdfBytes, '%PDF') !== 0) {
        throw new Exception('PDF output is invalid.');
    }

} catch (Throwable $e) {
    error_log('[send_certificate] PDF error: ' . $e->getMessage());
    logSend($conn, $userId, $eventId, $row['email'], 'failed');
    echo json_encode(['status' => 'error', 'message' => 'Could not generate the certificate: ' . $e->getMessage()]);
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
    $mail->addAddress($row['email'], $row['name']);

    $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $row['name']);
    $mail->addStringAttachment($pdfBytes, 'Certificate_' . $safeName . '.pdf');

    // Build date label for the email body
    if ($row['start_date'] === $row['end_date']) {
        $dateLabel = date('F j, Y', strtotime($row['start_date']));
    } else {
        $dateLabel = date('F j', strtotime($row['start_date']))
                   . ' – '
                   . date('F j, Y', strtotime($row['end_date']));
    }

    $safeUserName  = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    $safeEventName = htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8');

    $mail->isHTML(true);
    $mail->Subject = 'Your Certificate of Appearance — ' . $row['event_name'];
    $mail->Body = "
        <p>Dear <strong>{$safeUserName}</strong>,</p>
        <p>Thank you for attending <strong>{$safeEventName}</strong> held on {$dateLabel}.</p>
        <p>Your Certificate of Appearance is attached to this email as a PDF.</p>
        <p>Best regards,<br>QRAMS Team</p>
    ";
    $mail->AltBody = "Dear {$row['name']}, your Certificate of Appearance for {$row['event_name']} is attached.";

    $mail->send();

    logSend($conn, $userId, $eventId, $row['email'], 'sent');

    echo json_encode([
        'status'  => 'success',
        'message' => 'Sent to ' . $row['email']
    ]);

} catch (Exception $e) {
    error_log('[send_certificate] Mailer error: ' . $mail->ErrorInfo);
    logSend($conn, $userId, $eventId, $row['email'], 'failed');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Mailer error: ' . $mail->ErrorInfo
    ]);
}

// ------------------------------------------------------------
// LOG HELPER
// ------------------------------------------------------------
function logSend(PDO $conn, int $userId, int $eventId, string $email, string $status): void {
    try {
        $stmt = $conn->prepare("
            INSERT INTO certificates_sent (user_id, event_id, email, status, sent_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status  = VALUES(status),
                sent_at = NOW(),
                email   = VALUES(email)
        ");
        $stmt->execute([$userId, $eventId, $email, $status]);
    } catch (PDOException $e) {
        error_log('[send_certificate] logSend failed: ' . $e->getMessage());
    }
}