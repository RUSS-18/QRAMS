<?php
session_start();

// Already logged in? Go to dashboard.
if (isset($_SESSION['admin'])) {
    header("Location: admin/dashboard.php");
    exit;
}

// Otherwise, go to login.
header("Location: auth/login.php");
exit;