<?php
declare(strict_types=1);

/**
 * Shared helpers for review photos.
 * Place this file in the SITE ROOT (next to config.php, index.php and the uploads/ folder).
 *
 * Photos live in   <site root>/uploads/reviews/
 * DB column        user_reviews.review_images  (JSON array of "uploads/reviews/xxx.jpg"; use TEXT type)
 */

const REVIEW_UPLOAD_REL  = 'uploads/reviews';
const REVIEW_MAX_FILES   = 5;
const REVIEW_MAX_BYTES   = 5 * 1024 * 1024; // 5 MB per photo
const REVIEW_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

/** Absolute path of the upload folder (created if missing). */
function reviewUploadDir(): string
{
    $dir = __DIR__ . '/' . REVIEW_UPLOAD_REL;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Accepts whatever is stored in review_images and returns clean relative
 * paths such as "uploads/reviews/abc.jpg". Understands:
 *  - JSON array   ["uploads/reviews/a.jpg", ...]
 *  - double-encoded JSON, comma/semicolon/pipe separated text
 *  - bare filenames ("a.jpg"), "/uploads/reviews/a.jpg", "../uploads/reviews/a.jpg",
 *    full URLs "https://site.com/uploads/reviews/a.jpg", Windows slashes
 *  - arrays of objects with a path / url / file key
 */
function reviewImagePaths($raw): array
{
    if ($raw === null || $raw === '' || $raw === false) {
        return [];
    }

    $list = $raw;
    if (is_string($raw)) {
        $raw = trim($raw);
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {                 // double-encoded JSON
            $again = json_decode($decoded, true);
            $decoded = is_array($again) ? $again : [$decoded];
        }
        $list = is_array($decoded) ? $decoded : preg_split('/\s*[,;|]\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($list)) {
        return [];
    }

    $marker = REVIEW_UPLOAD_REL . '/';
    $out = [];
    foreach ($list as $item) {
        if (is_array($item)) {
            $item = $item['path'] ?? $item['url'] ?? $item['file'] ?? '';
        }
        if (!is_string($item) || trim($item) === '') {
            continue;
        }
        $p = str_replace('\\', '/', trim($item));
        if (preg_match('#^https?://#i', $p)) {      // full URL: drop ?query/#hash and decode %20 etc.
            $p = rawurldecode(preg_replace('/[?#].*$/', '', $p));
        }

        $pos = stripos($p, $marker);
        $p = ($pos !== false)
            ? substr($p, $pos)                      // keep from "uploads/reviews/" onward
            : $marker . basename($p);               // bare filename or other folder -> assume our folder

        // normalise case of the folder part, block traversal
        $p = $marker . substr($p, strlen($marker));
        if (strpos($p, '..') !== false) {
            continue;
        }
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!in_array($ext, REVIEW_ALLOWED_EXT, true)) {
            continue;
        }
        $out[$p] = $p;                              // de-duplicate
    }
    return array_values($out);
}

/**
 * Browser-ready URLs. $prefix is the path from the current page to the site root:
 *   admin pages  -> '../'
 *   root pages   -> ''
 * Each path segment is URL-encoded, so spaces, #, & etc. in file names still work.
 */
function reviewImageUrls($raw, string $prefix = ''): array
{
    $urls = [];
    foreach (reviewImagePaths($raw) as $p) {
        $urls[] = $prefix . implode('/', array_map('rawurlencode', explode('/', $p)));
    }
    return $urls;
}

/** Absolute paths of files that exist AND sit inside uploads/reviews/ (safe for unlink). */
function reviewImageAbsPaths($raw): array
{
    $base = realpath(__DIR__ . '/' . REVIEW_UPLOAD_REL);
    if ($base === false) {
        return [];
    }
    $out = [];
    foreach (reviewImagePaths($raw) as $p) {
        $full = realpath(__DIR__ . '/' . $p);
        if ($full !== false && is_file($full) && strpos($full, $base . DIRECTORY_SEPARATOR) === 0) {
            $out[] = $full;
        }
    }
    return $out;
}

/**
 * Validate + store uploaded photos from <input type="file" name="review_images[]" multiple>.
 *
 * Returns ['paths' => ['uploads/reviews/....jpg', ...], 'errors' => ['message', ...]]
 * Save json_encode($result['paths']) in user_reviews.review_images.
 */
function saveReviewImages(string $field = 'review_images'): array
{
    $result = ['paths' => [], 'errors' => []];

    if (empty($_FILES[$field])) {
        return $result;
    }

    $f      = $_FILES[$field];
    $names  = (array)$f['name'];
    $tmps   = (array)$f['tmp_name'];
    $errs   = (array)$f['error'];
    $sizes  = (array)$f['size'];

    $dir = reviewUploadDir();
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

    foreach ($names as $i => $origName) {
        $err = (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $label = htmlspecialchars((string)$origName, ENT_QUOTES, 'UTF-8');

        if (count($result['paths']) >= REVIEW_MAX_FILES) {
            $result['errors'][] = 'You can upload at most ' . REVIEW_MAX_FILES . ' photos.';
            break;
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $result['errors'][] = "\"$label\" is too large.";
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $result['errors'][] = "\"$label\" could not be uploaded (error code $err).";
            continue;
        }
        if ((int)$sizes[$i] > REVIEW_MAX_BYTES) {
            $result['errors'][] = "\"$label\" is larger than " . (REVIEW_MAX_BYTES / 1048576) . ' MB.';
            continue;
        }
        $tmp = (string)$tmps[$i];
        if (!is_uploaded_file($tmp)) {
            continue;
        }

        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : (string)(@getimagesize($tmp)['mime'] ?? '');
        if (!isset($mimeToExt[$mime]) || @getimagesize($tmp) === false) {
            $result['errors'][] = "\"$label\" is not a valid JPG, PNG, GIF or WebP image.";
            continue;
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            error_log('review upload: folder not writable: ' . $dir);
            $result['errors'][] = 'Photos could not be saved on the server (upload folder is not writable).';
            break;
        }

        $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $mimeToExt[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $fileName)) {
            $result['errors'][] = "\"$label\" could not be saved.";
            continue;
        }
        @chmod($dir . '/' . $fileName, 0644);
        $result['paths'][] = REVIEW_UPLOAD_REL . '/' . $fileName;
    }

    if ($finfo) {
        finfo_close($finfo);
    }
    return $result;
}