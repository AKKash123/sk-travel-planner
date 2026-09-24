<?php
require_once __DIR__ . '/../config.php';

// If already logged in, redirect to dashboard
if (isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

 $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't trim/clean password

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Prepared statement — SQL injection safe
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Login success — regenerate session ID
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../logo.jpg">
        <link rel="icon" type="image/png" sizes="16x16" href="../logo.jpg">
        <link rel="icon" type="image/x-icon" href="../logo.jpg">

        <!-- Apple Touch Icon -->
        <link rel="apple-touch-icon" sizes="180x180" href="../logo.jpg">
</head>
<body class="login-page">

<div class="login-card">
    <div style="text-align:center;margin-bottom:24px;">
        <span class="brand-icon" style="display:inline-flex;width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));border-radius:14px;align-items:center;justify-content:center;font-size:28px;color:white;margin-bottom:12px;">
           <img src="../logo.jpg" alt="Logo">
        </span>
    </div>
    <h2>Admin Login</h2>
    <p><?= e(APP_NAME) ?></p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <?= csrfField() ?>
        <div class="form-group">
            <label><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
        </div>
        <div class="form-group">
            <label><i class="fas fa-lock"></i> Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>

    <div style="text-align:center;margin-top:20px;">
        <a href="../index.php" style="font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Website</a>
    </div>
</div>

</body>
</html>