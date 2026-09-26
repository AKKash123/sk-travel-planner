<?php
// ============================================================
// SK Travel Planner - Configuration
// Production-safe configuration
// ============================================================

declare(strict_types=1);


// ============================================================
// ERROR REPORTING
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');


// ============================================================
// DATABASE CONFIGURATION
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sk_travel_planner');
define('DB_USER', 'root');
define('DB_PASS', '');


// ============================================================
// APP CONFIGURATION
// ============================================================
//
// LOCAL:
// http://localhost/skplanner
//
// PRODUCTION example:
// https://yourdomain.com
// ============================================================

define('APP_NAME', 'SK Travel Planner');

define(
    'APP_URL',
    'http://localhost/skplanner'
);


// ============================================================
// UPLOAD CONFIGURATION
// ============================================================

define(
    'UPLOAD_DIR',
    __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
);

define(
    'UPLOAD_URL',
    rtrim(APP_URL, '/') . '/uploads/'
);

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


// ============================================================
// CREATE UPLOAD DIRECTORY IF MISSING
// ============================================================
//
// This prevents:
// "uploads/ directory referenced by config.php
// doesn't exist on disk"
// ============================================================

if (!is_dir(UPLOAD_DIR)) {

    if (!@mkdir(UPLOAD_DIR, 0755, true)) {

        error_log(
            'SK Travel Planner - Unable to create upload directory: ' .
            UPLOAD_DIR
        );
    }
}


// ============================================================
// SESSION CONFIGURATION
// ============================================================

if (session_status() === PHP_SESSION_NONE) {

    ini_set(
        'session.cookie_httponly',
        '1'
    );

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    // --------------------------------------------------------
    // HTTPS Detection
    // --------------------------------------------------------

    $isHttps =
        (
            !empty($_SERVER['HTTPS']) &&
            strtolower((string) $_SERVER['HTTPS']) !== 'off'
        )
        ||
        (
            isset($_SERVER['SERVER_PORT']) &&
            (int) $_SERVER['SERVER_PORT'] === 443
        );

    ini_set(
        'session.cookie_secure',
        $isHttps ? '1' : '0'
    );

    session_start();
}


// ============================================================
// GLOBAL ERROR HANDLER
// ============================================================
//
// IMPORTANT:
// error-handler.php must NOT require config.php itself.
// ============================================================

$errorHandlerFile = __DIR__ . '/error-handler.php';

if (is_file($errorHandlerFile)) {
    require_once $errorHandlerFile;
}


// ============================================================
// SECURITY HEADERS
// ============================================================

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
}


// ============================================================
// PDO DATABASE CONNECTION
// ============================================================

try {

    $pdo = new PDO(
        'mysql:host=' . DB_HOST .
        ';dbname=' . DB_NAME .
        ';charset=utf8mb4',

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

} catch (PDOException $e) {

    // --------------------------------------------------------
    // Log actual database error privately
    // --------------------------------------------------------

    error_log(
        'SK Travel Planner - Database connection failed: ' .
        $e->getMessage()
    );


    // --------------------------------------------------------
    // HTTP 500
    // --------------------------------------------------------

    if (!headers_sent()) {
        http_response_code(500);
    }


    // --------------------------------------------------------
    // Load branded 500 page
    // --------------------------------------------------------

    $error500File = __DIR__ . '/error-500.php';

    if (is_file($error500File)) {

        require $error500File;

    } else {

        // ----------------------------------------------------
        // Emergency fallback
        // ----------------------------------------------------

        if (!headers_sent()) {
            header(
                'Content-Type: text/html; charset=UTF-8'
            );
        }

        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Server Error — SK Travel Planner</title>
</head>

<body style="
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    font-family:Arial,sans-serif;
    background:#F7F3ED;
    color:#1B2838;
">

<div>

    <div style="
        font-size:96px;
        font-weight:900;
        color:#E8912D;
    ">500</div>

    <h1>Server Error</h1>

    <p>
        Something went wrong.
        Please try again later.
    </p>

</div>

</body>
</html>';
    }

    exit;
}


// ============================================================
// SECURITY HELPER FUNCTIONS
// ============================================================

/**
 * Escape output for HTML.
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
 * Clean input string.
 */
function clean($string): string
{
    return trim(
        strip_tags(
            (string) $string
        )
    );
}


// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Generate CSRF token.
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
        '<input type="hidden" ' .
        'name="csrf_token" value="' .
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
        $_SESSION['csrf_token'],
        (string) $token
    );
}


