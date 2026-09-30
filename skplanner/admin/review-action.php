<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
requireAdmin();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax'])
    || isset($_GET['ajax']);

$action = clean($_POST['action'] ?? $_GET['action'] ?? '');
$id     = filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$token  = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

// Check CSRF for POST or GET actions
if (!csrfVerify($token)) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }
    redirect('reviews.php', 'Security token mismatch. Please try again.', 'danger');
}

if (!$id || $id < 1) {
    if ($isAjax) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid review ID.']);
        exit;
    }
    redirect('reviews.php', 'Invalid review ID.', 'danger');
}

$backUrl = !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false
    ? 'dashboard.php'
    : 'reviews.php';

try {
    // Check if review exists
    $stmt = $pdo->prepare("SELECT * FROM user_reviews WHERE id = ?");
    $stmt->execute([$id]);
    $review = $stmt->fetch();

    if (!$review) {
        if ($isAjax) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Review not found.']);
            exit;
        }
        redirect($backUrl, 'Review not found.', 'danger');
    }

    if ($action === 'accept' || $action === 'approve') {
        $up = $pdo->prepare("UPDATE user_reviews SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $up->execute([$id]);
        $msg = 'Review from "' . htmlspecialchars($review['user_name'], ENT_QUOTES, 'UTF-8') . '" has been accepted and is now live on the website.';
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'status' => 'approved',
                'status_label' => 'Accepted',
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'success');

    } elseif ($action === 'decline' || $action === 'reject') {
        $up = $pdo->prepare("UPDATE user_reviews SET status = 'declined', updated_at = NOW() WHERE id = ?");
        $up->execute([$id]);
        $msg = 'Review from "' . htmlspecialchars($review['user_name'], ENT_QUOTES, 'UTF-8') . '" has been declined.';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'status' => 'declined',
                'status_label' => 'Declined',
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'warning');

    } elseif ($action === 'delete') {
        $del = $pdo->prepare("DELETE FROM user_reviews WHERE id = ?");
        $del->execute([$id]);
        $msg = 'Review has been permanently deleted.';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'action' => 'delete',
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'success');

    } else {
        if ($isAjax) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Unknown action requested.']);
            exit;
        }
        redirect($backUrl, 'Unknown action.', 'danger');
    }

} catch (Throwable $e) {
    error_log('admin/review-action.php: ' . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Database error occurred.']);
        exit;
    }
    redirect($backUrl, 'Database error occurred: ' . $e->getMessage(), 'danger');
}
