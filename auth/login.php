<?php
session_start();

if (isset($_SESSION['admin'])) {
    header("Location: ../admin/dashboard.php");
    exit;
}

include '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($password, $row['password'])) {
            $error = 'Invalid username or password.';
        } elseif (isset($row['is_active']) && (int)$row['is_active'] !== 1) {
            $error = 'Your account is disabled. Contact the administrator.';
        } else {
            $_SESSION['admin']      = $row['id'];
            $_SESSION['admin_role'] = $row['role'] ?? 'super_admin';
            $_SESSION['admin_name'] = $row['full_name'] ?: $row['username'];

            header("Location: ../admin/dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — QRAMS</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<style>
    * { box-sizing: border-box; }

    html, body {
        height: 100%;
        margin: 0;
    }

    body {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #0d3b66 0%, #1e6091 100%);
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
        border: none;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        background: #fff;
    }

    .login-header {
        text-align: center;
        padding: 32px 24px 8px;
    }

    .login-header h1 {
        color: #0d3b66;
        font-weight: 700;
        font-size: 2rem;
        margin: 0;
        letter-spacing: 1px;
    }

    .login-header p {
        color: #6c757d;
        font-size: 0.85rem;
        margin: 4px 0 0;
    }

    .login-body {
        padding: 20px 32px 32px;
        position: relative;
    }

    /* Floating error — no reserved space, no layout shift */
    .alert-slot {
        position: absolute;
        top: -12px;
        left: 32px;
        right: 32px;
        margin: 0;
        pointer-events: none;
        z-index: 10;
    }

    .alert-slot .alert {
        width: 100%;
        margin: 0;
        padding: 8px 12px;
        font-size: 0.82rem;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.25);
        pointer-events: auto;
    }

    .form-label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #495057;
        margin-bottom: 4px;
    }

    .form-control {
        padding: 10px 14px;
        font-size: 0.95rem;
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }

    .btn-login {
        width: 100%;
        padding: 11px;
        font-weight: 600;
        font-size: 0.95rem;
        border-radius: 8px;
        background: #0d6efd;
        border: none;
        transition: background 0.15s;
    }

    .btn-login:hover {
        background: #0b5ed7;
    }

    .login-footer {
        text-align: center;
        padding: 0 32px 24px;
        font-size: 0.75rem;
        color: #adb5bd;
    }
</style>
</head>
<body>

<div class="card login-card">
    <div class="login-header">
        <h1>QRAMS</h1>
        <p>QR Attendance Management System</p>
    </div>

    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert-slot">
                <div class="alert alert-danger mb-0">
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input type="text"
                       id="username"
                       name="username"
                       class="form-control"
                       required
                       autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       required>
            </div>

            <button class="btn btn-primary btn-login" type="submit">
                Sign In
            </button>
        </form>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> QRAMS
    </div>
</div>

</body>
</html>