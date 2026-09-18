<?php
session_start();
include '../config/db.php';

// Check if form was submitted
if(!isset($_POST['username']) || !isset($_POST['password'])){
    die("Please enter username and password.");
}

$username = $_POST['username'];
$password = $_POST['password'];

// Prepare and execute query
try {
    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$admin){
        die("Username not found!");
    }

    if(password_verify($password, $admin['password'])){
        $_SESSION['admin'] = $admin['username'];
        header("Location: ../admin/dashboard.php");
        exit;
    } else {
        die("Password is incorrect!");
    }

} catch(PDOException $e){
    die("Database error: " . $e->getMessage());
}
?>