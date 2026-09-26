<?php
// ============================================================
// SK Travel Planner — Professional 404 Error Page
// Auto-detects application base path
// Works with /skplanner/ and domain root deployments
// ============================================================

http_response_code(404);

/*
|--------------------------------------------------------------------------
| Detect Application Base Path
|--------------------------------------------------------------------------
|
| Example:
|   /skplanner/error-404.php
|       => /skplanner
|
|   /error-404.php
|       => ""
|
| This avoids hardcoding /skplanner.
|
*/

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

$scriptDirectory = dirname(
    str_replace('\\', '/', $scriptName)
);

$basePath = rtrim($scriptDirectory, '/');

// dirname('/') / dirname('') edge cases
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

/*
|--------------------------------------------------------------------------
| URL Helper
|--------------------------------------------------------------------------
*/

function appUrl(string $path = ''): string
{
    global $basePath;

    $path = ltrim($path, '/');

    if ($path === '') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return $basePath . '/' . $path;
}

/*
|--------------------------------------------------------------------------
| HTML Escape Helper
|--------------------------------------------------------------------------
*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Request Information
|--------------------------------------------------------------------------
*/

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

/*
|--------------------------------------------------------------------------
| Remove CR/LF From Log Values
|--------------------------------------------------------------------------
*/

$requestUri = str_replace(["\r", "\n"], '', $requestUri);
$requestMethod = str_replace(["\r", "\n"], '', $requestMethod);
$requestIp = str_replace(["\r", "\n"], '', $requestIp);
$userAgent = str_replace(["\r", "\n"], '', $userAgent);

/*
|--------------------------------------------------------------------------
| Log 404
|--------------------------------------------------------------------------
*/

$logDirectory = __DIR__ . '/logs';
$logFile = $logDirectory . '/404.log';

if (is_dir($logDirectory) && is_writable($logDirectory)) {

    $logEntry =
        date('Y-m-d H:i:s') .
        ' | 404' .
        ' | METHOD=' . $requestMethod .
        ' | URI=' . $requestUri .
        ' | IP=' . $requestIp .
        ' | UA=' . $userAgent .
        PHP_EOL;

    @file_put_contents(
        $logFile,
        $logEntry,
        FILE_APPEND | LOCK_EX
    );
}

/*
|--------------------------------------------------------------------------
| Generate Correct Application URLs
|--------------------------------------------------------------------------
*/

$homeUrl = appUrl('index.php');
$itineraryUrl = appUrl('index.php#itineraries');
$adminUrl = appUrl('admin/index.php');
$logoUrl = appUrl('logo.jpg');

/*
|--------------------------------------------------------------------------
| Current Year
|--------------------------------------------------------------------------
*/

