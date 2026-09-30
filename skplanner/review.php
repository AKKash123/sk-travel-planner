<?php
declare(strict_types=1);

/**
 * review.php — saves a 1–5 star rating for an itinerary.
 * POST: itinerary_id, rating, token   →   JSON {ok, avg, count, mine}
 * One rating per browser token per itinerary; tapping again changes it.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$id     = filter_var($_POST['itinerary_id'] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$token  = (string)($_POST['token'] ?? '');

if (!$id || $id < 1 || !$rating || $rating < 1 || $rating > 5 || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token)) {
    out(['ok' => false, 'error' => 'Invalid request'], 422);
}

try {
    // Only active itineraries can be rated
    $chk = $pdo->prepare('SELECT 1 FROM itineraries WHERE id = ? AND status = 1');
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        out(['ok' => false, 'error' => 'Itinerary not found'], 404);
    }

    $voter = hash('sha256', $token);

    $find = $pdo->prepare('SELECT id FROM itinerary_reviews WHERE itinerary_id = ? AND voter_hash = ?');
    $find->execute([$id, $voter]);
    $existing = $find->fetchColumn();

    if ($existing) {
        $pdo->prepare('UPDATE itinerary_reviews SET rating = ? WHERE id = ?')->execute([$rating, $existing]);
    } else {
        $pdo->prepare('INSERT INTO itinerary_reviews (itinerary_id, voter_hash, rating) VALUES (?, ?, ?)')
            ->execute([$id, $voter, $rating]);
    }

    $agg = $pdo->prepare('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total FROM itinerary_reviews WHERE itinerary_id = ?');
    $agg->execute([$id]);
    $row = $agg->fetch(PDO::FETCH_ASSOC);

    out([
        'ok'    => true,
        'avg'   => (float)$row['avg_rating'],
        'count' => (int)$row['total'],
        'mine'  => $rating,
    ]);
} catch (Throwable $e) {
    error_log('review.php: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Could not save your rating'], 500);
}