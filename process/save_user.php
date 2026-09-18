<?php
include '../config/db.php';

// DELETE
if (isset($_GET['delete_id'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['delete_id']]);
    header("Location: ../admin/users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $role       = $_POST['role'] ?? 'Student';
    $department = $_POST['department'] ?? null;
    $yearLevel  = $_POST['year_level'] ?? null;
    $email      = trim($_POST['email'] ?? '');

    if ($department === '') $department = null;
    if ($yearLevel === '')  $yearLevel  = null;
    if ($email === '')      $email      = null;

    if (!empty($_POST['id'])) {
        $stmt = $conn->prepare("
            UPDATE users
            SET name = ?, role = ?, department = ?, year_level = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $role, $department, $yearLevel, $email, (int)$_POST['id']]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users (name, role, department, year_level, email)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $role, $department, $yearLevel, $email]);
    }

    header("Location: ../admin/users.php");
    exit;
}
?>