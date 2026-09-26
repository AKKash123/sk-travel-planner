<?php
// ============================================================
// SK Travel Planner — Professional 500 Error Page
// Auto-detects application base path
// Works with /skplanner/ and domain root deployments
// ============================================================

http_response_code(500);

/*
|--------------------------------------------------------------------------
| Detect Application Base Path
|--------------------------------------------------------------------------
|
| Local:
|   /skplanner/error-500.php
|   => /skplanner
|
| Production root:
|   /error-500.php
|   => ""
|
*/

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

$scriptDirectory = dirname(
    str_replace('\\', '/', $scriptName)
);

$basePath = rtrim($scriptDirectory, '/');

if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}

/*
|--------------------------------------------------------------------------
| Application URL Helper
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

$logUri = $_SERVER['REQUEST_URI'] ?? '/';
$logIp  = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
|--------------------------------------------------------------------------
| Prevent Log Injection
|--------------------------------------------------------------------------
*/

$logUri = str_replace(["\r", "\n"], '', $logUri);
$logIp  = str_replace(["\r", "\n"], '', $logIp);
$method = str_replace(["\r", "\n"], '', $method);

/*
|--------------------------------------------------------------------------
| Log Server Error
|--------------------------------------------------------------------------
*/

$logDirectory = __DIR__ . '/logs';
$logFile = $logDirectory . '/error.log';

