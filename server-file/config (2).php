<?php
/**
 * ============================================================
 * SK Travel Planner
 * Production Configuration
 * ============================================================
 *
 * IMPORTANT:
 * 1. Put this file in the same directory as index.php
 * 2. Replace DB credentials with the EXACT cPanel values
 * 3. Use HTTPS in APP_URL
 * 4. Do NOT display PHP errors in production
 * 5. Delete/disable setup and debug files before production
 */

declare(strict_types=1);


/* ============================================================
   ERROR REPORTING
   ============================================================ */

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set(
    'error_log',
    '/home/sktravel/logs/php.error.log'
);



/* ============================================================
   APPLICATION
   ============================================================ */

define('APP_NAME', 'SK Travel Planner');

/*
 * IMPORTANT:
 * Change this to your real production URL.
 */
define(
    'APP_URL',
    'https://sktravel-planners.com'
);


/* ============================================================
   DATABASE
   ============================================================ */

/*
 * IMPORTANT:
 * On cPanel these are usually prefixed.
 *
 * Example:
 *
 * Database:
 *   yourcpaneluser_sktravel_planner
 *
 * Username:
 *   yourcpaneluser_sktravel_dbadmin
 *
 * DO NOT blindly use the example values.
 * Use the exact values shown in:
 *
 * cPanel → MySQL Databases
 */

define('DB_HOST', 'localhost');

define(
    'DB_NAME',
    'sktravel_planner'
);

define(
    'DB_USER',
    'sktravel_dbadmin'
);

/*
 * Must match the password of this DB user in cPanel > MySQL Databases.
 */
define(
    'DB_PASS',
    'sktravel@2026'
);


/* ============================================================
   PATHS
   ============================================================ */

define(
    'BASE_PATH',
    __DIR__
);

define(
    'UPLOAD_DIR',
    BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
);

define(
    'UPLOAD_URL',
    rtrim(APP_URL, '/') . '/uploads/'
);


/* ============================================================
   UPLOAD SETTINGS
   ============================================================ */

define(
    'MAX_FILE_SIZE',
    5 * 1024 * 1024
);

define(
    'ALLOWED_EXT',
    [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp'
    ]
);


/* ============================================================
   LOG DIRECTORY
   ============================================================ */

define(
    'LOG_DIR',
    BASE_PATH . DIRECTORY_SEPARATOR . 'logs'
);


/*
 * Create logs directory if possible.
 */
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}


/* ============================================================
   APPLICATION ERROR LOG
   ============================================================ */

ini_set(
    'error_log',
    LOG_DIR . DIRECTORY_SEPARATOR . 'error.log'
);


/* ============================================================
   SESSION SECURITY
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {

    /*
     * Detect HTTPS.
     */
    $isHttps = false;

    if (
        isset($_SERVER['HTTPS']) &&
        strtolower((string) $_SERVER['HTTPS']) !== 'off' &&
        $_SERVER['HTTPS'] !== ''
    ) {
        $isHttps = true;
    }

    if (
        isset($_SERVER['SERVER_PORT']) &&
        (int) $_SERVER['SERVER_PORT'] === 443
    ) {
        $isHttps = true;
    }

    /*
     * cPanel / Cloudflare / reverse proxy HTTPS detection.
     */
    if (
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
    ) {
        $isHttps = true;
    }


    ini_set(
        'session.cookie_httponly',
        '1'
    );

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.cookie_secure',
        $isHttps ? '1' : '0'
    );

    ini_set(
        'session.cookie_samesite',
        'Lax'
    );

    session_start();
}


/* ============================================================
   SECURITY HEADERS
   ============================================================ */

if (!headers_sent()) {

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'X-Frame-Options: SAMEORIGIN'
    );

    header(
        'Referrer-Policy: strict-origin-when-cross-origin'
    );

    header(
        'Permissions-Policy: geolocation=(), microphone=(), camera=()'
    );
}


/* ============================================================
   DATABASE CONNECTION
   ============================================================ */

$pdo = null;

