<?php
// ============================================
// SK Travel Planner — Admin Credential Setup
// ============================================
// Allows creating or updating admin credentials.
//
// IMPORTANT:
// DELETE install.php from the server after setup.
// ============================================

declare(strict_types=1);

// --------------------------------------------
// Session & Security
// --------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --------------------------------------------
// Helpers
// --------------------------------------------
function e($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function generateCSRF(): string
{
    if (empty($_SESSION['setup_csrf'])) {
        $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['setup_csrf'];
}

function verifyCSRF(string $token): bool
{
    if (empty($_SESSION['setup_csrf']) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['setup_csrf'], $token);
}

// --------------------------------------------
// Database configuration
// --------------------------------------------
$db_host = 'localhost';
$db_name = 'sk_travel_planner';
$db_user = 'root';
$db_pass = '';

// Load DB values from config.php without executing it
$configPath = __DIR__ . '/config.php';

if (is_file($configPath)) {
    $configContent = file_get_contents($configPath);

    if ($configContent !== false) {
        if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) {
            $db_host = $m[1];
        }

        if (preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) {
            $db_name = $m[1];
        }

        if (preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) {
            $db_user = $m[1];
        }

        if (preg_match("/define\(\s*['\"]DB_PASS['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $configContent, $m)) {
            $db_pass = $m[1];
        }
    }
}

// --------------------------------------------
// Database connection
// --------------------------------------------
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
            PDO::ATTR_TIMEOUT            => 5,
        ]
    );

    $dbConnected = true;

    // Ensure admin_users exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_admin_username` (`username`)
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->query("
        SELECT id, username, created_at
        FROM admin_users
        ORDER BY id ASC
    ");

    $admins = $stmt->fetchAll();

    $adminExists = count($admins) > 0;
    $adminUsernames = array_column($admins, 'username');

} catch (PDOException $ex) {
    $dbConnected = false;

    // Do not expose credentials/database internals to visitors.
    error_log(
        'SK Travel Planner install.php database error: ' .
        $ex->getMessage()
    );

    $dbError = 'Unable to connect to the database. Check your database configuration.';
}

// --------------------------------------------
// Form state
// --------------------------------------------
$errors  = [];
$success = '';

$formStep = $adminExists ? 'choose' : 'create';