if (is_dir($logDirectory) && is_writable($logDirectory)) {

    $logEntry =
        date('Y-m-d H:i:s') .
        " | 500" .
        " | METHOD:{$method}" .
        " | IP:{$logIp}" .
        " | URL:{$logUri}" .
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

    <title>Server Error — SK Travel Planner</title>
        <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" href="assets/icons/favicon.ico">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">
    <meta
        name="description"
        content="A temporary server error occurred on SK Travel Planner."
    >

    <!--
        IMPORTANT:
        No local style.css dependency here.

        Error pages should remain functional even if
        style.css itself is unavailable.
    -->

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
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800;900&family=Source+Sans+3:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
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
                "Source Sans 3",
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            color: #1B2838;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(13, 115, 119, 0.10),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(232, 145, 45, 0.12),
                    transparent 34%
                ),
                #F7F3ED;

            display: flex;
            flex-direction: column;
        }

        /* =====================================================
           PAGE
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

            background: rgba(255, 255, 255, 0.80);

            border-bottom:
                1px solid rgba(27, 40, 56, 0.08);

            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .header-inner {
            width: 100%;
            max-width: 1180px;

            margin: 0 auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* =====================================================
           BRAND
        ===================================================== */

        .brand {
            display: inline-flex;

            align-items: center;

            gap: 13px;

            text-decoration: none;

            color: #1B2838;
        }

        .brand-mark {
            width: 46px;
            height: 46px;

            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #0D7377,
                    #095c60
                );

            color: #ffffff;

            font-size: 20px;

            box-shadow:
                0 10px 24px rgba(13, 115, 119, 0.20);
        }

        .brand-text {
            display: flex;
            flex-direction: column;

            line-height: 1.1;
        }

        .brand-name {
            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size: 18px;

            font-weight: 700;

            letter-spacing: -0.3px;
        }

        .brand-tagline {
            margin-top: 5px;

            color: #0D7377;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform: uppercase;
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            flex: 1;

            width: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            padding:
                70px 20px
                80px;
        }

        .error-container {
            width: 100%;
            max-width: 850px;

            text-align: center;
        }

        /* =====================================================
           ERROR ICON
        ===================================================== */

        .icon-wrapper {
            width: 82px;
            height: 82px;

            margin: 0 auto 24px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background:
                rgba(232, 145, 45, 0.12);

            border:
                1px solid rgba(232, 145, 45, 0.20);

            color: #E8912D;

            font-size: 32px;

            box-shadow:
                0 15px 35px rgba(27, 40, 56, 0.07);
        }

        /* =====================================================
           ERROR NUMBER
        ===================================================== */

        .error-number {
            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size:
                clamp(105px, 20vw, 190px);

            line-height: 0.85;

            font-weight: 900;

            letter-spacing: -7px;

            color: #E8912D;

            text-shadow:
                7px 7px 0 rgba(13, 115, 119, 0.08);

            user-select: none;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {
            margin-top: 38px;
        }

        .eyebrow {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 7px 14px;

            border-radius: 999px;

            background:
                rgba(13, 115, 119, 0.08);

            color: #0D7377;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: 1px;

            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #E8912D;

            box-shadow:
                0 0 0 4px
                rgba(232, 145, 45, 0.10);
        }

        h1 {
            margin-top: 18px;

            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size:
                clamp(32px, 5vw, 52px);

            line-height: 1.1;

            font-weight: 700;

            letter-spacing: -1px;

            color: #1B2838;
        }

        .description {
            max-width: 600px;

            margin: 17px auto 0;

            color: #6B7280;

            font-size: 17px;

            line-height: 1.7;
        }

        /* =====================================================
           REQUEST INFORMATION
        ===================================================== */

        .request-box {
            max-width: 680px;

            margin:
                28px auto 0;

            padding: 14px 18px;

            border-radius: 14px;

            background:
                rgba(255, 255, 255, 0.70);

            border:
                1px solid rgba(27, 40, 56, 0.08);

            text-align: left;

            box-shadow:
                0 10px 30px
                rgba(27, 40, 56, 0.04);
        }

        .request-label {
            display: block;

            margin-bottom: 6px;

            color: #0D7377;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1px;

            text-transform: uppercase;
        }

        .request-url {
            display: block;

            width: 100%;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            font-family:
                "SFMono-Regular",
                Consolas,
                "Liberation Mono",
                monospace;

            font-size: 12px;

            color: #6B7280;
        }

        /* =====================================================
           BUTTONS
        ===================================================== */

        .actions {
            margin-top: 32px;

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

            padding:
                0 21px;

            border-radius: 13px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 700;

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
                0 12px 28px
                rgba(13, 115, 119, 0.20);
        }

        .btn-primary:hover {
            background: #095c60;

            box-shadow:
                0 15px 32px
                rgba(13, 115, 119, 0.28);
        }

        .btn-secondary {
            color: #1B2838;

            background: #ffffff;

            border:
                1px solid
                rgba(27, 40, 56, 0.10);
        }

        .btn-secondary:hover {
            background: #fafafa;

            box-shadow:
                0 10px 25px
                rgba(27, 40, 56, 0.08);
        }

        .btn-admin {
            color: #9A5D10;

            background:
                rgba(232, 145, 45, 0.10);

            border:
                1px solid
                rgba(232, 145, 45, 0.16);
        }

        .btn-admin:hover {
            background:
                rgba(232, 145, 45, 0.17);
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {
            padding: 21px 20px;

            border-top:
                1px solid
                rgba(27, 40, 56, 0.07);

            text-align: center;

            color: #8A939D;

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
                padding:
                    15px 18px;
            }

            .brand-mark {
                width: 40px;
                height: 40px;

                border-radius: 12px;

                font-size: 17px;
            }

            .brand-name {
                font-size: 16px;
            }

            .brand-tagline {
                font-size: 8px;
                letter-spacing: 0.7px;
            }

            .main {
                padding:
                    55px 18px
                    65px;
            }

            .icon-wrapper {
                width: 68px;
                height: 68px;

                font-size: 27px;

                margin-bottom: 20px;
            }

            .error-number {
                font-size: 110px;
                letter-spacing: -5px;
            }

            .content {
                margin-top: 30px;
            }

            h1 {
                font-size: 31px;
            }

            .description {
                font-size: 15px;

                line-height: 1.65;
            }

            .request-box {
                margin-top: 23px;

                padding:
                    12px 14px;
            }

            .request-url {
                font-size: 11px;
            }

            .actions {
                width: 100%;

                flex-direction: column;

                margin-top: 27px;
            }

            .btn {
                width: 100%;
                max-width: 330px;
            }

        }

        /* =====================================================
           SMALL PHONES
        ===================================================== */

        @media (max-width: 360px) {

            .brand-tagline {
                display: none;
            }

            .error-number {
                font-size: 95px;
            }

            h1 {
                font-size: 28px;
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

                <span
                    class="brand-mark"
                    aria-hidden="true"
                >
                    <i class="fa-solid fa-compass"></i>
                </span>

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
                class="icon-wrapper"
                aria-hidden="true"
            >
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>


            <div
                class="error-number"
                aria-hidden="true"
            >
                500
            </div>


            <div class="content">

                <div class="eyebrow">

                    <span class="status-dot"></span>

                    Temporary problem

                </div>


                <h1>
                    Something went wrong.
                </h1>


                <p class="description">
                    We encountered an unexpected server problem
                    while processing your request. The issue has
                    been recorded, and you can try again or
                    continue exploring SK Travel Planner.
                </p>


                <div class="request-box">

                    <span class="request-label">
                        Requested address
                    </span>

                    <span class="request-url">
                        <?= e($logUri) ?>
                    </span>

                </div>


                <div class="actions">

                    <a
                        class="btn btn-primary"
                        href="<?= e($homeUrl) ?>"
                    >
                        <i class="fa-solid fa-house"></i>
                        Go Home
                    </a>


                    <a
                        class="btn btn-secondary"
                        href="<?= e($itineraryUrl) ?>"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        Explore Itineraries
                    </a>


                    <a
                        class="btn btn-admin"
                        href="<?= e($adminUrl) ?>"
                    >
                        <i class="fa-solid fa-shield-halved"></i>
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