// ============================================================
// ADMIN AUTHENTICATION
// ============================================================

/**
 * Check if admin is logged in.
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


// ============================================================
// REDIRECT / FLASH MESSAGE
// ============================================================

/**
 * Redirect with optional flash message.
 */
function redirect(
    string $url,
    string $msg = '',
    string $type = 'success'
): void {

    if ($msg !== '') {

        $_SESSION['flash_msg'] =
            $msg;

        $_SESSION['flash_type'] =
            $type;
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
            $_SESSION['flash_type']
            ?? 'success'
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


// ============================================================
// IMAGE UPLOAD
// ============================================================

/**
 * Upload and validate an image.
 *
 * Returns:
 * - new filename on success
 * - old filename if no new file uploaded
 * - false on failure
 */
function uploadImage(
    string $fileInput,
    ?string $oldImage = null
) {

    // --------------------------------------------------------
    // No file selected
    // --------------------------------------------------------

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


    // --------------------------------------------------------
    // Upload error
    // --------------------------------------------------------

    if (
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        error_log(
            'SK Travel Planner - Image upload error: ' .
            $file['error']
        );

        return false;
    }


    // --------------------------------------------------------
    // File size
    // --------------------------------------------------------

    if (
        $file['size'] <= 0 ||
        $file['size'] > MAX_FILE_SIZE
    ) {
        return false;
    }


    // --------------------------------------------------------
    // Temporary file
    // --------------------------------------------------------

    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        return false;
    }


    // --------------------------------------------------------
    // Image validation
    // --------------------------------------------------------

    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {
        return false;
    }


    // --------------------------------------------------------
    // MIME validation
    // --------------------------------------------------------

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


    // --------------------------------------------------------
    // Extension validation
    // --------------------------------------------------------

    $originalExt = strtolower(
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


    // --------------------------------------------------------
    // Normalize extension based on actual MIME
    // --------------------------------------------------------

    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    $ext =
        $extensionMap[$mime]
        ?? 'jpg';


    // --------------------------------------------------------
    // Generate random filename
    // --------------------------------------------------------

    try {

        $randomName =
            bin2hex(
                random_bytes(16)
            );

    } catch (Throwable $e) {

        error_log(
            'SK Travel Planner - Failed to generate upload filename: ' .
            $e->getMessage()
        );

        return false;
    }


    $newName =
        'sk_' .
        $randomName .
        '.' .
        $ext;


    // --------------------------------------------------------
    // Create upload directory
    // --------------------------------------------------------

    if (!is_dir(UPLOAD_DIR)) {

        if (
            !@mkdir(
                UPLOAD_DIR,
                0755,
                true
            )
        ) {

            error_log(
                'SK Travel Planner - Failed to create upload directory: ' .
                UPLOAD_DIR
            );

            return false;
        }
    }


    // --------------------------------------------------------
    // Directory write check
    // --------------------------------------------------------

    if (
        !is_writable(UPLOAD_DIR)
    ) {

        error_log(
            'SK Travel Planner - Upload directory is not writable: ' .
            UPLOAD_DIR
        );

        return false;
    }


    // --------------------------------------------------------
    // Move uploaded file
    // --------------------------------------------------------

    $destination =
        UPLOAD_DIR .
        $newName;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {

        error_log(
            'SK Travel Planner - Failed to move uploaded file.'
        );

        return false;
    }


    // --------------------------------------------------------
    // Safe file permissions
    // --------------------------------------------------------

    @chmod(
        $destination,
        0644
    );


    // --------------------------------------------------------
    // Delete old image
    // --------------------------------------------------------

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


// ============================================================
// IMAGE URL
// ============================================================

/**
 * Get image URL or default placeholder.
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
            rtrim(UPLOAD_URL, '/') .
            '/' .
            rawurlencode(
                basename($filename)
            );
    }


    // --------------------------------------------------------
    // Fallback placeholder
    // --------------------------------------------------------

    return
        'https://picsum.photos/seed/' .
        rawurlencode(
            'sktravel-' .
            ($filename ?? 'default')
        ) .
        '/800/500';
}


// ============================================================
// PRICE FORMAT
// ============================================================

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