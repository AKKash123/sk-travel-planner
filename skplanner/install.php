<?php
// ============================================
// SK Travel Planner — Admin Credential Setup
// Allows creating or updating admin username/password
// DELETE THIS FILE after setup is complete
// ============================================

// Session & security headers
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// ============================================
// Escape helper — XSS prevention
// ============================================
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ============================================
// CSRF Token
// ============================================
function generateCSRF() {
    if (empty($_SESSION['setup_csrf'])) {
        $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['setup_csrf'];
}

function verifyCSRF($token) {
    if (empty($_SESSION['setup_csrf']) || empty($token)) return false;
    return hash_equals($_SESSION['setup_csrf'], $token);
}

// ============================================
// Database Connection
// ============================================
 $db_host = 'localhost';
 $db_name = 'sk_travel_planner';
 $db_user = 'root';
 $db_pass = '';

// Try loading from config.php
 $configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    // Extract DB constants from config without executing full file
    $configContent = file_get_contents($configPath);
    if (preg_match("/define\('DB_HOST',\s*'([^']*)'\)/", $configContent, $m)) $db_host = $m[1];
    if (preg_match("/define\('DB_NAME',\s*'([^']*)'\)/", $configContent, $m)) $db_name = $m[1];
    if (preg_match("/define\('DB_USER',\s*'([^']*)'\)/", $configContent, $m)) $db_user = $m[1];
    if (preg_match("/define\('DB_PASS',\s*'([^']*)'\)/", $configContent, $m)) $db_pass = $m[1];
}

 $pdo = null;
 $dbConnected = false;
 $dbError = '';
 $adminExists = false;
 $adminUsernames = [];

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $dbConnected = true;

    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() > 0) {
        // Get all admin usernames
        $stmt = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id ASC");
        $admins = $stmt->fetchAll();
        $adminExists = count($admins) > 0;
        $adminUsernames = array_column($admins, 'username');
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// ============================================
// Form Processing
// ============================================
 $errors       = [];
 $success      = '';
 $formStep     = 'choose'; // choose | create | update | result

// Determine mode
if (!$adminExists) {
    $formStep = 'create'; // No admin yet — go straight to create
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {

    // CSRF verification
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {

        $action = $_POST['form_action'];

        // ============================================
        // ACTION: Choose mode (create new or update existing)
        // ============================================
        if ($action === 'choose_create') {
            $formStep = 'create';
        }

        elseif ($action === 'choose_update') {
            $formStep = 'update';
        }

        // ============================================
        // ACTION: Create new admin
        // ============================================
        elseif ($action === 'create_admin') {
            if (!$dbConnected) {
                $errors[] = 'Database not connected. Check config.php settings.';
            } else {
                $new_username  = trim($_POST['new_username'] ?? '');
                $new_password  = $_POST['new_password'] ?? '';
                $confirm_pass  = $_POST['confirm_password'] ?? '';

                // Validate username
                if (strlen($new_username) < 3) {
                    $errors[] = 'Username must be at least 3 characters.';
                } elseif (strlen($new_username) > 50) {
                    $errors[] = 'Username must not exceed 50 characters.';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
                    $errors[] = 'Username can only contain letters, numbers, and underscores.';
                } else {
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
                    $stmt->execute([$new_username]);
                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = 'Username "' . e($new_username) . '" already exists. Choose a different one.';
                    }
                }

                // Validate password
                if (strlen($new_password) < 8) {
                    $errors[] = 'Password must be at least 8 characters.';
                } elseif (strlen($new_password) > 128) {
                    $errors[] = 'Password must not exceed 128 characters.';
                }

                // Password confirmation
                if ($new_password !== $confirm_pass) {
                    $errors[] = 'Passwords do not match.';
                }

                // All valid — create admin
                if (empty($errors)) {
                    try {
                        // Ensure table exists
                        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `username` VARCHAR(50) NOT NULL UNIQUE,
                            `password` VARCHAR(255) NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                        // Hash password with bcrypt
                        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);

                        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
                        $stmt->execute([$new_username, $hashedPassword]);

                        $success = 'Admin account created successfully! Username: ' . e($new_username);
                        $formStep = 'result';

                        $_SESSION['setup_result'] = 'created';
                        $_SESSION['setup_username'] = $new_username;

                        // Refresh admin list
                        $adminExists = true;
                        $stmt = $pdo->query("SELECT username FROM admin_users ORDER BY id ASC");
                        $adminUsernames = array_column($stmt->fetchAll(), 'username');

                    } catch (PDOException $e) {
                        $errors[] = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $formStep = 'create';
                }
            }
        }

        // ============================================
        // ACTION: Update existing admin
        // ============================================
        elseif ($action === 'update_admin') {
            if (!$dbConnected) {
                $errors[] = 'Database not connected.';
            } else {
                $target_user  = trim($_POST['target_user'] ?? '');
                $current_pass = $_POST['current_password'] ?? '';
                $new_username  = trim($_POST['new_username'] ?? '');
                $new_password  = $_POST['new_password'] ?? '';
                $confirm_pass  = $_POST['confirm_password'] ?? '';
                $change_user  = isset($_POST['change_username']);
                $change_pass  = isset($_POST['change_password']);

                // Verify target user exists
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([$target_user]);
                $targetAdmin = $stmt->fetch();

                if (!$targetAdmin) {
                    $errors[] = 'Selected admin account not found.';
                } else {
                    // Verify current password
                    if (!password_verify($current_pass, $targetAdmin['password'])) {
                        $errors[] = 'Current password is incorrect.';
                    }
                }

                // Validate new username (if changing)
                if ($change_user && empty($errors)) {
                    if (strlen($new_username) < 3) {
                        $errors[] = 'New username must be at least 3 characters.';
                    } elseif (strlen($new_username) > 50) {
                        $errors[] = 'New username must not exceed 50 characters.';
                    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
                        $errors[] = 'New username can only contain letters, numbers, and underscores.';
                    } elseif ($new_username !== $target_user) {
                        // Check if new username already taken
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ? AND id != ?");
                        $stmt->execute([$new_username, $targetAdmin['id']]);
                        if ($stmt->fetchColumn() > 0) {
                            $errors[] = 'Username "' . e($new_username) . '" is already taken.';
                        }
                    } else {
                        $errors[] = 'New username is the same as current username.';
                    }
                }

                // Validate new password (if changing)
                if ($change_pass && empty($errors)) {
                    if (strlen($new_password) < 8) {
                        $errors[] = 'New password must be at least 8 characters.';
                    } elseif (strlen($new_password) > 128) {
                        $errors[] = 'New password must not exceed 128 characters.';
                    }
                    if ($new_password !== $confirm_pass) {
                        $errors[] = 'New passwords do not match.';
                    }
                }

                // Must change at least one thing
                if (!$change_user && !$change_pass) {
                    $errors[] = 'Select at least one field to change (username or password).';
                }

                // All valid — update admin
                if (empty($errors)) {
                    try {
                        if ($change_user && $change_pass) {
                            // Update both
                            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
                            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, password = ? WHERE id = ?");
                            $stmt->execute([$new_username, $hashedPassword, $targetAdmin['id']]);
                            $success = 'Username and password updated successfully!';
                            $_SESSION['setup_username'] = $new_username;
                        } elseif ($change_user) {
                            // Update username only
                            $stmt = $pdo->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
                            $stmt->execute([$new_username, $targetAdmin['id']]);
                            $success = 'Username updated successfully!';
                            $_SESSION['setup_username'] = $new_username;
                        } elseif ($change_pass) {
                            // Update password only
                            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
                            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashedPassword, $targetAdmin['id']]);
                            $success = 'Password updated successfully!';
                            $_SESSION['setup_username'] = $target_user;
                        }

                        $formStep = 'result';
                        $_SESSION['setup_result'] = 'updated';

                        // Refresh admin list
                        $stmt = $pdo->query("SELECT username FROM admin_users ORDER BY id ASC");
                        $adminUsernames = array_column($stmt->fetchAll(), 'username');

                    } catch (PDOException $e) {
                        $errors[] = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $formStep = 'update';
                }
            }
        }

        // ============================================
        // ACTION: Delete admin
        // ============================================
        elseif ($action === 'delete_admin') {
            if (!$dbConnected) {
                $errors[] = 'Database not connected.';
            } else {
                $target_user  = trim($_POST['target_user'] ?? '');
                $current_pass = $_POST['delete_password'] ?? '';

                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([$target_user]);
                $targetAdmin = $stmt->fetch();

                if (!$targetAdmin) {
                    $errors[] = 'Admin account not found.';
                } elseif (!password_verify($current_pass, $targetAdmin['password'])) {
                    $errors[] = 'Current password is incorrect. Deletion aborted.';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                        $stmt->execute([$targetAdmin['id']]);

                        $success = 'Admin account "' . e($target_user) . '" deleted successfully.';
                        $formStep = 'result';
                        $_SESSION['setup_result'] = 'deleted';

                        // Refresh
                        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
                        $adminExists = $stmt->fetchColumn() > 0;
                        $stmt = $pdo->query("SELECT username FROM admin_users ORDER BY id ASC");
                        $adminUsernames = array_column($stmt->fetchAll(), 'username');

                        if (!$adminExists) $formStep = 'create';

                    } catch (PDOException $e) {
                        $errors[] = 'Delete failed: ' . $e->getMessage();
                    }
                }
            }
        }

        // ============================================
        // ACTION: Reset to choose screen
        // ============================================
        elseif ($action === 'back_to_choose') {
            $formStep = 'choose';
        }
    }
}

