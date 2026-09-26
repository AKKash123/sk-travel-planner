<?php
// ============================================================
// SK Travel Planner — 403 Forbidden Error Page
// ============================================================

$logUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
$logIp  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Log the 403 error safely
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/error.log';

if (is_dir($logDir) && is_writable($logDir)) {
    $entry = date('Y-m-d H:i:s')
        . " | 403 Forbidden"
        . " | IP:" . $logIp
        . " | URL:" . $logUri
        . PHP_EOL;

    @file_put_contents(
        $logFile,
        $entry,
        FILE_APPEND | LOCK_EX
    );
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

    <title>403 Forbidden — SK Travel Planner</title>

    <!-- Root-relative CSS path -->
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="/style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
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
            background: rgba(220, 53, 69, 0.10);
            color: #DC3545;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        h1 {
            font-family: "Playfair Display", Georgia, serif;
            font-size: clamp(72px, 15vw, 120px);
            line-height: 1;
            color: #DC3545;
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
            <i class="fas fa-shield-halved"></i>
        </div>

        <h1>403</h1>

        <h2>Access Denied</h2>

        <p>
            You don't have permission to access this resource.
            Please return to the homepage and continue browsing.
        </p>

        <!-- Root-relative URL -->
        <a
            class="btn"
            href="/index.php"
        >
            <i class="fas fa-home"></i>
            Go Home
        </a>

    </main>

</body>
</html>