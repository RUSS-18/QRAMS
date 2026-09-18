<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ../index.php");
    exit;
}
include '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QRAMS Admin</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<style>
body { min-height: 100vh; display: flex; }
.sidebar { 
width: 220px; 
background-color: #343a40; 
color: white; 
min-height: 100vh;
}
.sidebar a { color: white; text-decoration: none; display: block; padding: 10px 20px; }
.sidebar a:hover { background-color: #495057; }
.main-content { flex: 1; padding: 20px; }
</style>
</head>
<body>
<div class="sidebar">
    <h3 class="text-center py-3">QRAMS</h3>
    <a href="dashboard.php">Dashboard</a>
    <a href="#" data-bs-toggle="modal" data-bs-target="#scanQrModal">Scan QR</a>
    <a href="attendance.php">Attendance</a>
    <a href="events.php">Events</a>
    <a href="users.php">Participants</a>
    <a href="certificates.php">Certificates</a>
    <a href="../auth/logout.php"
   data-confirm="Log out?"
   data-confirm-message="You will be returned to the login screen."
   data-confirm-button="Log Out"
   data-confirm-color="warning">
   Logout
</a>
</div>
<div class="main-content">

<?php include __DIR__ . '/modals/scan_qr.php';?>
<?php include __DIR__ . '/modals/scan_qr.php'; ?>
<?php include __DIR__ . '/modals/confirm_modal.php'; ?>