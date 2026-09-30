<?php
declare(strict_types=1);

/**
 * submit-review.php — Handles live public review submissions
 * Saves submitted review with status 'pending' for admin review.
 */

require_once __DIR__ . '/config.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax']);

function respond(array $data, int $statusCode = 200, bool $isAjax = true): void
{
    if ($isAjax) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    }

    if (!empty($data['ok'])) {
        redirect('index.php#reviews', $data['message'] ?? 'Review submitted successfully!', 'success');
    } else {
        redirect('index.php#reviews', $data['error'] ?? 'Failed to submit review.', 'danger');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(['ok' => false, 'error' => 'Method not allowed.'], 405, $isAjax);
}

// Honeypot anti-spam check
if (!empty($_POST['website_url'])) {
    respond(['ok' => false, 'error' => 'Spam detected.'], 400, $isAjax);
}

// Validate inputs
$userName     = clean($_POST['user_name'] ?? '');
$userEmail    = filter_var(trim($_POST['user_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['user_email']) : null;
$userLocation = clean($_POST['user_location'] ?? '');
$rating       = filter_var($_POST['rating'] ?? 5, FILTER_VALIDATE_INT);
$reviewTitle  = clean($_POST['review_title'] ?? '');
$reviewText   = clean($_POST['review_text'] ?? '');

// Errors array
$errors = [];

if (mb_strlen($userName) < 2 || mb_strlen($userName) > 100) {
    $errors[] = 'Please provide a valid name (2 to 100 characters).';
}

if (!empty($_POST['user_email']) && !$userEmail) {
    $errors[] = 'Please provide a valid email address.';
}

if (!$rating || $rating < 1 || $rating > 5) {
    $errors[] = 'Please select a star rating between 1 and 5.';
}

if (mb_strlen($reviewText) < 10) {
    $errors[] = 'Please share at least 10 characters in your review.';
} elseif (mb_strlen($reviewText) > 2000) {
    $errors[] = 'Review text cannot exceed 2000 characters.';
}

if (!empty($errors)) {
    respond(['ok' => false, 'error' => implode(' ', $errors)], 422, $isAjax);
}

try {
    // Basic rate limit / duplicate check: same user name and review within 1 hour
    $checkStmt = $pdo->prepare('SELECT id FROM user_reviews WHERE user_name = ? AND review_text = ? AND created_at >= (NOW() - INTERVAL 1 HOUR) LIMIT 1');
    $checkStmt->execute([$userName, $reviewText]);
    if ($checkStmt->fetchColumn()) {
        respond([
            'ok'      => false,
            'error'   => 'You have already submitted this review recently. Thank you for your patience!',
        ], 429, $isAjax);
    }

    $stmt = $pdo->prepare('INSERT INTO user_reviews 
        (user_name, user_email, user_location, rating, review_title, review_text, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())');

    $stmt->execute([
        $userName,
        $userEmail,
        $userLocation ?: null,
        $rating,
        $reviewTitle ?: null,
        $reviewText,
    ]);

    respond([
        'ok'      => true,
        'message' => 'Thank you, ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '! Your review has been submitted successfully and will appear once approved by our team.',
    ], 200, $isAjax);

} catch (Throwable $e) {
    error_log('submit-review.php: ' . $e->getMessage());
    respond(['ok' => false, 'error' => 'An error occurred while saving your review. Please try again.'], 500, $isAjax);
}
