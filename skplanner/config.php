<?php

// ============================================
// SK Travel Planner - Configuration
// ============================================

// ============================================
// ERROR REPORTING
// ============================================

// Development:
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Production:
// Do not display PHP errors to visitors.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');


// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sk_travel_planner');
define('DB_USER', 'root');
define('DB_PASS', '');


// ============================================
// APP CONFIGURATION
// ============================================

define('APP_NAME', 'SK Travel Planner');

// LOCAL DEVELOPMENT
define(
    'APP_URL',
    'http://localhost/sk-travel-planner'
);

// LIVE SERVER EXAMPLE:
// define('APP_URL', 'https://yourdomain.com/sk-travel-planner');


// ============================================
// UPLOAD CONFIGURATION
// ============================================

define(
    'UPLOAD_DIR',
    __DIR__ . '/uploads/'
);

define(
    'UPLOAD_URL',
    APP_URL . '/uploads/'
);

define(
    'MAX_FILE_SIZE',
    5 * 1024 * 1024
); // 5 MB

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


// ============================================
// SESSION CONFIGURATION
// ============================================

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');

    // HTTPS detection
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );

    ini_set(
        'session.cookie_secure',
        $isHttps ? '1' : '0'
    );

    session_start();
}


// ============================================
// SECURITY HEADERS
// ============================================

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


// ============================================
// PDO DATABASE CONNECTION
// ============================================

try {

    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
        ";dbname=" . DB_NAME .
        ";charset=utf8mb4",

        DB_USER,
        DB_PASS,

        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false
        ]
    );

} catch (PDOException $e) {

    // Do not expose database credentials/errors
    // to visitors on production.

    error_log(
        'Database connection failed: ' .
        $e->getMessage()
    );

    die(
        'Database connection failed. Please try again later.'
    );
}


// ============================================
// SECURITY HELPER FUNCTIONS
// ============================================

/**
 * Escape output for HTML.
 *
 * Primary XSS protection when displaying
 * database/user supplied content.
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


// ============================================
// CSRF PROTECTION
// ============================================

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


// ============================================
// ADMIN AUTHENTICATION
// ============================================

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
            APP_URL .
            '/admin/index.php'
        );

        exit;
    }
}


// ============================================
// REDIRECT / FLASH MESSAGE
// ============================================

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
            "<div class=\"alert alert-{$type}\">" .
            $msg .
            "</div>";
    }

    return '';
}


// ============================================
// IMAGE UPLOAD
// ============================================

/**
 * Upload and validate an image.
 *
 * Uses getimagesize() instead of finfo_open().
 *
 * Returns:
 *     new filename on success
 *     old filename if no new file uploaded
 *     false on failure
 */
function uploadImage(
    string $fileInput,
    ?string $oldImage = null
) {

    // No file selected
    if (
        !isset($_FILES[$fileInput]) ||
        $_FILES[$fileInput]['error'] ===
        UPLOAD_ERR_NO_FILE
    ) {

        return $oldImage;
    }


    $file = $_FILES[$fileInput];


    // ========================================
    // UPLOAD ERROR CHECK
    // ========================================

    if (
        $file['error'] !== UPLOAD_ERR_OK
    ) {

        return false;
    }


    // ========================================
    // FILE SIZE CHECK
    // ========================================

    if (
        $file['size'] <= 0 ||
        $file['size'] > MAX_FILE_SIZE
    ) {

        return false;
    }


    // ========================================
    // TEMP FILE CHECK
    // ========================================

    if (
        !is_uploaded_file(
            $file['tmp_name']
        )
    ) {

        return false;
    }


    // ========================================
    // IMAGE VALIDATION
    // ========================================

    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {

        return false;
    }


    // ========================================
    // MIME TYPE CHECK
    // ========================================

    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    $mime = $imageInfo['mime'] ?? '';

    if (
        !in_array(
            $mime,
            $allowedMimes,
            true
        )
    ) {

        return false;
    }


    // ========================================
    // EXTENSION CHECK
    // ========================================

    $ext = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    if (
        !in_array(
            $ext,
            ALLOWED_EXT,
            true
        )
    ) {

        return false;
    }


    // ========================================
    // NORMALIZE EXTENSION
    // ========================================

    // Do not trust the user's original
    // extension.

    $extensionMap = [

        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'

    ];

    $ext =
        $extensionMap[$mime]
        ?? 'jpg';


    // ========================================
    // GENERATE RANDOM FILENAME
    // ========================================

    try {

        $randomName =
            bin2hex(
                random_bytes(16)
            );

    } catch (Exception $e) {

        return false;
    }


    $newName =
        'sk_' .
        $randomName .
        '.' .
        $ext;


    // ========================================
    // CREATE UPLOAD DIRECTORY
    // ========================================

    if (
        !is_dir(UPLOAD_DIR)
    ) {

        if (
            !mkdir(
                UPLOAD_DIR,
                0755,
                true
            )
        ) {

            return false;
        }
    }


    // ========================================
    // DIRECTORY WRITE CHECK
    // ========================================

    if (
        !is_writable(UPLOAD_DIR)
    ) {

        return false;
    }


    // ========================================
    // MOVE UPLOADED FILE
    // ========================================

    $destination =
        UPLOAD_DIR .
        $newName;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {

        return false;
    }


    // ========================================
    // SET SAFE FILE PERMISSIONS
    // ========================================

    @chmod(
        $destination,
        0644
    );


    // ========================================
    // DELETE OLD IMAGE
    // ========================================

    if (
        $oldImage &&
        !str_contains(
            $oldImage,
            '/'
        ) &&
        !str_contains(
            $oldImage,
            '\\'
        )
    ) {

        $oldPath =
            UPLOAD_DIR .
            basename($oldImage);

        if (
            is_file($oldPath)
        ) {

            @unlink(
                $oldPath
            );
        }
    }


    return $newName;
}


// ============================================
// IMAGE URL
// ============================================

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
            UPLOAD_URL .
            rawurlencode(
                basename($filename)
            );
    }


    // Local fallback placeholder
    return
        'https://picsum.photos/seed/' .
        rawurlencode(
            'sktravel-' . ($filename ?? 'default')
        ) .
        '/800/500';
}


// ============================================
// PRICE FORMAT
// ============================================

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