<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: ../index.php"); exit; }
if (ob_get_level()) ob_end_clean();
error_reporting(E_ALL & ~E_DEPRECATED);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="participants_template.csv"');

$output = fopen('php://output', 'w');
fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

$escape = '\\';
fputcsv($output, ['name', 'role', 'department', 'year_level', 'email'], ',', '"', $escape);
fputcsv($output, ['Juan Dela Cruz', 'Student', 'CICS', '3rd Year', 'juan@example.com'], ',', '"', $escape);
fputcsv($output, ['Maria Santos',   'Faculty', 'CBA',  'Faculty',  'maria@example.com'], ',', '"', $escape);

fclose($output);
exit;