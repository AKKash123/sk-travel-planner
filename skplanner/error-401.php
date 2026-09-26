<?php
// ============================================================
// SK Travel Planner — 401 Unauthorized Error Page
// ============================================================

$logUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
$logIp  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Log error safely
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/error.log';
// define('APP_URL', '/sk-travel-planner');
define('APP_URL', '/skplanner');

if (is_dir($logDir) && is_writable($logDir)) {
    $entry = date('Y-m-d H:i:s')
        . " | 401 Unauthorized"
        . " | IP:" . $logIp
        . " | URL:" . $logUri
        . PHP_EOL;

    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
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
        content="noindex, nofollow"
    >

    <title>401 Unauthorized — SK Travel Planner</title>
    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" href="assets/icons/favicon.ico">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">
    <!-- IMPORTANT: Root-relative path -->
    <link rel="stylesheet"href="<?= APP_URL ?>/style.css">
    
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-TmJm..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    >

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            font-family: "Source Sans 3", Arial, sans-serif;
            background: #F7F3ED;
            color: #1B2838;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
        }

        .error-page {
            width: 100%;
            max-width: 620px;
            padding: 48px 30px;
        }

        .error-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 22px;
            border-radius: 50%;
            background: rgba(212, 160, 23, 0.12);
            color: #D4A017;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        h1 {
            font-family: "Playfair Display", Georgia, serif;
            font-size: clamp(72px, 15vw, 120px);
            line-height: 1;
            color: #D4A017;
            margin-bottom: 12px;
            font-weight: 700;
        }

        h2 {
            font-family: "Playfair Display", Georgia, serif;
            font-size: clamp(24px, 5vw, 34px);
            color: #1B2838;
            margin-bottom: 12px;
        }

        p {
            color: #6B7280;
            font-size: 16px;
            line-height: 1.7;
            margin: 0 auto 28px;
            max-width: 480px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 48px;
            padding: 12px 22px;
            border-radius: 10px;
            background: #0D7377;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .btn:hover {
            background: #095c60;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 115, 119, 0.20);
        }

        .btn:focus-visible {
            outline: 3px solid rgba(13, 115, 119, 0.30);
            outline-offset: 3px;
        }

        @media (max-width: 600px) {
            body {
                padding: 24px 16px;
            }

            .error-page {
                padding: 30px 18px;
            }

            .error-icon {
                width: 62px;
                height: 62px;
                font-size: 25px;
            }

            p {
                font-size: 15px;
            }

            .btn {
                width: 100%;
                max-width: 260px;
            }
        }
    </style>
</head>

<body>

    <main class="error-page">

        <div class="error-icon" aria-hidden="true">
            <i class="fas fa-lock"></i>
        </div>

        <h1>401</h1>

        <h2>Unauthorized</h2>

        <p>
            Authentication is required to access this page.
            Please sign in with an authorized account and try again.
        </p>

        <!-- IMPORTANT: Root-relative URL -->
        <a
            class="btn"
            href="/admin/index.php"
        >
            <i class="fas fa-sign-in-alt"></i>
            Admin Login
        </a>

    </main>

</body>
</html>