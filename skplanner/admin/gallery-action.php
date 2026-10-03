<?php
declare(strict_types=1);

// ============================================================
// SK Travel Planner — Admin Gallery Action Handler
// Actions: upload_multiple, add_single, edit, toggle_status,
//          delete, bulk_action
// Place this file in /admin/ (same folder as gallery.php)
// ============================================================

require_once __DIR__ . '/../config.php';
requireAdmin();

// ------------------------------------------------------------
// Fallbacks (only used if config.php does not define them)
// ------------------------------------------------------------
if (!defined('GALLERY_UPLOAD_DIR')) {
    define('GALLERY_UPLOAD_DIR', dirname(__DIR__) . '/uploads/gallery/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024);
}
if (!defined('ALLOWED_EXT')) {
    define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}

if (!function_exists('clean')) {
    function clean($value): string
    {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('deleteGalleryImageFile')) {
    function deleteGalleryImageFile(?string $path): void
    {
        if ($path === null || $path === '' || preg_match('#^https?://#i', $path)) {
            return; // remote URL or empty, nothing to delete
        }
        $base = realpath(GALLERY_UPLOAD_DIR);
        if ($base === false) {
            return;
        }
        $file = realpath($base . DIRECTORY_SEPARATOR . basename($path));
        if ($file !== false && str_starts_with($file, $base) && is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('galleryImageUrl')) {
    function galleryImageUrl(?string $path, string $prefix = ''): string
    {
        if ($path === null || $path === '') {
            return $prefix . 'assets/img/placeholder.jpg';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return $prefix . ltrim($path, '/');
    }
}

// ------------------------------------------------------------
// Request context
// ------------------------------------------------------------
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax'])
    || isset($_GET['ajax']);

$backUrl = 'gallery.php';

/**
 * Send a JSON response (AJAX) or redirect with a flash message (normal request).
 */
function galleryRespond(bool $ok, string $message, int $httpCode = 200, array $extra = []): void
{
    global $isAjax, $backUrl;

    if ($isAjax) {
        http_response_code($ok ? 200 : $httpCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = $ok
            ? array_merge(['ok' => true, 'message' => $message], $extra)
            : array_merge(['ok' => false, 'error' => $message], $extra);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    redirect($backUrl, $message, $ok ? 'success' : 'danger');
    exit;
}

function galleryUploadErrorText(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'exceeds the server upload size limit',
        UPLOAD_ERR_PARTIAL                        => 'was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR                     => 'could not be saved (missing temp folder on server)',
        UPLOAD_ERR_CANT_WRITE                     => 'could not be written to disk',
        UPLOAD_ERR_EXTENSION                      => 'was blocked by a PHP extension',
        default                                   => 'could not be uploaded (code ' . $code . ')',
    };
}

function galleryAllowedExtensions(): array
{
    $raw = ALLOWED_EXT;
    if (!is_array($raw)) {
        $raw = explode(',', (string)$raw);
    }
    return array_values(array_filter(array_map(
        static fn($e) => strtolower(trim((string)$e)),
        $raw
    )));
}

function galleryUploadDir(): string
{
    return rtrim(GALLERY_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
}

/**
 * Validate and store one uploaded image.
 * Returns the DB path (uploads/gallery/xxx.ext) or null and sets $error.
 */
function galleryStoreUpload(array $file, ?string &$error): ?string
{
    $name    = (string)($file['name'] ?? 'file');
    $code    = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $tmpName = (string)($file['tmp_name'] ?? '');
    $size    = (int)($file['size'] ?? 0);

    if ($code !== UPLOAD_ERR_OK) {
        $error = "File '{$name}' " . galleryUploadErrorText($code) . '.';
        return null;
    }
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $error = "File '{$name}' is not a valid upload.";
        return null;
    }
    if ($size <= 0 || $size > (int)MAX_FILE_SIZE) {
        $error = "File '{$name}' is empty or exceeds the " . round(((int)MAX_FILE_SIZE) / 1048576, 1) . ' MB size limit.';
        return null;
    }

    $imgInfo = @getimagesize($tmpName);
    if ($imgInfo === false) {
        $error = "File '{$name}' is not a valid image.";
        return null;
    }

    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = (string)($imgInfo['mime'] ?? '');
    if (!isset($extensionMap[$mime])) {
        $error = "File '{$name}' has an unsupported format (JPG, PNG, GIF, WEBP only).";
        return null;
    }

    $origExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($origExt, galleryAllowedExtensions(), true)) {
        $error = "File '{$name}' has an invalid extension.";
        return null;
    }

    $dir = galleryUploadDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        $error = 'Upload folder could not be created: ' . $dir;
        return null;
    }
    if (!is_writable($dir)) {
        $error = 'Upload folder is not writable: ' . $dir;
        return null;
    }

    try {
        $random = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $random = md5(uniqid((string)mt_rand(), true));
    }

    $newFilename = 'gal_' . $random . '.' . $extensionMap[$mime];
    $target      = $dir . $newFilename;

    if (!move_uploaded_file($tmpName, $target)) {
        $error = "Failed to save file '{$name}' to server storage.";
        return null;
    }
    @chmod($target, 0644);

    return 'uploads/gallery/' . $newFilename;
}

function galleryValidUrl(string $url): bool
{
    return $url !== ''
        && strlen($url) <= 500
        && preg_match('#^https?://#i', $url) === 1
        && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function galleryStatus($raw): int
{
    return ((int)$raw === 0 && $raw !== null && $raw !== '') ? 0 : 1;
}

// ------------------------------------------------------------
// Detect uploads larger than php.ini post_max_size
// (PHP empties $_POST and $_FILES in that case)
// ------------------------------------------------------------
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    galleryRespond(
        false,
        'Upload is too large for the server (post_max_size = ' . ini_get('post_max_size')
        . ', upload_max_filesize = ' . ini_get('upload_max_filesize') . '). Upload fewer or smaller photos.',
        413
    );
}

// ------------------------------------------------------------
// Inputs + CSRF
// ------------------------------------------------------------
$action = clean($_POST['action'] ?? $_GET['action'] ?? '');
$idRaw  = filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$id     = ($idRaw === false) ? 0 : (int)$idRaw;
$token  = (string)($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');

if (!csrfVerify($token)) {
    galleryRespond(false, 'Security token mismatch. Please refresh the page and try again.', 403);
}

// ------------------------------------------------------------
// Database handle
// ------------------------------------------------------------
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (function_exists('getDB')) {
        $pdo = getDB();
    } elseif (function_exists('db')) {
        $pdo = db();
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('Admin Gallery Action Error: $pdo is not available after loading config.php');
    galleryRespond(false, 'Database connection is not available. Check config.php.', 500);
}

try {

    // ============================================================
    // ACTION: UPLOAD MULTIPLE IMAGES
    // ============================================================
    if ($action === 'upload_multiple') {
        $destination = clean($_POST['destination'] ?? '');
        $baseTitle   = clean($_POST['base_title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $status      = galleryStatus($_POST['status'] ?? 1);

        if (empty($_FILES['gallery_images']) || !isset($_FILES['gallery_images']['name'])) {
            galleryRespond(false, 'Please select at least one image to upload.', 422);
        }

        $files      = $_FILES['gallery_images'];
        $isMultiple = is_array($files['name']);
        $totalFiles = $isMultiple ? count($files['name']) : 1;

        $uploadedCount = 0;
        $errors        = [];

        $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM gallery_images")->fetchColumn();

        $insertStmt = $pdo->prepare(
            "INSERT INTO gallery_images (title, destination, description, image, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        for ($i = 0; $i < $totalFiles; $i++) {
            $file = [
                'name'     => $isMultiple ? ($files['name'][$i] ?? '')                    : $files['name'],
                'error'    => $isMultiple ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE)   : $files['error'],
                'tmp_name' => $isMultiple ? ($files['tmp_name'][$i] ?? '')                : $files['tmp_name'],
                'size'     => $isMultiple ? ($files['size'][$i] ?? 0)                     : $files['size'],
            ];

            if ((int)$file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
                continue;
            }

            $err    = null;
            $dbPath = galleryStoreUpload($file, $err);
            if ($dbPath === null) {
                $errors[] = $err ?? ("File '{$file['name']}' could not be processed.");
                continue;
            }

            // Derive title
            if ($baseTitle !== '') {
                $imgTitle = $totalFiles > 1 ? ($baseTitle . ' ' . ($uploadedCount + 1)) : $baseTitle;
            } else {
                $rawName  = pathinfo((string)$file['name'], PATHINFO_FILENAME);
                $imgTitle = trim(ucwords(str_replace(['_', '-'], ' ', $rawName)));
                if ($imgTitle === '') {
                    $imgTitle = $destination !== ''
                        ? ($destination . ' Photo ' . ($uploadedCount + 1))
                        : ('Travel Photo ' . ($uploadedCount + 1));
                }
            }
            $imgTitle = mb_substr($imgTitle, 0, 255);

            try {
                $maxSort++;
                $insertStmt->execute([
                    $imgTitle,
                    $destination !== '' ? $destination : null,
                    $description !== '' ? $description : null,
                    $dbPath,
                    $maxSort,
                    $status,
                ]);
                $uploadedCount++;
            } catch (Throwable $dbEx) {
                // Remove the orphan file if the DB insert failed
                deleteGalleryImageFile($dbPath);
                error_log('Admin Gallery insert error: ' . $dbEx->getMessage());
                $errors[] = "File '{$file['name']}' could not be saved to the database.";
            }
        }

        if ($uploadedCount === 0) {
            $msg = !empty($errors) ? implode(' ', $errors) : 'No photos could be uploaded. Please choose valid image files.';
            galleryRespond(false, $msg, 422);
        }

        $successMsg = "Successfully uploaded {$uploadedCount} photo" . ($uploadedCount > 1 ? 's' : '') . ' to the gallery!';
        if (!empty($errors)) {
            $successMsg .= ' (' . count($errors) . ' file(s) skipped: ' . implode(' ', $errors) . ')';
        }

        galleryRespond(true, $successMsg, 200, ['count' => $uploadedCount, 'skipped' => count($errors)]);
    }

    // ============================================================
    // ACTION: ADD SINGLE PHOTO (FILE OR URL)
    // ============================================================
    if ($action === 'add_single') {
        $title       = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
        $destination = clean($_POST['destination'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $imageUrl    = trim((string)($_POST['image_url'] ?? ''));
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = galleryStatus($_POST['status'] ?? 1);

        if ($title === '') {
            galleryRespond(false, 'Photo title is required.', 422);
        }

        $imagePath = '';
        $hasFile   = !empty($_FILES['image_file'])
            && (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $err       = null;
            $imagePath = (string)galleryStoreUpload($_FILES['image_file'], $err);
            if ($imagePath === '') {
                galleryRespond(false, $err ?? 'Image upload failed.', 422);
            }
        } elseif ($imageUrl !== '') {
            if (!galleryValidUrl($imageUrl)) {
                galleryRespond(false, 'Invalid image URL provided (must start with http:// or https://).', 422);
            }
            $imagePath = $imageUrl;
        }

        if ($imagePath === '') {
            galleryRespond(false, 'Please upload an image file or provide a valid image URL.', 422);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO gallery_images (title, destination, description, image, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $title,
            $destination !== '' ? $destination : null,
            $description !== '' ? $description : null,
            $imagePath,
            $sortOrder,
            $status,
        ]);

        galleryRespond(true, 'Photo successfully added to gallery!');
    }

    // ============================================================
    // ACTION: EDIT PHOTO
    // ============================================================
    if ($action === 'edit') {
        if ($id < 1) {
            galleryRespond(false, 'Invalid photo ID.', 422);
        }

        $stmt = $pdo->prepare("SELECT * FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            galleryRespond(false, 'Gallery photo not found.', 404);
        }

        $title       = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
        $destination = clean($_POST['destination'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $imageUrl    = trim((string)($_POST['image_url'] ?? ''));
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = galleryStatus($_POST['status'] ?? 1);

        if ($title === '') {
            galleryRespond(false, 'Photo title cannot be empty.', 422);
        }

        $imagePath = (string)$row['image'];
        $oldImage  = $imagePath;
        $hasFile   = !empty($_FILES['image_file'])
            && (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $err     = null;
            $newPath = galleryStoreUpload($_FILES['image_file'], $err);
            if ($newPath === null) {
                galleryRespond(false, $err ?? 'Image upload failed.', 422);
            }
            $imagePath = $newPath;
        } elseif ($imageUrl !== '' && $imageUrl !== $oldImage) {
            if (!galleryValidUrl($imageUrl)) {
                galleryRespond(false, 'Invalid image URL provided (must start with http:// or https://).', 422);
            }
            $imagePath = $imageUrl;
        }

        $upStmt = $pdo->prepare(
            "UPDATE gallery_images
             SET title = ?, destination = ?, description = ?, image = ?, sort_order = ?, status = ?
             WHERE id = ?"
        );
        $upStmt->execute([
            $title,
            $destination !== '' ? $destination : null,
            $description !== '' ? $description : null,
            $imagePath,
            $sortOrder,
            $status,
            $id,
        ]);

        // Delete the old file only after the DB update succeeded
        if ($imagePath !== $oldImage) {
            deleteGalleryImageFile($oldImage);
        }

        galleryRespond(true, 'Photo details updated successfully.', 200, [
            'id'          => $id,
            'title'       => $title,
            'destination' => $destination,
            'description' => $description,
            'image'       => galleryImageUrl($imagePath, '../'),
            'sort_order'  => $sortOrder,
            'status'      => $status,
        ]);
    }

    // ============================================================
    // ACTION: TOGGLE STATUS
    // ============================================================
    if ($action === 'toggle_status') {
        if ($id < 1) {
            galleryRespond(false, 'Invalid photo ID.', 422);
        }

        $stmt = $pdo->prepare("SELECT id, status FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            galleryRespond(false, 'Photo not found.', 404);
        }

        $newStatus = ((int)$row['status'] === 1) ? 0 : 1;
        $upStmt = $pdo->prepare("UPDATE gallery_images SET status = ? WHERE id = ?");
        $upStmt->execute([$newStatus, $id]);

        $msg = $newStatus === 1
            ? 'Photo is now Active (visible on website).'
            : 'Photo is now Inactive (hidden from website).';

        galleryRespond(true, $msg, 200, [
            'id'           => $id,
            'new_status'   => $newStatus,
            'status_label' => $newStatus === 1 ? 'Active' : 'Inactive',
        ]);
    }

    // ============================================================
    // ACTION: DELETE SINGLE PHOTO
    // ============================================================
    if ($action === 'delete') {
        if ($id < 1) {
            galleryRespond(false, 'Invalid photo ID.', 422);
        }

        $stmt = $pdo->prepare("SELECT id, image FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            galleryRespond(false, 'Photo not found.', 404);
        }

        $delStmt = $pdo->prepare("DELETE FROM gallery_images WHERE id = ?");
        $delStmt->execute([$id]);

        deleteGalleryImageFile((string)$row['image']);

        galleryRespond(true, 'Photo successfully deleted from gallery.', 200, ['id' => $id]);
    }

    // ============================================================
    // ACTION: BULK ACTIONS
    // ============================================================
    if ($action === 'bulk_action') {
        $bulkType = clean($_POST['bulk_type'] ?? '');
        $rawIds   = $_POST['ids'] ?? [];

        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }

        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $val) {
                $cleanId = filter_var($val, FILTER_VALIDATE_INT);
                if ($cleanId !== false && $cleanId > 0) {
                    $ids[] = (int)$cleanId;
                }
            }
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            galleryRespond(false, 'No photos selected for bulk action.', 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkType === 'activate') {
            $stmt = $pdo->prepare("UPDATE gallery_images SET status = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $msg = count($ids) . ' photo(s) set to Active.';

        } elseif ($bulkType === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE gallery_images SET status = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $msg = count($ids) . ' photo(s) set to Inactive.';

        } elseif ($bulkType === 'delete') {
            $fStmt = $pdo->prepare("SELECT image FROM gallery_images WHERE id IN ($placeholders)");
            $fStmt->execute($ids);
            $imagesToRemove = $fStmt->fetchAll(PDO::FETCH_COLUMN);

            $dStmt = $pdo->prepare("DELETE FROM gallery_images WHERE id IN ($placeholders)");
            $dStmt->execute($ids);

            foreach ($imagesToRemove as $imgPath) {
                deleteGalleryImageFile((string)$imgPath);
            }
            $msg = count($ids) . ' photo(s) deleted permanently.';

        } else {
            galleryRespond(false, 'Invalid bulk action requested.', 422);
        }

        galleryRespond(true, $msg, 200, ['affected' => count($ids), 'action' => $bulkType]);
    }

    // ------------------------------------------------------------
    // Unknown action
    // ------------------------------------------------------------
    galleryRespond(false, 'Unknown action requested.', 400);

} catch (Throwable $e) {
    error_log(
        'Admin Gallery Action Error: ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine()
    );
    // This page is admin-only, so the real reason is shown to help you debug.
    // Set APP_DEBUG to false in config.php to hide details.
    $showDetails = !defined('APP_DEBUG') || APP_DEBUG;
    $publicMsg = $showDetails
        ? 'Server error: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'An unexpected server error occurred. Please try again.';
    galleryRespond(false, $publicMsg, 500);
}