// --------------------------------------------
// POST processing
// --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {

        $errors[] = 'Security token mismatch. Please refresh the page and try again.';

    } elseif (!$dbConnected || !$pdo) {

        $errors[] = 'Database is not connected. Check config.php settings.';

    } else {

        $action = $_POST['form_action'] ?? '';

        // ----------------------------------------
        // Choose Create
        // ----------------------------------------
        if ($action === 'choose_create') {

            $formStep = 'create';

        }

        // ----------------------------------------
        // Choose Update
        // ----------------------------------------
        elseif ($action === 'choose_update') {

            $formStep = 'update';

        }

        // ----------------------------------------
        // Back
        // ----------------------------------------
        elseif ($action === 'back_to_choose') {

            $formStep = 'choose';

        }

        // ----------------------------------------
        // Create Admin
        // ----------------------------------------
        elseif ($action === 'create_admin') {

            $formStep = 'create';

            $new_username = trim($_POST['new_username'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            // Username
            if (strlen($new_username) < 3) {

                $errors[] = 'Username must be at least 3 characters.';

            } elseif (strlen($new_username) > 50) {

                $errors[] = 'Username must not exceed 50 characters.';

            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {

                $errors[] = 'Username can only contain letters, numbers, and underscores.';

            } else {

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM admin_users WHERE username = ?"
                );

                $stmt->execute([$new_username]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'That username already exists. Choose another username.';
                }
            }

            // Password
            if (strlen($new_password) < 8) {

                $errors[] = 'Password must be at least 8 characters.';

            } elseif (strlen($new_password) > 128) {

                $errors[] = 'Password must not exceed 128 characters.';
            }

            if ($new_password !== $confirm_pass) {
                $errors[] = 'Passwords do not match.';
            }

            // Create
            if (empty($errors)) {

                try {

                    $hashedPassword = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT,
                        ['cost' => 12]
                    );

                    if ($hashedPassword === false) {
                        throw new RuntimeException('Password hashing failed.');
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO admin_users
                            (username, password)
                        VALUES
                            (?, ?)
                    ");

                    $stmt->execute([
                        $new_username,
                        $hashedPassword
                    ]);

                    $success = 'Admin account created successfully.';

                    $_SESSION['setup_result'] = 'created';
                    $_SESSION['setup_username'] = $new_username;

                    $adminExists = true;

                    $stmt = $pdo->query("
                        SELECT username
                        FROM admin_users
                        ORDER BY id ASC
                    ");

                    $adminUsernames = array_column(
                        $stmt->fetchAll(),
                        'username'
                    );

                    $formStep = 'result';

                } catch (Throwable $ex) {

                    error_log(
                        'SK Travel Planner install.php create admin error: ' .
                        $ex->getMessage()
                    );

                    $errors[] = 'Unable to create the administrator account.';
                }
            }
        }

        // ----------------------------------------
        // Update Admin
        // ----------------------------------------
        elseif ($action === 'update_admin') {

            $formStep = 'update';

            $target_user  = trim($_POST['target_user'] ?? '');
            $current_pass = $_POST['current_password'] ?? '';
            $new_username = trim($_POST['new_username'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            $change_user = isset($_POST['change_username']);
            $change_pass = isset($_POST['change_password']);

            // At least one change
            if (!$change_user && !$change_pass) {
                $errors[] = 'Select username or password to change.';
            }

            // Find target account
            $targetAdmin = null;

            if (empty($errors)) {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM admin_users
                    WHERE username = ?
                    LIMIT 1
                ");

                $stmt->execute([$target_user]);
                $targetAdmin = $stmt->fetch();

                if (!$targetAdmin) {

                    $errors[] = 'Selected admin account was not found.';

                } elseif (!password_verify(
                    $current_pass,
                    $targetAdmin['password']
                )) {

                    $errors[] = 'Current password is incorrect.';
                }
            }

            // Username validation
            if ($change_user && empty($errors)) {

                if (strlen($new_username) < 3) {

                    $errors[] = 'New username must be at least 3 characters.';

                } elseif (strlen($new_username) > 50) {

                    $errors[] = 'New username must not exceed 50 characters.';

                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {

                    $errors[] = 'New username can only contain letters, numbers, and underscores.';

                } elseif ($new_username === $target_user) {

                    $errors[] = 'New username is the same as the current username.';

                } else {

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM admin_users
                        WHERE username = ?
                        AND id != ?
                    ");

                    $stmt->execute([
                        $new_username,
                        $targetAdmin['id']
                    ]);

                    if ((int)$stmt->fetchColumn() > 0) {
                        $errors[] = 'That username is already taken.';
                    }
                }
            }

            // Password validation
            if ($change_pass && empty($errors)) {

                if (strlen($new_password) < 8) {
                    $errors[] = 'New password must be at least 8 characters.';
                }

                if (strlen($new_password) > 128) {
                    $errors[] = 'New password must not exceed 128 characters.';
                }

                if ($new_password !== $confirm_pass) {
                    $errors[] = 'New passwords do not match.';
                }
            }

            // Update
            if (empty($errors) && $targetAdmin) {

                try {

                    if ($change_user && $change_pass) {

                        $hashedPassword = password_hash(
                            $new_password,
                            PASSWORD_DEFAULT,
                            ['cost' => 12]
                        );

                        $stmt = $pdo->prepare("
                            UPDATE admin_users
                            SET username = ?, password = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $new_username,
                            $hashedPassword,
                            $targetAdmin['id']
                        ]);

                        $_SESSION['setup_username'] = $new_username;

                    } elseif ($change_user) {

                        $stmt = $pdo->prepare("
                            UPDATE admin_users
                            SET username = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $new_username,
                            $targetAdmin['id']
                        ]);

                        $_SESSION['setup_username'] = $new_username;

                    } elseif ($change_pass) {

                        $hashedPassword = password_hash(
                            $new_password,
                            PASSWORD_DEFAULT,
                            ['cost' => 12]
                        );

                        $stmt = $pdo->prepare("
                            UPDATE admin_users
                            SET password = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $hashedPassword,
                            $targetAdmin['id']
                        ]);

                        $_SESSION['setup_username'] = $target_user;
                    }

                    $_SESSION['setup_result'] = 'updated';

                    $success = 'Admin credentials updated successfully.';

                    $stmt = $pdo->query("
                        SELECT username
                        FROM admin_users
                        ORDER BY id ASC
                    ");

                    $adminUsernames = array_column(
                        $stmt->fetchAll(),
                        'username'
                    );

                    $formStep = 'result';

                } catch (Throwable $ex) {

                    error_log(
                        'SK Travel Planner install.php update admin error: ' .
                        $ex->getMessage()
                    );

                    $errors[] = 'Unable to update the administrator account.';
                }
            }
        }

        // ----------------------------------------
        // Delete Admin
        // ----------------------------------------
        elseif ($action === 'delete_admin') {

            $target_user = trim($_POST['target_user'] ?? '');
            $delete_password = $_POST['delete_password'] ?? '';

            $stmt = $pdo->prepare("
                SELECT *
                FROM admin_users
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$target_user]);
            $targetAdmin = $stmt->fetch();

            if (!$targetAdmin) {

                $errors[] = 'Admin account not found.';
                $formStep = 'update';

            } elseif (!password_verify(
                $delete_password,
                $targetAdmin['password']
            )) {

                $errors[] = 'Current password is incorrect. Deletion aborted.';
                $formStep = 'update';

            } else {

                try {

                    // Prevent deleting the final admin
                    $countStmt = $pdo->query("
                        SELECT COUNT(*)
                        FROM admin_users
                    ");

                    $adminCount = (int)$countStmt->fetchColumn();

                    if ($adminCount <= 1) {

                        $errors[] =
                            'The last administrator cannot be deleted. Create another admin first.';

                        $formStep = 'update';

                    } else {

                        $stmt = $pdo->prepare("
                            DELETE FROM admin_users
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $targetAdmin['id']
                        ]);

                        $_SESSION['setup_result'] = 'deleted';
                        $_SESSION['setup_username'] = $target_user;

                        $success = 'Admin account deleted successfully.';

                        $stmt = $pdo->query("
                            SELECT username
                            FROM admin_users
                            ORDER BY id ASC
                        ");

                        $adminUsernames = array_column(
                            $stmt->fetchAll(),
                            'username'
                        );

                        $adminExists = count($adminUsernames) > 0;

                        $formStep = 'result';
                    }

                } catch (Throwable $ex) {

                    error_log(
                        'SK Travel Planner install.php delete admin error: ' .
                        $ex->getMessage()
                    );

                    $errors[] = 'Unable to delete the administrator account.';
                    $formStep = 'update';
                }
            }
        }
    }

    // Rotate CSRF after POST
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = generateCSRF();

$resultType = $_SESSION['setup_result'] ?? 'created';
$resultUsername = $_SESSION['setup_username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Credential Setup — SK Travel Planner</title>

    <!-- ROOT FILE: install.php is in project root -->
    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: #0D7377;
            --primary-dark: #095558;
            --primary-light: #14A3A8;
            --accent: #E8912D;
            --accent-dark: #C75B2A;
            --dark: #1B2838;
            --text: #2D2D2D;
            --muted: #6B7280;
            --light: #F7F3ED;
            --border: #D1CBC0;
            --white: #FFFFFF;
            --success: #2E8B57;
            --danger: #DC3545;
            --warning: #D4A017;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            padding: 28px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Source Sans 3", sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(232,145,45,.18),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #1B2838,
                    #095558 55%,
                    #0D7377
                );
        }

        .card {
            width: 100%;
            max-width: 650px;
            overflow: hidden;
            background: var(--white);
            border-radius: 18px;
            box-shadow: 0 24px 70px rgba(0,0,0,.32);
        }

        .card-header {
            padding: 30px 28px;
            text-align: center;
            color: white;
            background:
                linear-gradient(
                    135deg,
                    var(--dark),
                    var(--primary-dark)
                );
        }

        .header-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            color: white;
            font-size: 27px;
            background:
                linear-gradient(
                    135deg,
                    var(--accent),
                    var(--accent-dark)
                );
        }

        .card-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 27px;
            margin-bottom: 5px;
        }

        .card-header p {
            color: rgba(255,255,255,.68);
            font-size: 14px;
        }

        .card-body {
            padding: 32px;
        }

        h2 {
            color: var(--dark);
            margin-bottom: 7px;
            font-size: 21px;
        }

        h3 {
            color: var(--dark);
        }

        .desc {
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 22px;
        }

        .db-status,
        .alert {
            border-radius: 10px;
        }

        .db-status {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .db-status.ok {
            color: #155724;
            background: #edf8f1;
        }

        .db-status.ok i {
            color: var(--success);
        }

        .db-status.fail {
            color: #721c24;
            background: #fff0f1;
        }

        .db-status.fail i {
            color: var(--danger);
        }

        .admin-title {
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .admin-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 22px;
        }

        .admin-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            border-radius: 20px;
            color: var(--dark);
            background: var(--light);
            font-size: 13px;
            font-weight: 600;
        }

        .admin-chip i {
            color: var(--primary);
        }

        .choice-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 22px;
        }

        .choice-form {
            margin: 0;
        }

        .choice-card {
            width: 100%;
            min-height: 155px;
            padding: 22px 16px;
            border: 2px solid transparent;
            border-radius: 13px;
            cursor: pointer;
            text-align: center;
            background: var(--light);
            transition: .25s ease;
        }

        .choice-card:hover {
            border-color: var(--primary);
            background: #f2fbfb;
            transform: translateY(-2px);
        }

        .choice-card i {
            display: block;
            margin-bottom: 11px;
            color: var(--primary);
            font-size: 30px;
        }

        .choice-card h3 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .choice-card p {
            color: var(--muted);
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
        }

        .req {
            color: var(--danger);
        }

        .hint {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid var(--border);
            border-radius: 9px;
            outline: none;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 15px;
            transition: .2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13,115,119,.09);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .password-row {
            display: flex;
            gap: 8px;
        }

        .password-row .form-control {
            flex: 1;
        }

        .btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 10px 18px;
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            transition: .2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            color: white;
            background: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-accent {
            color: white;
            background: var(--accent);
        }

        .btn-accent:hover {
            background: var(--accent-dark);
        }

        .btn-outline {
            color: var(--primary);
            border: 2px solid var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            color: white;
            background: var(--primary);
        }

        .btn-danger {
            color: white;
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #b02a37;
        }

        .btn-lg {
            min-height: 48px;
            padding: 12px 22px;
            font-size: 15px;
        }

        .btn-sm {
            min-height: 42px;
            padding: 8px 14px;
            font-size: 13px;
        }

        .alert {
            padding: 13px 15px;
            margin-bottom: 14px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-danger {
            color: #721c24;
            border-left: 4px solid var(--danger);
            background: #f8d7da;
        }

        .alert-success {
            color: #155724;
            border-left: 4px solid var(--success);
            background: #d4edda;
        }

        .alert-info {
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            background: #d1ecf1;
        }

        .divider {
            border: 0;
            border-top: 1px solid #e9e3d8;
            margin: 25px 0;
        }

        .check-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            cursor: pointer;
        }

        .check-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .check-box {
            width: 22px;
            height: 22px;
            flex: 0 0 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border);
            border-radius: 6px;
        }

        .check-box i {
            color: transparent;
            font-size: 11px;
        }

        .check-toggle input:checked + .check-box {
            border-color: var(--primary);
            background: var(--primary);
        }

        .check-toggle input:checked + .check-box i {
            color: white;
        }

        .check-label {
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
        }

        .pw-strength {
            display: none;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }

        .pw-bar {
            flex: 1;
            height: 4px;
            border-radius: 5px;
            background: #ede7db;
        }

        .pw-label {
            min-width: 52px;
            text-align: right;
            font-size: 11px;
            font-weight: 700;
        }

        .result-icon {
            width: 78px;
            height: 78px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 32px;
        }

        .result-icon.created,
        .result-icon.updated {
            background: var(--success);
        }

        .result-icon.deleted {
            background: var(--danger);
        }

        .cred-box {
            margin: 18px 0;
            padding: 17px;
            overflow-wrap: anywhere;
            border-radius: 9px;
            color: rgba(255,255,255,.9);
            background: var(--dark);
            font-family: monospace;
            font-size: 13px;
            line-height: 1.8;
        }

        .cred-box .lbl {
            color: #F5B041;
            font-weight: 700;
        }

        .warn-box {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            border: 1px solid rgba(220,53,69,.2);
            border-radius: 9px;
            background: #fff5f5;
        }

        .warn-box i {
            color: var(--danger);
            margin-top: 3px;
        }

        .warn-box p {
            color: #721c24;
            font-size: 13px;
            line-height: 1.55;
        }

        .danger-title {
            margin-bottom: 9px;
            color: var(--danger);
            font-size: 16px;
        }

        .card-footer {
            padding: 17px 20px 22px;
            border-top: 1px solid #e9e3d8;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            line-height: 2;
        }

        .card-footer a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .card-footer a:hover {
            color: var(--accent);
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
                align-items: flex-start;
            }

            .card {
                margin: 10px 0;
                border-radius: 14px;
            }

            .card-header {
                padding: 25px 18px;
            }

            .card-header h1 {
                font-size: 23px;
            }

            .card-body {
                padding: 24px 18px;
            }

            .choice-grid,
            .form-row {
                grid-template-columns: 1fr;
            }

            .password-row {
                flex-direction: column;
            }

            .password-row .btn {
                width: 100%;
            }

            .btn-row {
                flex-direction: column;
            }

            .btn-row .btn,
            .btn-row form,
            .btn-row form .btn {
                width: 100%;
            }
        }

        @media (max-width: 380px) {
            .card-body {
                padding: 20px 14px;
            }

            .card-header {
                padding: 22px 14px;
            }

            .card-footer {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<div class="card">

    <div class="card-header">
        <div class="header-icon">
            <i class="fas fa-user-shield"></i>
        </div>

        <h1>Admin Credential Setup</h1>
        <p>SK Travel Planner</p>
    </div>

    <div class="card-body">

        <!-- Database Status -->
        <?php if ($dbConnected): ?>

            <div class="db-status ok">
                <i class="fas fa-check-circle"></i>

                <div>
                    Connected to
                    <strong><?= e($db_name) ?></strong>
                    at <?= e($db_host) ?>
                </div>
            </div>

        <?php else: ?>

            <div class="db-status fail">
                <i class="fas fa-times-circle"></i>

                <div>
                    <?= e($dbError) ?>
                </div>
            </div>

            <div class="alert alert-danger">
                <i class="fas fa-triangle-exclamation"></i>
                Check the database settings in
                <code>config.php</code>.
            </div>

        <?php endif; ?>

        <!-- Existing Admins -->
        <?php if ($dbConnected && $adminExists): ?>

            <div class="admin-title">
                Existing Admin Accounts
            </div>

            <div class="admin-chips">

                <?php foreach ($adminUsernames as $username): ?>

                    <span class="admin-chip">
                        <i class="fas fa-user"></i>
                        <?= e($username) ?>
                    </span>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>

            <?php foreach ($errors as $error): ?>

                <div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i>
                    <?= e($error) ?>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

        <!-- Success -->
        <?php if ($success && $formStep !== 'result'): ?>

            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <?php if (!$dbConnected): ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No credential forms are available until the database connection is restored.
            </div>


        <!-- CHOOSE -->
        <?php elseif ($formStep === 'choose'): ?>

            <h2>What would you like to do?</h2>

            <p class="desc">
                Select an action below to manage administrator credentials.
            </p>

            <div class="choice-grid">

                <form method="POST" class="choice-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="form_action"
                        value="choose_create"
                    >

                    <button type="submit" class="choice-card">
                        <i class="fas fa-user-plus"></i>

                        <h3>Create New Admin</h3>

                        <p>
                            Add another administrator account.
                        </p>
                    </button>

                </form>


                <form method="POST" class="choice-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="form_action"
                        value="choose_update"
                    >

                    <button type="submit" class="choice-card">
                        <i class="fas fa-user-edit"></i>

                        <h3>Update Existing</h3>

                        <p>
                            Change username or password.
                        </p>
                    </button>

                </form>

            </div>


        <!-- CREATE -->
        <?php elseif ($formStep === 'create'): ?>

            <h2>
                <i
                    class="fas fa-user-plus"
                    style="color:var(--primary);"
                ></i>

                Create New Admin
            </h2>

            <p class="desc">
                Create a secure administrator account.
                Passwords are stored using PHP's secure password hashing.
            </p>

            <form method="POST" id="create-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="form_action"
                    value="create_admin"
                >

                <div class="form-group">

                    <label>
                        Username
                        <span class="req">*</span>
                    </label>

                    <input
                        type="text"
                        name="new_username"
                        class="form-control"
                        value="<?= e($_POST['new_username'] ?? '') ?>"
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]+"
                        placeholder="e.g. admin"
                        autocomplete="username"
                        required
                    >

                    <p class="hint">
                        Letters, numbers and underscores only.
                    </p>

                </div>


                <div class="form-group">

                    <label>
                        Password
                        <span class="req">*</span>
                    </label>

                    <div class="password-row">

                        <input
                            type="password"
                            name="new_password"
                            id="new_password"
                            class="form-control"
                            minlength="8"
                            maxlength="128"
                            placeholder="Minimum 8 characters"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-accent"
                            onclick="openGenerator('new_password')"
                        >
                            <i class="fas fa-key"></i>
                            Generate
                        </button>

                    </div>

                    <div
                        class="pw-strength"
                        id="pw-strength"
                    >
                        <div class="pw-bar" id="pw-bar-1"></div>
                        <div class="pw-bar" id="pw-bar-2"></div>
                        <div class="pw-bar" id="pw-bar-3"></div>
                        <div class="pw-bar" id="pw-bar-4"></div>

                        <span
                            class="pw-label"
                            id="pw-label"
                        ></span>
                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Confirm Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        class="form-control"
                        placeholder="Re-enter password"
                        autocomplete="new-password"
                        required
                    >

                    <p
                        class="hint"
                        id="match-hint"
                        style="display:none;"
                    ></p>

                </div>


                <div class="alert alert-info">
                    <i class="fas fa-shield-halved"></i>

                    Password is securely hashed before being stored.
                    The original password is never saved in plaintext.
                </div>


                <div class="btn-row">

                    <?php if ($adminExists): ?>

                        <button
                            type="submit"
                            name="form_action"
                            value="back_to_choose"
                            formnovalidate
                            class="btn btn-outline"
                        >
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </button>

                    <?php endif; ?>

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg"
                    >
                        <i class="fas fa-user-plus"></i>
                        Create Admin
                    </button>

                </div>

            </form>


        <!-- UPDATE -->
        <?php elseif ($formStep === 'update'): ?>

            <h2>
                <i
                    class="fas fa-user-edit"
                    style="color:var(--primary);"
                ></i>

                Update Admin Credentials
            </h2>

            <p class="desc">
                Verify the current password before changing an administrator's
                username or password.
            </p>

            <form method="POST" id="update-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="form_action"
                    value="update_admin"
                >

                <div class="form-group">

                    <label>
                        Select Admin Account
                        <span class="req">*</span>
                    </label>

                    <select
                        name="target_user"
                        class="form-control"
                        required
                    >

                        <option value="">
                            — Choose an admin —
                        </option>

                        <?php foreach ($adminUsernames as $username): ?>

                            <option
                                value="<?= e($username) ?>"
                                <?= (
                                    isset($_POST['target_user']) &&
                                    $_POST['target_user'] === $username
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($username) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Current Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="current_password"
                        class="form-control"
                        placeholder="Enter current password"
                        autocomplete="current-password"
                        required
                    >

                    <p class="hint">
                        Required to verify account ownership.
                    </p>

                </div>


                <hr class="divider">


                <h3 style="font-size:16px;margin-bottom:15px;">
                    What do you want to change?
                </h3>


                <label class="check-toggle">

                    <input
                        type="checkbox"
                        name="change_username"
                        id="chk_username"
                        value="1"
                        <?= isset($_POST['change_username']) ? 'checked' : '' ?>
                        onchange="toggleUsernameField()"
                    >

                    <span class="check-box">
                        <i class="fas fa-check"></i>
                    </span>

                    <span class="check-label">
                        Change Username
                    </span>

                </label>


                <div
                    class="form-group"
                    id="username-group"
                    style="<?= isset($_POST['change_username']) ? '' : 'display:none;' ?>"
                >

                    <label>
                        New Username
                        <span class="req">*</span>
                    </label>

                    <input
                        type="text"
                        name="new_username"
                        class="form-control"
                        value="<?= e($_POST['new_username'] ?? '') ?>"
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]+"
                        placeholder="Enter new username"
                        autocomplete="username"
                    >

                </div>


                <label class="check-toggle">

                    <input
                        type="checkbox"
                        name="change_password"
                        id="chk_password"
                        value="1"
                        <?= isset($_POST['change_password']) ? 'checked' : '' ?>
                        onchange="togglePasswordField()"
                    >

                    <span class="check-box">
                        <i class="fas fa-check"></i>
                    </span>

                    <span class="check-label">
                        Change Password
                    </span>

                </label>


                <div
                    id="password-group"
                    style="<?= isset($_POST['change_password']) ? '' : 'display:none;' ?>"
                >

                    <div class="form-group">

                        <label>
                            New Password
                            <span class="req">*</span>
                        </label>

                        <div class="password-row">

                            <input
                                type="password"
                                name="new_password"
                                id="upd_password"
                                class="form-control"
                                minlength="8"
                                maxlength="128"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                            >

                            <button
                                type="button"
                                class="btn btn-accent"
                                onclick="openGenerator('upd_password')"
                            >
                                <i class="fas fa-key"></i>
                                Generate
                            </button>

                        </div>

                        <div
                            class="pw-strength"
                            id="upd-pw-strength"
                        >
                            <div class="pw-bar" id="upd-bar-1"></div>
                            <div class="pw-bar" id="upd-bar-2"></div>
                            <div class="pw-bar" id="upd-bar-3"></div>
                            <div class="pw-bar" id="upd-bar-4"></div>

                            <span
                                class="pw-label"
                                id="upd-pw-label"
                            ></span>
                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Confirm New Password
                            <span class="req">*</span>
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            id="upd_confirm"
                            class="form-control"
                            placeholder="Re-enter new password"
                            autocomplete="new-password"
                        >

                        <p
                            class="hint"
                            id="upd-match-hint"
                            style="display:none;"
                        ></p>

                    </div>

                </div>


                <div class="btn-row">

                    <button
                        type="submit"
                        name="form_action"
                        value="back_to_choose"
                        formnovalidate
                        class="btn btn-outline"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg"
                    >
                        <i class="fas fa-save"></i>
                        Update Credentials
                    </button>

                </div>

            </form>


            <!-- Danger Zone -->
            <hr class="divider">

            <h3 class="danger-title">
                <i class="fas fa-triangle-exclamation"></i>
                Danger Zone
            </h3>

            <p class="hint" style="font-size:14px;margin-bottom:15px;">
                Permanently delete an admin account. This cannot be undone.
            </p>

            <form
                method="POST"
                onsubmit="return confirmDelete();"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="form_action"
                    value="delete_admin"
                >

                <div class="form-row">

                    <div class="form-group">

                        <label>Select Admin</label>

                        <select
                            name="target_user"
                            class="form-control"
                            required
                        >

                            <option value="">
                                — Select admin —
                            </option>

                            <?php foreach ($adminUsernames as $username): ?>

                                <option value="<?= e($username) ?>">
                                    <?= e($username) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Current Password</label>

                        <input
                            type="password"
                            name="delete_password"
                            class="form-control"
                            placeholder="Password to confirm"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="btn btn-danger"
                >
                    <i class="fas fa-trash"></i>
                    Delete Account
                </button>

            </form>


        <!-- RESULT -->
        <?php elseif ($formStep === 'result'): ?>

            <?php if ($resultType === 'created'): ?>

                <div class="result-icon created">
                    <i class="fas fa-user-plus"></i>
                </div>

                <h2 style="text-align:center;">
                    Admin Account Created
                </h2>

            <?php elseif ($resultType === 'updated'): ?>

                <div class="result-icon updated">
                    <i class="fas fa-user-edit"></i>
                </div>

                <h2 style="text-align:center;">
                    Credentials Updated
                </h2>

            <?php else: ?>

                <div class="result-icon deleted">
                    <i class="fas fa-user-minus"></i>
                </div>

                <h2 style="text-align:center;">
                    Account Deleted
                </h2>

            <?php endif; ?>


            <p style="text-align:center;color:var(--muted);margin:12px 0 20px;">
                <?php if ($resultType === 'deleted'): ?>

                    The administrator account has been permanently removed.

                <?php else: ?>

                    The administrator credentials have been processed successfully.

                <?php endif; ?>
            </p>


            <?php if ($resultType !== 'deleted'): ?>

                <div class="cred-box">

                    <span class="lbl">Username:</span>
                    <?= e($resultUsername) ?>

                    <br>

                    <span class="lbl">Password:</span>
                    Stored securely as a password hash.

                    <br>

                    <span class="lbl">Storage:</span>
                    Password hash only — plaintext password is not stored.

                </div>

            <?php endif; ?>


            <div class="warn-box">

                <i class="fas fa-triangle-exclamation"></i>

                <p>
                    <strong>Security Warning:</strong>
                    Delete <code>install.php</code> from your server
                    after setup is complete. Leaving the credential setup
                    page publicly accessible is a security risk.
                </p>

            </div>


            <div
                style="
                    display:flex;
                    gap:10px;
                    justify-content:center;
                    flex-wrap:wrap;
                    margin-top:22px;
                "
            >

                <?php if ($adminExists): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="form_action"
                            value="back_to_choose"
                        >

                        <button
                            type="submit"
                            class="btn btn-outline"
                        >
                            <i class="fas fa-cog"></i>
                            Manage More
                        </button>

                    </form>

                <?php endif; ?>


                <!-- ROOT WEBSITE -->
                <a
                    href="index.php"
                    class="btn btn-primary btn-lg"
                >
                    <i class="fas fa-globe"></i>
                    Visit Website
                </a>


                <!-- ADMIN DIRECTORY -->
                <a
                    href="admin/index.php"
                    class="btn btn-accent btn-lg"
                >
                    <i class="fas fa-lock"></i>
                    Admin Login
                </a>

            </div>

        <?php endif; ?>

    </div>


    <!-- FOOTER -->
    <div class="card-footer">

        <!-- install.php is in ROOT -->
        <a href="index.php">
            <i class="fas fa-home"></i>
            Website
        </a>

        &nbsp; • &nbsp;

        <!-- Admin is inside /admin/ -->
        <a href="admin/index.php">
            <i class="fas fa-lock"></i>
            Admin Panel
        </a>

        &nbsp; • &nbsp;

        <!-- Generator is inside /admin/ -->
        <a href="admin/password-generator.php">
            <i class="fas fa-key"></i>
            Password Generator
        </a>

    </div>

</div>


<script>
// --------------------------------------------
// Toggle Username
// --------------------------------------------
function toggleUsernameField() {

    const checkbox = document.getElementById('chk_username');
    const group = document.getElementById('username-group');

    if (!checkbox || !group) return;

    group.style.display = checkbox.checked ? '' : 'none';
}


// --------------------------------------------
// Toggle Password
// --------------------------------------------
function togglePasswordField() {

    const checkbox = document.getElementById('chk_password');
    const group = document.getElementById('password-group');

    if (!checkbox || !group) return;

    group.style.display = checkbox.checked ? '' : 'none';
}


// --------------------------------------------
// Password Strength
// --------------------------------------------
function showStrength(
    value,
    barPrefix,
    labelId,
    containerId
) {
    const container = document.getElementById(containerId);

    if (!container) return;

    if (!value.length) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'flex';

    let score = 0;

    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^a-zA-Z0-9]/.test(value)) score++;

    score = Math.min(score, 4);

    const colors = [
        '#DC3545',
        '#E8912D',
        '#D4A017',
        '#2E8B57'
    ];

    const labels = [
        'Weak',
        'Fair',
        'Good',
        'Strong'
    ];

    const color = colors[Math.max(score - 1, 0)];

    for (let i = 1; i <= 4; i++) {

        const bar = document.getElementById(
            barPrefix + i
        );

        if (!bar) continue;

        bar.style.background =
            i <= score
                ? color
                : '#EDE7DB';
    }

    const label = document.getElementById(labelId);

    if (label) {
        label.textContent = labels[score - 1] || 'Weak';
        label.style.color = color;
    }
}