try {

    $dsn =
        'mysql:host=' .
        DB_HOST .
        ';dbname=' .
        DB_NAME .
        ';charset=utf8mb4';


    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,

            PDO::ATTR_TIMEOUT =>
                5
        ]
    );


} catch (Throwable $e) {

    /*
     * NEVER show the real database error to visitors.
     */

    error_log(
        '[SK Travel Planner] Database connection failed: ' .
        $e->getMessage()
    );


    /*
     * HTTP 500.
     */

    if (!headers_sent()) {
        http_response_code(500);

        header(
            'Content-Type: text/html; charset=UTF-8'
        );
    }


    /*
     * IMPORTANT:
     * Do NOT require another PHP file here.
     *
     * If error-500.php has an error, it can hide
     * the original database problem.
     *
     * This fallback is deliberately self-contained.
     */

    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Temporarily Unavailable — SK Travel Planner</title>

<style>
* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        radial-gradient(
            circle at top left,
            rgba(13, 115, 119, 0.10),
            transparent 35%
        ),
        linear-gradient(
            180deg,
            #fffaf3 0%,
            #f7f3ed 100%
        );

    color: #1B2838;
}

.error-box {
    width: 100%;
    max-width: 560px;

    padding: 42px 30px;

    text-align: center;

    background: #ffffff;

    border-radius: 24px;

    box-shadow:
        0 20px 60px
        rgba(27, 40, 56, 0.12);
}

.logo {
    width: 70px;
    height: 70px;

    margin: 0 auto 20px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 20px;

    background: #0D7377;

    color: #ffffff;

    font-size: 26px;
    font-weight: 900;
}

.code {
    margin: 0;

    font-size: clamp(64px, 15vw, 110px);

    line-height: 0.9;

    font-weight: 900;

    color: #E8912D;
}

h1 {
    margin: 22px 0 10px;

    font-size: clamp(24px, 5vw, 34px);
}

p {
    margin: 0 auto;

    max-width: 430px;

    color: #667085;

    line-height: 1.7;
}

.brand {
    margin-top: 24px;

    color: #0D7377;

    font-weight: 700;
}

@media (max-width: 480px) {

    body {
        padding: 16px;
    }

    .error-box {
        padding: 34px 22px;
        border-radius: 20px;
    }
}
</style>

</head>

<body>

<main class="error-box">

    <div class="logo">
        SK
    </div>

    <p class="code">
        500
    </p>

    <h1>
        Temporarily Unavailable
    </h1>

    <p>
        SK Travel Planner is temporarily unable to connect
        to the application database.
        Please try again shortly.
    </p>

    <div class="brand">
        SK Travel Planner
    </div>

</main>

</body>
</html>';

    exit;
}


/* ============================================================
   SECURITY HELPERS
   ============================================================ */

/**
 * Escape HTML output.
 */
function e($string): string
{
    return htmlspecialchars(
        (string) $string,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}


/**
 * Clean basic input.
 */
function clean($string): string
{
    return trim(
        strip_tags(
            (string) $string
        )
    );
}


/* ============================================================
   CSRF PROTECTION
   ============================================================ */

/**
 * Generate / return CSRF token.
 */
function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


/**
 * Generate hidden CSRF field.
 */
function csrfField(): string
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        e(csrfToken()) .
        '">';
}


/**
 * Verify CSRF token.
 */
function csrfVerify($token): bool
{
    if (
        empty($_SESSION['csrf_token']) ||
        empty($token)
    ) {
        return false;
    }

    return hash_equals(
        (string) $_SESSION['csrf_token'],
        (string) $token
    );
}


/* ============================================================
   ADMIN AUTHENTICATION
   ============================================================ */

/**
 * Check admin login.
 */
function isAdmin(): bool
{
    return (
        isset($_SESSION['admin_id']) &&
        isset($_SESSION['admin_user'])
    );
}


/**
 * Require admin login.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {

        header(
            'Location: ' .
            rtrim(APP_URL, '/') .
            '/admin/index.php'
        );

        exit;
    }
}


/* ============================================================
   REDIRECT / FLASH
   ============================================================ */

/**
 * Redirect with optional flash message.
 */
function redirect(
    string $url,
    string $msg = '',
    string $type = 'success'
): void {

    if ($msg !== '') {

        $_SESSION['flash_msg'] = $msg;

        $_SESSION['flash_type'] = $type;
    }

    header(
        'Location: ' . $url
    );

    exit;
}


/**
 * Get and clear flash message.
 */
function flashMsg(): string
{
    if (
        isset($_SESSION['flash_msg'])
    ) {

        $msg = e(
            $_SESSION['flash_msg']
        );

        $type = e(
            $_SESSION['flash_type'] ?? 'success'
        );

        unset(
            $_SESSION['flash_msg'],
            $_SESSION['flash_type']
        );

        return
            '<div class="alert alert-' .
            $type .
            '">' .
            $msg .
            '</div>';
    }

    return '';
}


