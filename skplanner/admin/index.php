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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
    <link rel="icon" href="../assets/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">

    <style>
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .form-control {
            padding-right: 44px;
            width: 100%;
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #888;
            font-size: 16px;
            padding: 4px;
            line-height: 1;
        }
        .toggle-password:hover,
        .toggle-password:focus {
            color: #333;
            outline: none;
        }
    </style>
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
            <label for="username"><i class="fas fa-user"></i> Username</label>
            <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password" title="Show password">
                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>

    <div style="text-align:center;margin-top:20px;">
        <a href="../index.php" style="font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Website</a>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    var input = document.getElementById('password');
    var icon  = document.getElementById('togglePasswordIcon');
    var show  = input.type === 'password';

    input.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    this.title = show ? 'Hide password' : 'Show password';
    input.focus();
});
</script>

</body>
</html>