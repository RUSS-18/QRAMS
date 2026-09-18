<?php
require_once __DIR__ . '/fpdf19/fpdf.php';

/**
 * Generate a Certificate of Appearance PDF.
 * Supports both single-day and multi-day events.
 * Renders an optional signature image above a single centered signature line.
 *
 * @param string $userName      Recipient's full name
 * @param string $eventName     Name of the event
 * @param string $startDate     Start date (YYYY-MM-DD)
 * @param string $endDate       End date (YYYY-MM-DD). Defaults to start date.
 * @param string $signatoryName Name shown below the signature line
 * @param string $signaturePath Absolute path to the signature image (optional)
 * @return string               Raw PDF bytes
 */
function generateCertificate(
    string $userName,
    string $eventName,
    string $startDate,
    string $endDate = '',
    string $signatoryName = '',
    string $signaturePath = ''
): string {
    if ($endDate === '') $endDate = $startDate;

    // Format the date label
    if ($startDate === $endDate) {
        $dateLine = date('F j, Y', strtotime($startDate));
    } else {
        $dateLine = date('F j', strtotime($startDate))
                  . ' – '
                  . date('F j, Y', strtotime($endDate));
    }

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);

    // Outer border
    $pdf->SetDrawColor(20, 60, 120);
    $pdf->SetLineWidth(3);
    $pdf->Rect(10, 10, 277, 190);

    // Inner border
    $pdf->SetDrawColor(180, 200, 230);
    $pdf->SetLineWidth(1);
    $pdf->Rect(15, 15, 267, 180);

    // Title
    $pdf->SetY(30);
    $pdf->SetFont('Helvetica', 'B', 36);
    $pdf->SetTextColor(20, 60, 120);
    $pdf->Cell(0, 15, 'CERTIFICATE', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 18);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 10, 'of Appearance', 0, 1, 'C');

    $pdf->Ln(6);

    // Intro
    $pdf->SetFont('Helvetica', '', 14);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(0, 8, 'This certificate is proudly presented to', 0, 1, 'C');

    $pdf->Ln(2);

    // Recipient name
    $pdf->SetFont('Helvetica', 'B', 28);
    $pdf->SetTextColor(20, 60, 120);
    $pdf->Cell(0, 14, $userName, 0, 1, 'C');

    // Underline for name
    $pdf->SetDrawColor(20, 60, 120);
    $pdf->SetLineWidth(0.5);
    $nameWidth = $pdf->GetStringWidth($userName) + 40;
    $x = (297 - $nameWidth) / 2;
    $pdf->Line($x, $pdf->GetY() + 2, $x + $nameWidth, $pdf->GetY() + 2);

    $pdf->Ln(6);

    // Event details
    $pdf->SetFont('Helvetica', '', 13);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(0, 7, 'for actively participating in', 0, 1, 'C');

    $pdf->Ln(1);
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 9, $eventName, 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 7, 'held on ' . $dateLine, 0, 1, 'C');

    // ------------------------------------------------------------
    // Signature block (centered)
    // ------------------------------------------------------------
    $pdf->Ln(15);

    $lineWidth   = 80;
    $lineCenterX = 148.5;
    $lineStartX  = $lineCenterX - ($lineWidth / 2);
    $lineEndX    = $lineCenterX + ($lineWidth / 2);

    // Signature image above the line (if provided)
    if (!empty($signaturePath) && file_exists($signaturePath)) {
        $imgW = 55;
        $imgH = 20;
        $imgX = $lineCenterX - ($imgW / 2);
        $imgY = $pdf->GetY() - 6;

        try {
            $imgInfo = @getimagesize($signaturePath);
            $type    = $imgInfo ? $imgInfo[2] : null;

            if ($type === IMAGETYPE_PNG) {
                $pdf->Image($signaturePath, $imgX, $imgY, $imgW, $imgH, 'PNG');
            } elseif ($type === IMAGETYPE_JPEG) {
                $pdf->Image($signaturePath, $imgX, $imgY, $imgW, $imgH, 'JPG');
            } elseif ($type === IMAGETYPE_GIF) {
                $pdf->Image($signaturePath, $imgX, $imgY, $imgW, $imgH, 'GIF');
            }
        } catch (Throwable $e) {
            // Silently skip invalid images
        }
    }

    // Signature line
    $pdf->SetDrawColor(60, 60, 60);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($lineStartX, $pdf->GetY() + 18, $lineEndX, $pdf->GetY() + 18);

    // Signatory name below the line
    $pdf->SetY($pdf->GetY() + 20);

    $displayName = trim($signatoryName) !== ''
        ? strtoupper($signatoryName)
        : 'AUTHORIZED SIGNATORY';

    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 6, $displayName, 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(110, 110, 110);
    $pdf->Cell(0, 5, 'Authorized Signatory', 0, 1, 'C');

    // Footer with certificate ID
    $pdf->SetY(190);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5,
        'Certificate ID: ' . strtoupper(substr(md5($userName . $eventName . $startDate), 0, 12)) .
        '  |  Generated on ' . date('Y-m-d H:i'),
        0, 1, 'C');

    return $pdf->Output('S');
}