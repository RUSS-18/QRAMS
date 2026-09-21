<?php
// TEMP: show all errors while debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header("Location: ../admin/dashboard.php");
    exit;
}

include '../config/db.php';

// ------------------------------------------------------------
// HELPER — flash message and redirect
// ------------------------------------------------------------
function flashAndRedirect($type, $message) {
    $_SESSION['facilitator_msg'] = ['type' => $type, 'message' => $message];
    header("Location: ../admin/facilitators.php");
    exit;
}

// ------------------------------------------------------------
// DELETE
// ------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    if ($id === (int)$_SESSION['admin']) {
        flashAndRedirect('danger', 'You cannot delete your own account.');
    }

    try {
        $stmt = $conn->prepare("SELECT role FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['role'] === 'super_admin') {
            $count = $conn->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'")->fetchColumn();
            if ($count <= 1) {
                flashAndRedirect('danger', 'Cannot delete the last Super Admin.');
            }
        }

        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$id]);

        flashAndRedirect('success', 'Facilitator deleted.');
    } catch (PDOException $e) {
        flashAndRedirect('danger', 'DB error (delete): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// ASSIGN EVENTS
// ------------------------------------------------------------
if (isset($_POST['assign_events'])) {
    $adminId  = (int)($_POST['assign_admin_id'] ?? 0);
    $eventIds = $_POST['event_ids'] ?? [];

    if ($adminId <= 0) {
        flashAndRedirect('danger', 'Invalid facilitator.');
    }

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("DELETE FROM event_facilitators WHERE admin_id = ?");
        $stmt->execute([$adminId]);

        if (!empty($eventIds)) {
            $stmt = $conn->prepare("INSERT INTO event_facilitators (event_id, admin_id) VALUES (?, ?)");
            foreach ($eventIds as $eid) {
                $stmt->execute([(int)$eid, $adminId]);
            }
        }

        $conn->commit();

        flashAndRedirect('success', 'Assigned ' . count($eventIds) . ' event(s).');
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        flashAndRedirect('danger', 'DB error (assign): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// CREATE
// ------------------------------------------------------------
if (isset($_POST['save'])) {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'facilitator';

    if ($username === '' || $password === '') {
        flashAndRedirect('danger', 'Username and password are required.');
    }

    if (strlen($password) < 6) {
        flashAndRedirect('danger', 'Password must be at least 6 characters.');
    }

    if (!in_array($role, ['super_admin', 'facilitator'], true)) {
        $role = 'facilitator';
    }

    try {
        // Username uniqueness
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            flashAndRedirect('danger', 'Username already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO admins (username, full_name, password, role, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$username, $fullName, $hash, $role]);

        flashAndRedirect('success', 'Facilitator "' . $username . '" created successfully.');
    } catch (PDOException $e) {
        flashAndRedirect('danger', 'DB error (create): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// UPDATE
// ------------------------------------------------------------
if (isset($_POST['update'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'facilitator';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($id <= 0 || $username === '') {
        flashAndRedirect('danger', 'Invalid input.');
    }

    if (!in_array($role, ['super_admin', 'facilitator'], true)) {
        $role = 'facilitator';
    }

    if ($id === (int)$_SESSION['admin'] && $isActive === 0) {
        flashAndRedirect('danger', 'You cannot disable your own account.');
    }

    try {
        // Username uniqueness
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            flashAndRedirect('danger', 'Username already taken.');
        }

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE admins
                SET username = ?, full_name = ?, password = ?, role = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullName, $hash, $role, $isActive, $id]);
        } else {
            $stmt = $conn->prepare("
                UPDATE admins
                SET username = ?, full_name = ?, role = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullName, $role, $isActive, $id]);
        }

        flashAndRedirect('success', 'Facilitator updated.');
    } catch (PDOException $e) {
        flashAndRedirect('danger', 'DB error (update): ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// FALLBACK
// ------------------------------------------------------------
flashAndRedirect('danger', 'Invalid request.');