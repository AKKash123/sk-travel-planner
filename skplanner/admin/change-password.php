<?php
// ============================================================
// SK Travel Planner — Admin Credential Manager
// Secure password / username management
// ============================================================

// ------------------------------------------------------------
// Load central application configuration
// ------------------------------------------------------------
// change-password.php is inside /admin/
// config.php is in the project root.
require_once dirname(__DIR__) . '/config.php';

// ------------------------------------------------------------
// Admin authentication
// ------------------------------------------------------------
if (!function_exists('isAdmin') || !isAdmin()) {
    header('Location: index.php');
    exit;
}

// ------------------------------------------------------------
// Helper fallback
// ------------------------------------------------------------
if (!function_exists('e')) {
    function e($s): string
    {
        return htmlspecialchars(
            (string)$s,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

// ------------------------------------------------------------
// Session
// ------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// ------------------------------------------------------------
// CSRF token
// ------------------------------------------------------------
if (
    empty($_SESSION['pw_csrf']) ||
    !is_string($_SESSION['pw_csrf'])
) {
    $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['pw_csrf'];

// ------------------------------------------------------------
// Admin list
// ------------------------------------------------------------
$admins = [];

try {
    $stmt = $pdo->query(
        "SELECT id, username, created_at
         FROM admin_users
         ORDER BY id"
    );

    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    error_log(
        'SK Travel Planner - Credential manager admin list error: '
        . $ex->getMessage()
    );

    http_response_code(500);

    $error500File = dirname(__DIR__) . '/error-500.php';

    if (is_file($error500File)) {
        require $error500File;
    } else {
        echo '<h1>500 — Internal Server Error</h1>';
    }

    exit;
}

// ------------------------------------------------------------
// Process form
// ------------------------------------------------------------
$errors  = [];
$success = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
    isset($_POST['act'])
) {

    // --------------------------------------------------------
    // CSRF validation
    // --------------------------------------------------------
    $postedCsrf = (string)($_POST['csrf'] ?? '');

    if (
        empty($postedCsrf) ||
        empty($_SESSION['pw_csrf']) ||
        !hash_equals($_SESSION['pw_csrf'], $postedCsrf)
    ) {
        $errors[] = 'Security token mismatch. Refresh and retry.';
    } else {

        $act = (string)$_POST['act'];

        // ====================================================
        // CHANGE PASSWORD
        // ====================================================
        if ($act === 'chpass') {

            $uid = (int)($_POST['uid'] ?? 0);
            $cur = (string)($_POST['cur'] ?? '');
            $new = (string)($_POST['new'] ?? '');
            $cnf = (string)($_POST['cnf'] ?? '');

            $stmt = $pdo->prepare(
                "SELECT id, username, password
                 FROM admin_users
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$uid]);
            $adm = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adm) {
                $errors[] = 'Account not found.';
            } elseif (!password_verify($cur, $adm['password'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif (strlen($new) > 128) {
                $errors[] = 'Password too long (maximum 128 characters).';
            } elseif ($new !== $cnf) {
                $errors[] = 'New passwords do not match.';
            } elseif (password_verify($new, $adm['password'])) {
                $errors[] = 'New password must differ from current.';
            }

            if (empty($errors)) {

                $hash = password_hash(
                    $new,
                    PASSWORD_DEFAULT,
                    ['cost' => 12]
                );

                $stmt = $pdo->prepare(
                    "UPDATE admin_users
                     SET password = ?
                     WHERE id = ?"
                );

                $stmt->execute([$hash, $uid]);

                $success =
                    'Password updated for "' .
                    $adm['username'] .
                    '". Old password no longer works.';
            }
        }

        // ====================================================
        // CHANGE USERNAME
        // ====================================================
        if ($act === 'chuser') {

            $uid  = (int)($_POST['uid'] ?? 0);
            $cur  = (string)($_POST['cur'] ?? '');
            $nusr = trim((string)($_POST['nusr'] ?? ''));

            $stmt = $pdo->prepare(
                "SELECT id, username, password
                 FROM admin_users
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$uid]);
            $adm = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adm) {
                $errors[] = 'Account not found.';
            } elseif (!password_verify($cur, $adm['password'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($nusr) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            } elseif (strlen($nusr) > 50) {
                $errors[] = 'Username cannot exceed 50 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nusr)) {
                $errors[] =
                    'Username: only letters, numbers and underscores.';
            } elseif ($nusr === $adm['username']) {
                $errors[] = 'New username is same as current.';
            } else {

                $chk = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM admin_users
                     WHERE username = ?
                     AND id != ?"
                );

                $chk->execute([$nusr, $uid]);

                if ((int)$chk->fetchColumn() > 0) {
                    $errors[] = 'Username already taken.';
                }
            }

            if (empty($errors)) {

                $stmt = $pdo->prepare(
                    "UPDATE admin_users
                     SET username = ?
                     WHERE id = ?"
                );

                $stmt->execute([$nusr, $uid]);

                $success =
                    'Username changed to "' .
                    $nusr .
                    '". Use the new username for your next login.';

                $admins = $pdo
                    ->query(
                        "SELECT id, username, created_at
                         FROM admin_users
                         ORDER BY id"
                    )
                    ->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // ====================================================
        // CREATE ADMIN
        // ====================================================
        if ($act === 'create') {

            $nusr  = trim((string)($_POST['nusr'] ?? ''));
            $npass = (string)($_POST['npass'] ?? '');
            $ncnf  = (string)($_POST['ncnf'] ?? '');

            if (strlen($nusr) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            } elseif (strlen($nusr) > 50) {
                $errors[] = 'Username cannot exceed 50 characters.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nusr)) {
                $errors[] =
                    'Username: only letters, numbers and underscores.';
            } else {

                $chk = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM admin_users
                     WHERE username = ?"
                );

                $chk->execute([$nusr]);

                if ((int)$chk->fetchColumn() > 0) {
                    $errors[] = 'Username already exists.';
                }
            }

            if (strlen($npass) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }

            if (strlen($npass) > 128) {
                $errors[] = 'Password cannot exceed 128 characters.';
            }

            if ($npass !== $ncnf) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {

                $hash = password_hash(
                    $npass,
                    PASSWORD_DEFAULT,
                    ['cost' => 12]
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO admin_users
                     (username, password)
                     VALUES (?, ?)"
                );

                $stmt->execute([$nusr, $hash]);

                $success =
                    'Admin "' .
                    $nusr .
                    '" created successfully.';

                $admins = $pdo
                    ->query(
                        "SELECT id, username, created_at
                         FROM admin_users
                         ORDER BY id"
                    )
                    ->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // ====================================================
        // DELETE ADMIN
        // ====================================================
        if ($act === 'deladm') {

            $uid = (int)($_POST['uid'] ?? 0);
            $cur = (string)($_POST['cur'] ?? '');

            $stmt = $pdo->prepare(
                "SELECT id, username, password
                 FROM admin_users
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$uid]);
            $adm = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adm) {
                $errors[] = 'Account not found.';
            } elseif (!password_verify($cur, $adm['password'])) {
                $errors[] =
                    'Password incorrect. Deletion denied.';
            } else {

                // Prevent deleting the final admin account.
                $countStmt = $pdo->query(
                    "SELECT COUNT(*)
                     FROM admin_users"
                );

                $adminCount = (int)$countStmt->fetchColumn();

                if ($adminCount <= 1) {

                    $errors[] =
                        'The last admin account cannot be deleted.';

                } else {

                    $stmt = $pdo->prepare(
                        "DELETE FROM admin_users
                         WHERE id = ?"
                    );

                    $stmt->execute([$uid]);

                    $success =
                        'Admin "' .
                        $adm['username'] .
                        '" deleted permanently.';

                    $admins = $pdo
                        ->query(
                            "SELECT id, username, created_at
                             FROM admin_users
                             ORDER BY id"
                        )
                        ->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    }

    // --------------------------------------------------------
    // Rotate CSRF token after every POST
    // --------------------------------------------------------
    $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['pw_csrf'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex,nofollow,noarchive"
    >

    <title>Admin Credential Manager — SK Travel Planner</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
        <link rel="icon" href="../assets/icons/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">
    <!-- IMPORTANT:
         change-password.php is inside /admin/
         style.css is in the project root -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        >
        
        <link rel="stylesheet" href="../style.css">
            
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <!-- Google Fonts -->
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
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Correct root-relative logo paths -->
    <link
        rel="icon"
        type="image/jpeg"
        href="../logo.jpg"
    >

    <link
        rel="apple-touch-icon"
        href="../logo.jpg"
    >

    <style>
        :root {
            --primary: #0D7377;
            --primary-dark: #095558;
            --accent: #E8912D;
            --accent-dark: #C75B2A;
            --dark: #1B2838;
            --text: #2D2D2D;
            --text-muted: #6B7280;
            --light: #F7F3ED;
            --light-darker: #EDE7DB;
            --white: #FFF;
            --success: #2E8B57;
            --danger: #DC3545;
            --warning: #D4A017;
            --border: #D1CBC0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            font-family: 'Source Sans 3', sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(232,145,45,.22),
                    transparent 32%
                ),
                linear-gradient(
                    135deg,
                    #1B2838 0%,
                    #095558 52%,
                    #0D7377 100%
                );
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
            color: var(--text);
        }

        .card {
            background: var(--white);
            border-radius: 18px;
            width: 100%;
            max-width: 680px;
            box-shadow: 0 25px 70px rgba(0,0,0,.32);
            overflow: hidden;
        }

        .card-hdr {
            background:
                linear-gradient(
                    135deg,
                    var(--dark),
                    var(--primary-dark)
                );
            padding: 24px 32px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .card-hdr .ico {
            width: 48px;
            height: 48px;
            background:
                linear-gradient(
                    135deg,
                    var(--accent),
                    var(--accent-dark)
                );
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0,0,0,.18);
        }

        .card-hdr h1 {
            font-family: 'Playfair Display', serif;
            color: var(--white);
            font-size: 20px;
            margin-bottom: 2px;
        }

        .card-hdr p {
            color: rgba(255,255,255,.58);
            font-size: 13px;
        }

        .card-body {
            padding: 28px 32px;
        }

        .admin-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--light);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
        }

        .chip i {
            color: var(--primary);
        }

        .sec {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--light-darker);
        }

        .sec:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .sec-title {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            color: var(--dark);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sec-title i {
            color: var(--primary);
        }

        .form-g {
            margin-bottom: 16px;
        }

        .form-g label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
        }

        .req {
            color: var(--danger);
            margin-left: 2px;
        }

        .fc {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-family: 'Source Sans 3', sans-serif;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
            background: var(--white);
        }

        .fc:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13,115,119,.10);
        }

        select.fc {
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .pw-meter {
            display: flex;
            gap: 3px;
            align-items: center;
            margin-top: 6px;
        }

        .pw-bar {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: var(--light-darker);
        }

        .pw-lbl {
            font-size: 11px;
            font-weight: 600;
            min-width: 50px;
            text-align: right;
        }

        .match-hint {
            font-size: 11px;
            font-weight: 600;
            margin-top: 3px;
            display: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Source Sans 3', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
        }

        .btn-p {
            background: var(--primary);
            color: white;
        }

        .btn-p:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        .btn-a {
            background: var(--accent);
            color: white;
        }

        .btn-a:hover {
            background: var(--accent-dark);
            color: white;
        }

        .btn-d {
            background: var(--danger);
            color: white;
        }

        .btn-d:hover {
            background: #b02a37;
            color: white;
        }

        .btn-g {
            background: transparent;
            color: var(--text-muted);
            border: 2px solid var(--border);
        }

        .btn-g:hover {
            background: var(--light);
            color: var(--dark);
            border-color: var(--text-muted);
        }

        .btn-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .gen-btn {
            white-space: nowrap;
            padding: 10px 14px;
            font-size: 13px;
            flex-shrink: 0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 500;
            font-size: 13px;
        }

        .alert-s {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-d {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .warn {
            background: rgba(220,53,69,.05);
            border: 1.5px solid rgba(220,53,69,.15);
            border-radius: 8px;
            padding: 14px 18px;
            margin-top: 18px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #721c24;
        }

        .warn i {
            color: var(--danger);
            font-size: 18px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .foot {
            text-align: center;
            padding: 16px 32px 24px;
            border-top: 1px solid var(--light-darker);
            font-size: 13px;
            line-height: 2;
        }

        .foot a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin: 0 3px;
        }

        .foot a:hover {
            color: var(--accent);
        }

        .danger-zone {
            border: 1px solid rgba(220,53,69,.15);
            background: rgba(220,53,69,.025);
            border-radius: 10px;
            padding: 18px;
        }

        .danger-zone .sec-title {
            margin-bottom: 8px;
        }

        .danger-description {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px 10px;
                align-items: flex-start;
            }

            .card {
                margin: 10px 0;
                border-radius: 14px;
            }

            .card-hdr {
                padding: 20px;
            }

            .card-hdr h1 {
                font-size: 18px;
            }

            .card-body {
                padding: 22px 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .btn-row {
                flex-direction: column;
            }

            .btn-row .btn {
                width: 100%;
            }

            .foot {
                padding: 15px 16px 20px;
            }
        }

        @media (max-width: 420px) {
            .card-hdr .ico {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            .card-hdr {
                gap: 10px;
            }

            .admin-chips {
                flex-direction: column;
                align-items: flex-start;
            }

            .password-input-row {
                flex-direction: column !important;
            }

            .password-input-row .gen-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="card">

    <!-- =====================================================
         HEADER
         ===================================================== -->
    <div class="card-hdr">

        <span class="ico">
            <i class="fas fa-user-shield"></i>
        </span>

        <div>
            <h1>Admin Credential Manager</h1>
            <p>SK Travel Planner</p>
        </div>

    </div>

    <div class="card-body">

        <!-- =================================================
             ERROR MESSAGES
             ================================================= -->
        <?php if (!empty($errors)): ?>

            <?php foreach ($errors as $er): ?>

                <div class="alert alert-d">
                    <i class="fas fa-times-circle"></i>
                    <?= e($er) ?>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

        <!-- =================================================
             SUCCESS MESSAGE
             ================================================= -->
        <?php if ($success): ?>

            <div class="alert alert-s">
                <i class="fas fa-check-circle"></i>
                <?= e($success) ?>
            </div>

        <?php endif; ?>


        <!-- =================================================
             ADMIN ACCOUNTS
             ================================================= -->
        <?php if (!empty($admins)): ?>

            <div class="admin-chips">

                <?php foreach ($admins as $a): ?>

                    <span class="chip">

                        <i class="fas fa-user"></i>

                        <?= e($a['username']) ?>

                        <?php if (!empty($a['created_at'])): ?>

                            <span
                                style="
                                    color:var(--text-muted);
                                    font-size:11px;
                                    margin-left:2px;
                                "
                            >
                                <?= e(
                                    date(
                                        'M j, Y',
                                        strtotime($a['created_at'])
                                    )
                                ) ?>
                            </span>

                        <?php endif; ?>

                    </span>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             CHANGE PASSWORD
             ================================================= -->
        <div class="sec">

            <div class="sec-title">
                <i class="fas fa-key"></i>
                Change Password
            </div>

            <form method="POST" autocomplete="off">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="act"
                    value="chpass"
                >

                <div class="form-g">

                    <label>
                        Select Account
                        <span class="req">*</span>
                    </label>

                    <select
                        name="uid"
                        class="fc"
                        required
                    >

                        <option value="">
                            — Choose admin —
                        </option>

                        <?php foreach ($admins as $a): ?>

                            <option
                                value="<?= (int)$a['id'] ?>"
                            >
                                <?= e($a['username']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-g">

                    <label>
                        Current Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="cur"
                        class="fc"
                        placeholder="Verify current password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <div class="form-g">

                    <label>
                        New Password
                        <span class="req">*</span>
                    </label>

                    <div
                        class="password-input-row"
                        style="display:flex;gap:6px;"
                    >

                        <input
                            type="password"
                            name="new"
                            id="pw_new"
                            class="fc"
                            style="flex:1;"
                            placeholder="Min 8 characters"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-a gen-btn"
                            onclick="openPasswordGenerator()"
                        >
                            <i class="fas fa-key"></i>
                            Gen
                        </button>

                    </div>

                    <div
                        class="pw-meter"
                        id="pw-meter"
                        style="display:none;"
                    >

                        <div
                            class="pw-bar"
                            id="pb1"
                        ></div>

                        <div
                            class="pw-bar"
                            id="pb2"
                        ></div>

                        <div
                            class="pw-bar"
                            id="pb3"
                        ></div>

                        <div
                            class="pw-bar"
                            id="pb4"
                        ></div>

                        <span
                            class="pw-lbl"
                            id="plbl"
                        ></span>

                    </div>

                </div>

                <div class="form-g">

                    <label>
                        Confirm New Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="cnf"
                        id="pw_cnf"
                        class="fc"
                        placeholder="Re-enter new password"
                        autocomplete="new-password"
                        required
                    >

                    <div
                        class="match-hint"
                        id="mh"
                    ></div>

                </div>

                <div class="btn-row">

                    <button
                        type="submit"
                        class="btn btn-p"
                    >
                        <i class="fas fa-save"></i>
                        Update Password
                    </button>

                </div>

            </form>

        </div>


        <!-- =================================================
             CHANGE USERNAME
             ================================================= -->
        <div class="sec">

            <div class="sec-title">
                <i class="fas fa-user-edit"></i>
                Change Username
            </div>

            <form method="POST" autocomplete="off">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="act"
                    value="chuser"
                >

                <div class="form-row">

                    <div class="form-g">

                        <label>
                            Select Account
                            <span class="req">*</span>
                        </label>

                        <select
                            name="uid"
                            class="fc"
                            required
                        >

                            <option value="">
                                — Choose admin —
                            </option>

                            <?php foreach ($admins as $a): ?>

                                <option
                                    value="<?= (int)$a['id'] ?>"
                                >
                                    <?= e($a['username']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-g">

                        <label>
                            New Username
                            <span class="req">*</span>
                        </label>

                        <input
                            type="text"
                            name="nusr"
                            class="fc"
                            placeholder="Letters, numbers, underscores"
                            minlength="3"
                            maxlength="50"
                            pattern="[a-zA-Z0-9_]+"
                            autocomplete="off"
                            required
                        >

                    </div>

                </div>

                <div class="form-g">

                    <label>
                        Current Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="cur"
                        class="fc"
                        placeholder="Enter current password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <div class="btn-row">

                    <button
                        type="submit"
                        class="btn btn-p"
                    >
                        <i class="fas fa-save"></i>
                        Update Username
                    </button>

                </div>

            </form>

        </div>


        <!-- =================================================
             CREATE ADMIN
             ================================================= -->
        <div class="sec">

            <div class="sec-title">
                <i class="fas fa-user-plus"></i>
                Create New Admin
            </div>

            <form method="POST" autocomplete="off">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($csrf) ?>"
                >

                <input
                    type="hidden"
                    name="act"
                    value="create"
                >

                <div class="form-g">

                    <label>
                        Username
                        <span class="req">*</span>
                    </label>

                    <input
                        type="text"
                        name="nusr"
                        class="fc"
                        placeholder="e.g. superadmin"
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]+"
                        autocomplete="off"
                        required
                    >

                </div>

                <div class="form-g">

                    <label>
                        Password
                        <span class="req">*</span>
                    </label>

                    <div
                        class="password-input-row"
                        style="display:flex;gap:6px;"
                    >

                        <input
                            type="password"
                            name="npass"
                            id="cr_pw"
                            class="fc"
                            style="flex:1;"
                            placeholder="Min 8 characters"
                            minlength="8"
                            maxlength="128"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-a gen-btn"
                            onclick="openPasswordGenerator()"
                        >
                            <i class="fas fa-key"></i>
                            Gen
                        </button>

                    </div>

                </div>

                <div class="form-g">

                    <label>
                        Confirm Password
                        <span class="req">*</span>
                    </label>

                    <input
                        type="password"
                        name="ncnf"
                        id="cr_cnf"
                        class="fc"
                        placeholder="Re-enter password"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <div class="btn-row">

                    <button
                        type="submit"
                        class="btn btn-a"
                    >
                        <i class="fas fa-user-plus"></i>
                        Create Admin
                    </button>

                </div>

            </form>

        </div>


        <!-- =================================================
             DANGER ZONE
             ================================================= -->
        <div class="sec">

            <div class="danger-zone">

                <div
                    class="sec-title"
                    style="color:var(--danger);"
                >
                    <i class="fas fa-trash-alt"></i>
                    Delete Admin Account
                </div>

                <p class="danger-description">
                    This permanently removes the account.
                    This action cannot be undone.
                </p>

                <form
                    method="POST"
                    onsubmit="return confirmDelete();"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($csrf) ?>"
                    >

                    <input
                        type="hidden"
                        name="act"
                        value="deladm"
                    >

                    <div class="form-row">

                        <div class="form-g">

                            <select
                                name="uid"
                                class="fc"
                                required
                            >

                                <option value="">
                                    — Select admin —
                                </option>

                                <?php foreach ($admins as $a): ?>

                                    <option
                                        value="<?= (int)$a['id'] ?>"
                                    >
                                        <?= e($a['username']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-g">

                            <input
                                type="password"
                                name="cur"
                                class="fc"
                                placeholder="Current password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                    </div>

                    <div class="btn-row">

                        <button
                            type="submit"
                            class="btn btn-d"
                        >
                            <i class="fas fa-trash-alt"></i>
                            Delete
                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
             SECURITY WARNING
             ================================================= -->
        <div class="warn">

            <i class="fas fa-exclamation-triangle"></i>

            <span>
                <strong>Security warning:</strong>
                Delete or protect this credential-management page
                after setup. Leaving credential management publicly
                accessible creates a security risk.
            </span>

        </div>

    </div>


    <!-- =====================================================
         FOOTER
         ===================================================== -->
    <div class="foot">

        <!-- ROOT website -->
        <a href="../index.php">
            <i class="fas fa-home"></i>
            Website
        </a>

        &bull;

        <!-- Current admin directory -->
        <a href="index.php">
            <i class="fas fa-lock"></i>
            Admin Panel
        </a>

        &bull;

        <!-- Generator assumed to be inside /admin/ -->
        <a href="password-generator.php">
            <i class="fas fa-key"></i>
            Password Generator
        </a>

    </div>

</div>


<script>
/* ============================================================
   Password Generator
   ============================================================ */

function openPasswordGenerator() {
    window.open(
        'password-generator.php',
        'pwgen',
        'width=640,height=800,resizable=yes,scrollbars=yes'
    );
}


/* ============================================================
   Password Strength
   ============================================================ */

var pwf = document.getElementById('pw_new');

if (pwf) {

    pwf.addEventListener('input', function () {

        var v = this.value;
        var meter = document.getElementById('pw-meter');

        if (!v) {
            meter.style.display = 'none';
            return;
        }

        meter.style.display = 'flex';

        var score = 0;

        if (v.length >= 8) {
            score++;
        }

        if (v.length >= 12) {
            score++;
        }

        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) {
            score++;
        }

        if (/[0-9]/.test(v)) {
            score++;
        }

        if (/[^a-zA-Z0-9]/.test(v)) {
            score++;
        }

        score = Math.min(score, 4);

        var colors = [
            '#DC3545',
            '#E8912D',
            '#D4A017',
            '#2E8B57'
        ];

        var labels = [
            'Weak',
            'Fair',
            'Good',
            'Strong'
        ];

        for (var i = 1; i <= 4; i++) {

            var bar = document.getElementById('pb' + i);

            if (bar) {
                bar.style.background =
                    (i <= score)
                        ? colors[score - 1]
                        : '#EDE7DB';
            }
        }

        var label = document.getElementById('plbl');

        if (label) {

            label.textContent =
                labels[score - 1] || 'Weak';

            label.style.color =
                colors[score - 1] || '#DC3545';
        }

    });

}


/* ============================================================
   Confirm Password
   ============================================================ */

var cnf = document.getElementById('pw_cnf');

if (cnf) {

    cnf.addEventListener('input', function () {

        var pw =
            document.getElementById('pw_new').value;

        var hint =
            document.getElementById('mh');

        if (!this.value) {

            hint.style.display = 'none';

            return;
        }

        hint.style.display = 'block';

        if (pw === this.value) {

            hint.textContent =
                'Passwords match';

            hint.style.color =
                '#2E8B57';

        } else {

            hint.textContent =
                'Passwords do not match';

            hint.style.color =
                '#DC3545';
        }

    });

}


/* ============================================================
   Password Generator Message
   ============================================================ */

window.addEventListener('message', function (ev) {

    if (
        !ev.data ||
        ev.data.type !== 'password' ||
        typeof ev.data.password !== 'string'
    ) {
        return;
    }

    var pw = ev.data.password;

    var changePw =
        document.getElementById('pw_new');

    var changeCnf =
        document.getElementById('pw_cnf');

    var createPw =
        document.getElementById('cr_pw');

    var createCnf =
        document.getElementById('cr_cnf');


    /*
     * If the change-password fields exist,
     * use those first.
     */
    if (changePw && changeCnf) {

        changePw.value = pw;
        changeCnf.value = pw;

        changePw.dispatchEvent(
            new Event('input', { bubbles: true })
        );

        changeCnf.dispatchEvent(
            new Event('input', { bubbles: true })
        );

        return;
    }


    /*
     * Otherwise fill Create Admin fields.
     */
    if (createPw && createCnf) {

        createPw.value = pw;
        createCnf.value = pw;

    }

});


/* ============================================================
   Delete Confirmation
   ============================================================ */

function confirmDelete() {

    return confirm(
        'DELETE this admin account permanently?\n\n' +
        'This action cannot be undone.'
    );

}
</script>

</body>
</html>