// --------------------------------------------
// Password Match
// --------------------------------------------
function showMatch(
    password,
    confirmation,
    hintId
) {
    const hint = document.getElementById(hintId);

    if (!hint) return;

    if (!confirmation.length) {
        hint.style.display = 'none';
        return;
    }

    hint.style.display = 'block';

    if (password === confirmation) {

        hint.textContent = 'Passwords match';
        hint.style.color = '#2E8B57';

    } else {

        hint.textContent = 'Passwords do not match';
        hint.style.color = '#DC3545';
    }
}


// --------------------------------------------
// Password Generator
// IMPORTANT:
// install.php is ROOT.
// generator is /admin/password-generator.php
// --------------------------------------------
function openGenerator(targetField) {

    const generatorUrl =
        'admin/password-generator.php?target=' +
        encodeURIComponent(targetField);

    window.open(
        generatorUrl,
        'pwgen',
        'width=640,height=800,resizable=yes,scrollbars=yes'
    );
}


// --------------------------------------------
// Receive generated password
// --------------------------------------------
window.addEventListener('message', function(event) {

    if (!event.data) return;

    if (event.data.type !== 'password') return;

    const password = event.data.password;

    if (!password) return;

    const target =
        event.data.target || 'new_password';

    const field =
        document.getElementById(target);

    if (!field) return;

    field.value = password;

    field.dispatchEvent(
        new Event('input', { bubbles: true })
    );

    // Fill corresponding confirmation field
    let confirmField = null;

    if (target === 'new_password') {
        confirmField =
            document.getElementById('confirm_password');
    }

    if (target === 'upd_password') {
        confirmField =
            document.getElementById('upd_confirm');
    }

    if (confirmField) {

        confirmField.value = password;

        confirmField.dispatchEvent(
            new Event('input', { bubbles: true })
        );
    }
});