/* ============================================================
   IMAGE UPLOAD
   ============================================================ */

/**
 * Upload and validate image.
 *
 * Returns:
 *
 * filename  = success
 * oldImage  = no new file
 * false     = failure
 */
function uploadImage(
    string $fileInput,
    ?string $oldImage = null
) {

    if (
        !isset($_FILES[$fileInput]) ||
        !isset($_FILES[$fileInput]['error'])
    ) {
        return $oldImage;
    }


    if (
        $_FILES[$fileInput]['error'] ===
        UPLOAD_ERR_NO_FILE
    ) {
        return $oldImage;
    }


    $file = $_FILES[$fileInput];


    /*
     * Upload error.
     */
    if (
        $file['error'] !== UPLOAD_ERR_OK
    ) {

        error_log(
            '[SK Travel Planner] Image upload error: ' .
            $file['error']
        );

        return false;
    }


    /*
     * File size.
     */
    if (
        $file['size'] <= 0 ||
        $file['size'] > MAX_FILE_SIZE
    ) {
        return false;
    }


    /*
     * Temporary file.
     */
    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        return false;
    }


    /*
     * Verify actual image.
     */
    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {
        return false;
    }


    /*
     * MIME validation.
     */
    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    $mime =
        $imageInfo['mime'] ?? '';


    if (
        !in_array(
            $mime,
            $allowedMimes,
            true
        )
    ) {
        return false;
    }


    /*
     * Extension validation.
     */
    $originalExt =
        strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );


    if (
        !in_array(
            $originalExt,
            ALLOWED_EXT,
            true
        )
    ) {
        return false;
    }


    /*
     * Normalize extension.
     */
    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];


    $ext =
        $extensionMap[$mime] ?? 'jpg';


    /*
     * Random filename.
     */
    try {

        $randomName =
            bin2hex(
                random_bytes(16)
            );

    } catch (Throwable $e) {

        error_log(
            '[SK Travel Planner] Filename generation failed: ' .
            $e->getMessage()
        );

        return false;
    }


    $newName =
        'sk_' .
        $randomName .
        '.' .
        $ext;


    /*
     * Ensure upload directory exists.
     */
    if (!is_dir(UPLOAD_DIR)) {

        if (
            !@mkdir(
                UPLOAD_DIR,
                0755,
                true
            )
        ) {

            error_log(
                '[SK Travel Planner] Failed to create upload directory: ' .
                UPLOAD_DIR
            );

            return false;
        }
    }


    /*
     * Check writable.
     */
    if (
        !is_writable(UPLOAD_DIR)
    ) {

        error_log(
            '[SK Travel Planner] Upload directory is not writable: ' .
            UPLOAD_DIR
        );

        return false;
    }


    /*
     * Destination.
     */
    $destination =
        UPLOAD_DIR .
        $newName;


    /*
     * Move uploaded file.
     */
    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {

        error_log(
            '[SK Travel Planner] Failed to move uploaded file.'
        );

        return false;
    }


    /*
     * Safe permissions.
     */
    @chmod(
        $destination,
        0644
    );


    /*
     * Delete old image safely.
     */
    if (
        $oldImage &&
        !str_contains($oldImage, '/') &&
        !str_contains($oldImage, '\\')
    ) {

        $oldPath =
            UPLOAD_DIR .
            basename($oldImage);


        if (
            is_file($oldPath)
        ) {
            @unlink($oldPath);
        }
    }


    return $newName;
}


/* ============================================================
   IMAGE URL
   ============================================================ */

/**
 * Return uploaded image URL.
 */
function imageUrl(
    ?string $filename
): string {

    if (
        $filename &&
        is_file(
            UPLOAD_DIR .
            basename($filename)
        )
    ) {

        return
            rtrim(
                UPLOAD_URL,
                '/'
            ) .
            '/' .
            rawurlencode(
                basename($filename)
            );
    }


    /*
     * Remote fallback placeholder.
     */
    return
        'https://picsum.photos/seed/' .
        rawurlencode(
            'sktravel-' .
            ($filename ?? 'default')
        ) .
        '/800/500';
}


/* ============================================================
   PRICE FORMAT
   ============================================================ */

/**
 * Format price in INR.
 */
function formatPrice(
    $price
): string {

    return
        '₹' .
        number_format(
            (float) $price,
            0,
            '.',
            ','
        );
}
?>