$currentYear = date('Y');

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

    <title>Page Not Found — SK Travel Planner</title>
        <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" href="assets/icons/favicon.ico">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">
    <meta
        name="description"
        content="The page you are looking for could not be found. Return to SK Travel Planner and explore available travel itineraries."
    >

    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Helvetica,
                Arial,
                sans-serif;

            color: #1B2838;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(13, 115, 119, 0.10),
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(232, 145, 45, 0.12),
                    transparent 35%
                ),
                #F7F3ED;

            display: flex;
            flex-direction: column;
        }

        /* =====================================================
           PAGE WRAPPER
        ===================================================== */

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header {
            width: 100%;
            padding: 20px 5%;
            background: rgba(255, 255, 255, 0.78);
            border-bottom: 1px solid rgba(27, 40, 56, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);

            position: relative;
            z-index: 10;
        }

        .header-inner {
            max-width: 1180px;
            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        /* =====================================================
           BRAND
        ===================================================== */

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 13px;

            color: #1B2838;
            text-decoration: none;

            font-weight: 800;
            font-size: 18px;
        }

        .brand-logo {
            width: 46px;
            height: 46px;

            object-fit: cover;

            border-radius: 13px;

            background: #ffffff;

            border: 1px solid rgba(13, 115, 119, 0.12);

            box-shadow:
                0 8px 25px rgba(27, 40, 56, 0.08);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .brand-name {
            font-size: 17px;
            letter-spacing: -0.3px;
        }

        .brand-tagline {
            margin-top: 4px;

            font-size: 11px;
            font-weight: 600;

            color: #0D7377;

            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            width: 100%;
            flex: 1;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 70px 20px 80px;
        }

        .error-container {
            width: 100%;
            max-width: 900px;

            text-align: center;
        }

        /* =====================================================
           ERROR NUMBER
        ===================================================== */

        .error-number {
            position: relative;

            font-size: clamp(110px, 20vw, 220px);

            line-height: 0.82;

            font-weight: 900;

            letter-spacing: -12px;

            color: #0D7377;

            text-shadow:
                8px 8px 0 rgba(232, 145, 45, 0.13);

            user-select: none;
        }

        .error-number::after {
            content: "";

            position: absolute;

            width: 110px;
            height: 110px;

            border-radius: 50%;

            background: rgba(232, 145, 45, 0.10);

            top: 30%;
            left: 50%;

            transform: translate(-50%, -50%);

            z-index: -1;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .error-content {
            margin-top: 42px;
        }

        .eyebrow {
            display: inline-flex;

            align-items: center;
            gap: 8px;

            padding: 8px 14px;

            border-radius: 999px;

            background: rgba(13, 115, 119, 0.08);

            color: #0D7377;

            font-size: 12px;
            font-weight: 800;

            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #E8912D;
        }

        h1 {
            margin-top: 20px;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: clamp(32px, 5vw, 54px);

            line-height: 1.08;

            letter-spacing: -1.5px;

            color: #1B2838;
        }

        .description {
            max-width: 610px;

            margin: 18px auto 0;

            font-size: 17px;

            line-height: 1.75;

            color: #66727f;
        }

        /* =====================================================
           REQUEST BOX
        ===================================================== */

        .request-box {
            max-width: 700px;

            margin: 30px auto 0;

            padding: 14px 18px;

            border: 1px solid rgba(27, 40, 56, 0.08);

            background: rgba(255, 255, 255, 0.68);

            border-radius: 14px;

            overflow: hidden;
        }

        .request-label {
            display: block;

            margin-bottom: 6px;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1px;

            text-transform: uppercase;

            color: #0D7377;
        }

        .request-url {
            display: block;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            font-family:
                "SFMono-Regular",
                Consolas,
                "Liberation Mono",
                monospace;

            font-size: 13px;

            color: #66727f;
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions {
            margin-top: 34px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-wrap: wrap;

            gap: 12px;
        }

        .btn {
            min-height: 50px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            padding: 0 22px;

            border-radius: 13px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 750;

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            color: #ffffff;

            background: #0D7377;

            box-shadow:
                0 12px 28px rgba(13, 115, 119, 0.20);
        }

        .btn-primary:hover {
            background: #095c60;

            box-shadow:
                0 15px 32px rgba(13, 115, 119, 0.28);
        }

        .btn-secondary {
            color: #1B2838;

            background: #ffffff;

            border: 1px solid rgba(27, 40, 56, 0.10);
        }

        .btn-secondary:hover {
            background: #fafafa;

            box-shadow:
                0 10px 25px rgba(27, 40, 56, 0.08);
        }

        .btn-admin {
            color: #9a5d10;

            background: rgba(232, 145, 45, 0.10);

            border: 1px solid rgba(232, 145, 45, 0.16);
        }

        .btn-admin:hover {
            background: rgba(232, 145, 45, 0.16);
        }

        .icon {
            font-size: 18px;
            line-height: 1;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            padding: 22px 20px;

            text-align: center;

            border-top: 1px solid rgba(27, 40, 56, 0.07);

            color: #8a939d;

            font-size: 12px;
        }

        .footer strong {
            color: #0D7377;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 600px) {

            .header {
                padding: 15px 18px;
            }

            .brand-logo {
                width: 40px;
                height: 40px;
                border-radius: 11px;
            }

            .brand-name {
                font-size: 15px;
            }

            .brand-tagline {
                font-size: 9px;
            }

            .main {
                padding:
                    55px 18px
                    65px;
            }

            .error-number {
                letter-spacing: -7px;
            }

            .error-content {
                margin-top: 32px;
            }

            .description {
                font-size: 15px;
                line-height: 1.65;
            }

            .request-box {
                margin-top: 24px;
                padding: 12px 14px;
            }

            .request-url {
                font-size: 11px;
            }

            .actions {
                width: 100%;

                flex-direction: column;

                margin-top: 28px;
            }

            .btn {
                width: 100%;
                max-width: 330px;
            }

        }

        /* =====================================================
           VERY SMALL DEVICES
        ===================================================== */

        @media (max-width: 360px) {

            h1 {
                font-size: 29px;
            }

            .error-number {
                font-size: 105px;
            }

            .brand-tagline {
                display: none;
            }

        }

        /* =====================================================
           REDUCED MOTION
        ===================================================== */

        @media (prefers-reduced-motion: reduce) {

            html {
                scroll-behavior: auto;
            }

            .btn {
                transition: none;
            }

            .btn:hover {
                transform: none;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="header">

        <div class="header-inner">

            <a
                class="brand"
                href="<?= e($homeUrl) ?>"
                aria-label="SK Travel Planner Home"
            >

                <img
                    class="brand-logo"
                    src="<?= e($logoUrl) ?>"
                    alt="SK Travel Planner"
                    onerror="this.style.display='none';"
                >

                <span class="brand-text">

                    <span class="brand-name">
                        SK Travel Planner
                    </span>

                    <span class="brand-tagline">
                        Explore • Experience • Remember
                    </span>

                </span>

            </a>

        </div>

    </header>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">

        <div class="error-container">

            <div
                class="error-number"
                aria-hidden="true"
            >
                404
            </div>


            <div class="error-content">

                <div class="eyebrow">

                    <span class="eyebrow-dot"></span>

                    Wrong turn

                </div>


                <h1>
                    This journey doesn't exist.
                </h1>


                <p class="description">
                    The page you're looking for may have moved,
                    been removed, or the address may have been
                    entered incorrectly. Let's get you back on
                    the right path.
                </p>


                <div class="request-box">

                    <span class="request-label">
                        Requested address
                    </span>

                    <span class="request-url">
                        <?= e($requestUri) ?>
                    </span>

                </div>


                <div class="actions">

                    <a
                        class="btn btn-primary"
                        href="<?= e($homeUrl) ?>"
                    >
                        <span class="icon">⌂</span>
                        Back to Home
                    </a>


                    <a
                        class="btn btn-secondary"
                        href="<?= e($itineraryUrl) ?>"
                    >
                        <span class="icon">✦</span>
                        Explore Itineraries
                    </a>


                    <a
                        class="btn btn-admin"
                        href="<?= e($adminUrl) ?>"
                    >
                        <span class="icon">⚙</span>
                        Admin Panel
                    </a>

                </div>

            </div>

        </div>

    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="footer">

        © <?= e((string)$currentYear) ?>

        <strong>SK Travel Planner</strong>

        · Travel made simpler.

    </footer>

</div>

</body>

</html>