// Regenerate CSRF token for next form
 $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
 $csrfToken = $_SESSION['setup_csrf'];

// Check if setup file warning needed
 $setupFileExists = file_exists(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Credential Setup — SK Travel Planner</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary:#0D7377;--primary-dark:#095558;--primary-light:#14A3A8;
            --accent:#E8912D;--accent-dark:#C75B2A;--accent-light:#F5B041;
            --dark:#1B2838;--text:#2D2D2D;--text-muted:#6B7280;
            --light:#F7F3ED;--light-darker:#EDE7DB;--white:#FFFFFF;
            --success:#2E8B57;--danger:#DC3545;--warning:#D4A017;--border:#D1CBC0;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Source Sans 3',sans-serif;
            background:linear-gradient(135deg,#1B2838 0%,#095558 50%,#0D7377 100%);
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            padding:30px 20px;
        }
        .card{
            background:var(--white);border-radius:16px;width:100%;max-width:600px;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;
        }
        .card-header{
            background:linear-gradient(135deg,var(--dark),var(--primary-dark));
            padding:28px 36px;text-align:center;
        }
        .card-header .icon{
            display:inline-flex;align-items:center;justify-content:center;
            width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));
            border-radius:14px;font-size:26px;color:white;margin-bottom:12px;
        }
        .card-header h1{font-family:'Playfair Display',serif;color:var(--white);font-size:24px;margin-bottom:4px;}
        .card-header p{color:rgba(255,255,255,0.6);font-size:14px;}
        .card-body{padding:32px 36px;}
        .card-body h2{font-size:20px;margin-bottom:8px;color:var(--dark);}
        .card-body .desc{color:var(--text-muted);font-size:15px;margin-bottom:24px;line-height:1.6;}

        /* DB Status */
        .db-status{
            display:flex;align-items:center;gap:10px;padding:12px 16px;
            border-radius:8px;margin-bottom:24px;font-size:14px;font-weight:500;
        }
        .db-status.ok{background:rgba(46,139,87,0.08);color:#155724;}
        .db-status.ok i{color:var(--success);}
        .db-status.fail{background:rgba(220,53,69,0.08);color:#721c24;}
        .db-status.fail i{color:var(--danger);}

        /* Admin list */
        .admin-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
        .admin-chip{
            display:inline-flex;align-items:center;gap:6px;
            padding:6px 14px;background:var(--light);border-radius:20px;
            font-size:13px;font-weight:600;color:var(--dark);
        }
        .admin-chip i{color:var(--primary);}

        /* Choice cards */
        .choice-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}
        .choice-card{
            background:var(--light);border:2px solid transparent;border-radius:12px;
            padding:24px 20px;text-align:center;cursor:pointer;
            transition:all 0.3s ease;
        }
        .choice-card:hover{border-color:var(--primary);background:rgba(13,115,119,0.04);transform:translateY(-2px);}
        .choice-card i{font-size:32px;color:var(--primary);margin-bottom:12px;display:block;}
        .choice-card h3{font-size:16px;margin-bottom:6px;color:var(--dark);}
        .choice-card p{font-size:13px;color:var(--text-muted);}

        /* Form */
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:14px;color:var(--dark);}
        .form-group label .req{color:var(--danger);margin-left:2px;}
        .form-group .hint{font-size:12px;color:var(--text-muted);margin-top:4px;}
        .form-control{
            width:100%;padding:12px 16px;border:2px solid var(--border);border-radius:8px;
            font-family:'Source Sans 3',sans-serif;font-size:15px;transition:border-color 0.3s;background:var(--white);
        }
        .form-control:focus{outline:none;border-color:var(--primary);}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}

        /* Select */
        select.form-control{cursor:pointer;appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%236B7280'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 14px center;padding-right:36px;
        }

        /* Checkbox toggle */
        .check-toggle{display:flex;align-items:center;gap:10px;margin-bottom:16px;cursor:pointer;user-select:none;}
        .check-toggle input[type="checkbox"]{display:none;}
        .check-box{
            width:22px;height:22px;border:2px solid var(--border);border-radius:6px;
            display:flex;align-items:center;justify-content:center;transition:all 0.2s;flex-shrink:0;
        }
        .check-box i{font-size:12px;color:transparent;transition:color 0.2s;}
        .check-toggle input:checked + .check-box{background:var(--primary);border-color:var(--primary);}
        .check-toggle input:checked + .check-box i{color:white;}
        .check-toggle .check-label{font-size:14px;font-weight:600;color:var(--dark);}

        /* Password strength */
        .pw-strength{display:flex;gap:4px;align-items:center;margin-top:8px;}
        .pw-bar{flex:1;height:4px;border-radius:2px;background:var(--light-darker);transition:background 0.3s;}
        .pw-label{font-size:12px;font-weight:600;min-width:60px;text-align:right;}

        /* Buttons */
        .btn-row{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;}
        .btn{
            display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border:none;border-radius:8px;
            font-family:'Source Sans 3',sans-serif;font-size:15px;font-weight:600;
            cursor:pointer;transition:all 0.3s;text-decoration:none;
        }
        .btn-primary{background:var(--primary);color:var(--white);}
        .btn-primary:hover{background:var(--primary-dark);color:var(--white);transform:translateY(-1px);}
        .btn-accent{background:var(--accent);color:var(--white);}
        .btn-accent:hover{background:var(--accent-dark);color:var(--white);transform:translateY(-1px);}
        .btn-outline{background:transparent;color:var(--primary);border:2px solid var(--primary);}
        .btn-outline:hover{background:var(--primary);color:var(--white);}
        .btn-danger{background:var(--danger);color:var(--white);}
        .btn-danger:hover{background:#b02a37;color:var(--white);}
        .btn-success{background:var(--success);color:var(--white);}
        .btn-success:hover{background:#247247;color:var(--white);}
        .btn-lg{padding:14px 32px;font-size:16px;}
        .btn-sm{padding:8px 16px;font-size:13px;}

        /* Alerts */
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:16px;font-weight:500;font-size:14px;}
        .alert-success{background:#d4edda;color:#155724;border-left:4px solid var(--success);}
        .alert-danger{background:#f8d7da;color:#721c24;border-left:4px solid var(--danger);}
        .alert-warning{background:#fff3cd;color:#856404;border-left:4px solid var(--warning);}
        .alert-info{background:#d1ecf1;color:#0c5460;border-left:4px solid #17a2b8;}

        /* Result card */
        .result-icon{
            width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;
            font-size:36px;color:white;margin:0 auto 20px;
            animation:popIn 0.6s cubic-bezier(0.175,0.885,0.32,1.275) both;
        }
        .result-icon.created{background:linear-gradient(135deg,var(--success),#247247);}
        .result-icon.updated{background:linear-gradient(135deg,var(--primary),var(--primary-light));}
        .result-icon.deleted{background:linear-gradient(135deg,var(--danger),#b02a37);}
        @keyframes popIn{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}

        .cred-box{
            background:var(--dark);border-radius:8px;padding:20px;margin:16px 0;
            color:rgba(255,255,255,0.9);font-family:'Courier New',monospace;font-size:14px;line-height:1.8;
        }
        .cred-box .lbl{color:var(--accent-light);font-weight:700;}

        .warn-box{
            background:rgba(220,53,69,0.06);border:2px solid rgba(220,53,69,0.2);border-radius:8px;
            padding:16px 20px;margin-top:20px;display:flex;align-items:flex-start;gap:12px;
        }
        .warn-box i{color:var(--danger);font-size:20px;margin-top:2px;flex-shrink:0;}
        .warn-box p{font-size:14px;color:#721c24;line-height:1.6;}

        .divider{border:none;border-top:1px solid var(--light-darker);margin:24px 0;}

        /* Footer */
        .card-footer{
            text-align:center;padding:16px 36px 24px;
            border-top:1px solid var(--light-darker);font-size:14px;
        }
        .card-footer a{color:var(--primary);font-weight:600;text-decoration:none;}
        .card-footer a:hover{color:var(--accent);}

        @media(max-width:500px){
            .card-body{padding:24px 20px;} .card-header{padding:24px 20px;}
            .form-row,.form-row-3,.choice-grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>

<div class="card">

    <!-- Header -->
    <div class="card-header">
        <div class="icon"><i class="fas fa-user-shield"></i></div>
        <h1>Admin Credential Setup</h1>
        <p>SK Travel Planner</p>
    </div>

    <div class="card-body">

        <!-- DB Status -->
        <?php if ($dbConnected): ?>
            <div class="db-status ok">
                <i class="fas fa-check-circle"></i>
                Connected to <strong><?= e($db_name) ?></strong> at <?= e($db_host) ?>
            </div>
        <?php else: ?>
            <div class="db-status fail">
                <i class="fas fa-times-circle"></i>
                Database connection failed: <?= e($dbError) ?>
            </div>
            <div class="alert alert-danger">
                Fix your database connection in <code>config.php</code> before proceeding.
            </div>
        <?php endif; ?>

        <!-- Existing Admins -->
        <?php if ($dbConnected && $adminExists): ?>
            <div style="margin-bottom:24px;">
                <label style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;display:block;">
                    Existing Admin Accounts
                </label>
                <div class="admin-chips">
                    <?php foreach ($adminUsernames as $u): ?>
                        <span class="admin-chip"><i class="fas fa-user"></i> <?= e($u) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Success -->
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>

        <?php if (!$dbConnected): ?>
            <!-- No forms if DB is down -->

        <!-- ============================================
             CHOOSE MODE
             ============================================ -->
        <?php elseif ($formStep === 'choose'): ?>

            <h2>What would you like to do?</h2>
            <p class="desc">Select an action below to manage admin credentials.</p>

            <div class="choice-grid">
                <!-- Create New -->
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="choose_create">
                    <button type="submit" class="choice-card" style="border:none;cursor:pointer;width:100%;">
                        <i class="fas fa-user-plus"></i>
                        <h3>Create New Admin</h3>
                        <p>Add a new administrator account</p>
                    </button>
                </form>

                <!-- Update Existing -->
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="choose_update">
                    <button type="submit" class="choice-card" style="border:none;cursor:pointer;width:100%;">
                        <i class="fas fa-user-edit"></i>
                        <h3>Update Existing</h3>
                        <p>Change username or password</p>
                    </button>
                </form>
            </div>

        <!-- ============================================
             CREATE NEW ADMIN
             ============================================ -->
        <?php elseif ($formStep === 'create'): ?>

            <h2><i class="fas fa-user-plus" style="color:var(--primary);"></i> Create New Admin</h2>
            <p class="desc">Set a username and password for the new administrator account. The password is hashed with bcrypt before storage — it is never saved in plaintext.</p>

            <form method="POST" id="create-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_action" value="create_admin">

                <!-- Username -->
                <div class="form-group">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="new_username" class="form-control"
                           value="<?= e($_POST['new_username'] ?? '') ?>"
                           placeholder="e.g. admin, superadmin, john_doe"
                           minlength="3" maxlength="50"
                           pattern="[a-zA-Z0-9_]+" required>
                    <p class="hint">Letters, numbers, and underscores only. Min 3, max 50 characters.</p>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label>Password <span class="req">*</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="password" name="new_password" id="new_password" class="form-control"
                               style="flex:1;"
                               placeholder="Minimum 8 characters"
                               minlength="8" maxlength="128" required>
                        <button type="button" class="btn btn-accent btn-sm" onclick="openGenerator()" style="white-space:nowrap;">
                            <i class="fas fa-key"></i> Generate
                        </button>
                    </div>
                    <div class="pw-strength" id="pw-strength" style="display:none;">
                        <div class="pw-bar" id="pw-bar-1"></div>
                        <div class="pw-bar" id="pw-bar-2"></div>
                        <div class="pw-bar" id="pw-bar-3"></div>
                        <div class="pw-bar" id="pw-bar-4"></div>
                        <span class="pw-label" id="pw-label"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label>Confirm Password <span class="req">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           placeholder="Re-enter the same password" required>
                    <p class="hint" id="match-hint" style="display:none;"></p>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Security:</strong> Password is hashed with bcrypt (cost 12). It cannot be recovered if forgotten — only reset.
                </div>

                <div class="btn-row">
                    <?php if ($adminExists): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="back_to_choose">
                            <button type="submit" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</button>
                        </form>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Create Admin</button>
                </div>
            </form>

        <!-- ============================================
             UPDATE EXISTING ADMIN
             ============================================ -->
        <?php elseif ($formStep === 'update'): ?>

            <h2><i class="fas fa-user-edit" style="color:var(--primary);"></i> Update Admin Credentials</h2>
            <p class="desc">Select an existing admin account, verify the current password, then set a new username and/or password.</p>

            <form method="POST" id="update-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_action" value="update_admin">

                <!-- Select Admin -->
                <div class="form-group">
                    <label>Select Admin Account <span class="req">*</span></label>
                    <select name="target_user" class="form-control" required>
                        <option value="">— Choose an admin —</option>
                        <?php foreach ($adminUsernames as $u): ?>
                            <option value="<?= e($u) ?>" <?= (isset($_POST['target_user']) && $_POST['target_user']===$u) ? 'selected' : '' ?>>
                                <?= e($u) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Current Password -->
                <div class="form-group">
                    <label>Current Password <span class="req">*</span></label>
                    <input type="password" name="current_password" class="form-control"
                           placeholder="Enter current password to verify identity" required>
                    <p class="hint">Required for security — proves you own this account.</p>
                </div>

                <hr class="divider">

                <!-- What to change -->
                <h3 style="font-size:16px;margin-bottom:16px;color:var(--dark);">What do you want to change?</h3>

                <label class="check-toggle">
                    <input type="checkbox" name="change_username" id="chk_username" value="1"
                           <?= isset($_POST['change_username']) ? 'checked' : '' ?>
                           onchange="toggleUsernameField()">
                    <span class="check-box"><i class="fas fa-check"></i></span>
                    <span class="check-label">Change Username</span>
                </label>

                <!-- New Username -->
                <div class="form-group" id="username-group" style="<?= isset($_POST['change_username']) ? '' : 'display:none;' ?>">
                    <label>New Username <span class="req">*</span></label>
                    <input type="text" name="new_username" class="form-control"
                           value="<?= e($_POST['new_username'] ?? '') ?>"
                           placeholder="Enter new username"
                           minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+">
                </div>

                <label class="check-toggle">
                    <input type="checkbox" name="change_password" id="chk_password" value="1"
                           <?= isset($_POST['change_password']) ? 'checked' : '' ?>
                           onchange="togglePasswordField()">
                    <span class="check-box"><i class="fas fa-check"></i></span>
                    <span class="check-label">Change Password</span>
                </label>

                <!-- New Password -->
                <div id="password-group" style="<?= isset($_POST['change_password']) ? '' : 'display:none;' ?>">
                    <div class="form-group">
                        <label>New Password <span class="req">*</span></label>
                        <div style="display:flex;gap:8px;">
                            <input type="password" name="new_password" id="upd_password" class="form-control"
                                   style="flex:1;"
                                   placeholder="Minimum 8 characters"
                                   minlength="8" maxlength="128">
                            <button type="button" class="btn btn-accent btn-sm" onclick="openGenerator()" style="white-space:nowrap;">
                                <i class="fas fa-key"></i> Generate
                            </button>
                        </div>
                        <div class="pw-strength" id="upd-pw-strength" style="display:none;">
                            <div class="pw-bar" id="upd-bar-1"></div>
                            <div class="pw-bar" id="upd-bar-2"></div>
                            <div class="pw-bar" id="upd-bar-3"></div>
                            <div class="pw-bar" id="upd-bar-4"></div>
                            <span class="pw-label" id="upd-pw-label"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password <span class="req">*</span></label>
                        <input type="password" name="confirm_password" id="upd_confirm" class="form-control"
                               placeholder="Re-enter new password">
                        <p class="hint" id="upd-match-hint" style="display:none;"></p>
                    </div>
                </div>

                <div class="btn-row">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="back_to_choose">
                        <button type="submit" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</button>
                    </form>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update Credentials</button>
                </div>
            </form>

            <!-- Delete Section -->
            <hr class="divider">
            <h3 style="font-size:16px;margin-bottom:12px;color:var(--danger);"><i class="fas fa-trash-alt"></i> Danger Zone</h3>
            <p style="font-size:14px;color:var(--text-muted);margin-bottom:16px;">
                Permanently delete an admin account. This cannot be undone.
            </p>

            <form method="POST" onsubmit="return confirm('Are you sure you want to DELETE this admin account? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="form_action" value="delete_admin">

                <div class="form-row" style="margin-bottom:0;">
                    <div class="form-group">
                        <select name="target_user" class="form-control" required>
                            <option value="">— Select admin to delete —</option>
                            <?php foreach ($adminUsernames as $u): ?>
                                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="password" name="delete_password" class="form-control"
                               placeholder="Current password to confirm" required>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete Account</button>
                </div>
            </form>

        <!-- ============================================
             RESULT
             ============================================ -->
        <?php elseif ($formStep === 'result'): ?>
            <?php $resultType = $_SESSION['setup_result'] ?? 'created'; ?>

            <?php if ($resultType === 'created'): ?>
                <div class="result-icon created"><i class="fas fa-user-plus"></i></div>
                <h2 style="text-align:center;">Admin Account Created</h2>
            <?php elseif ($resultType === 'updated'): ?>
                <div class="result-icon updated"><i class="fas fa-user-edit"></i></div>
                <h2 style="text-align:center;">Credentials Updated</h2>
            <?php elseif ($resultType === 'deleted'): ?>
                <div class="result-icon deleted"><i class="fas fa-user-trash"></i></div>
                <h2 style="text-align:center;">Account Deleted</h2>
            <?php endif; ?>

            <p style="text-align:center;color:var(--text-muted);margin-bottom:16px;">
                <?php if ($resultType === 'deleted'): ?>
                    The admin account has been permanently removed.
                <?php else: ?>
                    Save your credentials now. The password is stored as a bcrypt hash and cannot be recovered.
                <?php endif; ?>
            </p>

            <?php if ($resultType !== 'deleted'): ?>
                <div class="cred-box">
                    <span class="lbl">Username:</span> <?= e($_SESSION['setup_username'] ?? '') ?><br>
                    <span class="lbl">Password:</span> ******** (as you entered — not stored in plaintext)<br>
                    <span class="lbl">Hash:</span> <?= e(substr(password_hash('placeholder', PASSWORD_DEFAULT), 0, 20)) ?>... (bcrypt, cost 12)
                </div>
            <?php endif; ?>

            <div class="warn-box">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Security Warning:</strong> Delete <code>setup-admin.php</code> from your server after setup. Leaving credential management files accessible is a security risk.</p>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                <?php if ($adminExists): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="back_to_choose">
                        <button type="submit" class="btn btn-outline"><i class="fas fa-cog"></i> Manage More</button>
                    </form>
                <?php endif; ?>
                <a href="index.php" class="btn btn-primary btn-lg"><i class="fas fa-globe"></i> Visit Website</a>
                <a href="admin/index.php" class="btn btn-accent btn-lg"><i class="fas fa-lock"></i> Admin Login</a>
            </div>

        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="card-footer">
        <a href="index.php"><i class="fas fa-home"></i> Website</a>
        &nbsp;&bull;&nbsp;
        <a href="admin/index.php"><i class="fas fa-lock"></i> Admin Panel</a>
        &nbsp;&bull;&nbsp;
        <a href="password-generator.php"><i class="fas fa-key"></i> Password Generator</a>
    </div>

</div>

<!-- ============================================
     JavaScript
     ============================================ -->
<script>
// Toggle username field visibility
function toggleUsernameField() {
    var chk = document.getElementById('chk_username');
    var grp = document.getElementById('username-group');
    grp.style.display = chk.checked ? '' : 'none';
}

// Toggle password field visibility
function togglePasswordField() {
    var chk = document.getElementById('chk_password');
    var grp = document.getElementById('password-group');
    grp.style.display = chk.checked ? '' : 'none';
}

// Password strength meter (for create form)
var newPassField = document.getElementById('new_password');
if (newPassField) {
    newPassField.addEventListener('input', function() {
        showStrength(this.value, 'pw-bar-', 'pw-label', 'pw-strength');
    });
}

// Password strength meter (for update form)
var updPassField = document.getElementById('upd_password');
if (updPassField) {
    updPassField.addEventListener('input', function() {
        showStrength(this.value, 'upd-bar-', 'upd-pw-label', 'upd-pw-strength');
    });
}

// Confirm password match (create form)
var confirmField = document.getElementById('confirm_password');
if (confirmField) {
    confirmField.addEventListener('input', function() {
        showMatch(newPassField.value, this.value, 'match-hint');
    });
}

// Confirm password match (update form)
var updConfirmField = document.getElementById('upd_confirm');
if (updConfirmField) {
    updConfirmField.addEventListener('input', function() {
        showMatch(updPassField.value, this.value, 'upd-match-hint');
    });
}

// Show password strength
function showStrength(val, barPrefix, labelId, containerId) {
    var container = document.getElementById(containerId);
    if (val.length === 0) { container.style.display = 'none'; return; }
    container.style.display = 'flex';

    var score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^a-zA-Z0-9]/.test(val)) score++;
    score = Math.min(score, 4);

    var colors = ['#DC3545','#E8912D','#D4A017','#2E8B57'];
    var labels = ['Weak','Fair','Good','Strong'];
    var labelColors = ['#DC3545','#E8912D','#D4A017','#2E8B57'];

    for (var i = 1; i <= 4; i++) {
        var bar = document.getElementById(barPrefix + i);
        bar.style.background = (i <= score) ? colors[score-1] : '#EDE7DB';
    }
    var lbl = document.getElementById(labelId);
    lbl.textContent = labels[score-1] || 'Weak';
    lbl.style.color = labelColors[score-1] || '#DC3545';
}

// Show password match status
function showMatch(pw, confirm, hintId) {
    var hint = document.getElementById(hintId);
    if (confirm.length === 0) { hint.style.display = 'none'; return; }
    hint.style.display = 'block';
    if (pw === confirm) {
        hint.textContent = 'Passwords match';
        hint.style.color = '#2E8B57';
    } else {
        hint.textContent = 'Passwords do not match';
        hint.style.color = '#DC3545';
    }
}

// Open password generator
function openGenerator() {
    window.open('password-generator.php', 'pwgen', 'width=640,height=800,scrollbars=yes');
}

// Listen for password from generator
window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'password') {
        var pw = e.data.password;
        // Try to fill the most relevant password field
        var fields = ['new_password', 'upd_password'];
        for (var i = 0; i < fields.length; i++) {
            var f = document.getElementById(fields[i]);
            if (f && f.closest('div').style.display !== 'none') {
                f.value = pw;
                f.dispatchEvent(new Event('input'));
                // Find and fill confirm field
                var confirms = ['confirm_password', 'upd_confirm'];
                var cf = document.getElementById(confirms[i]);
                if (cf) { cf.value = pw; cf.dispatchEvent(new Event('input')); }
                break;
            }
        }
    }
});

// Also listen via localStorage (fallback)
window.addEventListener('storage', function(e) {
    if (e.key === 'generated_password' && e.newValue) {
        var pw = e.newValue;
        var fields = ['new_password', 'upd_password'];
        for (var i = 0; i < fields.length; i++) {
            var f = document.getElementById(fields[i]);
            if (f) { f.value = pw; f.dispatchEvent(new Event('input')); break; }
        }
        localStorage.removeItem('generated_password');
    }
});

// Prevent double submit
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            var origHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            // Re-enable after 5s as safety net
            setTimeout(function() { btn.disabled = false; btn.innerHTML = origHTML; }, 5000);
        }
    });
});
</script>

</body>
</html>