// --------------------------------------------
// LocalStorage fallback
// --------------------------------------------
window.addEventListener('storage', function(event) {

    if (
        event.key !== 'generated_password' ||
        !event.newValue
    ) {
        return;
    }

    const password = event.newValue;

    const activeFields = [
        'new_password',
        'upd_password'
    ];

    for (const id of activeFields) {

        const field = document.getElementById(id);

        if (
            field &&
            field.offsetParent !== null
        ) {

            field.value = password;

            field.dispatchEvent(
                new Event('input', { bubbles: true })
            );

            break;
        }
    }

    localStorage.removeItem('generated_password');
});


// --------------------------------------------
// Password listeners
// --------------------------------------------
const newPass =
    document.getElementById('new_password');

if (newPass) {

    newPass.addEventListener('input', function() {

        showStrength(
            this.value,
            'pw-bar-',
            'pw-label',
            'pw-strength'
        );
    });
}


const newConfirm =
    document.getElementById('confirm_password');

if (newConfirm) {

    newConfirm.addEventListener('input', function() {

        showMatch(
            newPass ? newPass.value : '',
            this.value,
            'match-hint'
        );
    });
}


const updPass =
    document.getElementById('upd_password');

if (updPass) {

    updPass.addEventListener('input', function() {

        showStrength(
            this.value,
            'upd-bar-',
            'upd-pw-label',
            'upd-pw-strength'
        );
    });
}


const updConfirm =
    document.getElementById('upd_confirm');

if (updConfirm) {

    updConfirm.addEventListener('input', function() {

        showMatch(
            updPass ? updPass.value : '',
            this.value,
            'upd-match-hint'
        );
    });
}


// --------------------------------------------
// Delete confirmation
// --------------------------------------------
function confirmDelete() {

    return confirm(
        'Are you sure you want to DELETE this admin account?\n\n' +
        'This action cannot be undone.'
    );
}
</script>

</body>
</html>