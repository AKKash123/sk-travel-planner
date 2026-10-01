<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
requireAdmin();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax'])
    || isset($_GET['ajax']);

$action  = clean($_POST['action'] ?? $_GET['action'] ?? '');
$id      = filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$imageId = filter_var($_POST['image_id'] ?? $_GET['image_id'] ?? 0, FILTER_VALIDATE_INT);
$token   = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

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

$backUrl = !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false
    ? 'dashboard.php'
    : 'reviews.php';

try {

    // ============================================
    // ACTION: DELETE SINGLE REVIEW IMAGE
    // ============================================
    if ($action === 'delete_image') {
        if (!$imageId || $imageId < 1) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid photo ID.']);
                exit;
            }
            redirect($backUrl, 'Invalid photo ID.', 'danger');
        }

        $imgStmt = $pdo->prepare("SELECT * FROM review_images WHERE id = ?");
        $imgStmt->execute([$imageId]);
        $imgRow = $imgStmt->fetch();

        if (!$imgRow) {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Photo not found.']);
                exit;
            }
            redirect($backUrl, 'Photo not found.', 'danger');
        }

        // Delete physical file from review_folder
        deleteReviewImageFile($imgRow['image']);

        // Delete record from review_images
        $delImg = $pdo->prepare("DELETE FROM review_images WHERE id = ?");
        $delImg->execute([$imageId]);

        $revId = (int)$imgRow['review_id'];
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM review_images WHERE review_id = ?");
        $countStmt->execute([$revId]);
        $remainingCount = (int)$countStmt->fetchColumn();

        $msg = 'Photo removed successfully.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'image_id' => $imageId,
                'review_id' => $revId,
                'remaining_count' => $remainingCount,
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'success');
    }

    // ============================================
    // ACTIONS REQUIRING REVIEW ID ($id)
    // ============================================
    if (!$id || $id < 1) {
        if ($isAjax) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid review ID.']);
            exit;
        }
        redirect('reviews.php', 'Invalid review ID.', 'danger');
    }

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

    // ============================================
    // ACTION: UPLOAD IMAGES TO REVIEW (Admin)
    // ============================================
    if ($action === 'upload_images') {
        if (empty($_FILES['review_images'])) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'No image files selected for upload.']);
                exit;
            }
            redirect($backUrl, 'No image files selected.', 'warning');
        }

        $uploaded = uploadReviewImages('review_images', 10);
        if (empty($uploaded)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Upload failed. Ensure images are JPG, PNG, GIF, or WebP and under 5MB.']);
                exit;
            }
            redirect($backUrl, 'Upload failed. Check file types and sizes.', 'danger');
        }

        $ins = $pdo->prepare("INSERT INTO review_images (review_id, image, created_at) VALUES (?, ?, NOW())");
        $newRecords = [];
        foreach ($uploaded as $filename) {
            $ins->execute([$id, $filename]);
            $newRecords[] = [
                'id' => (int)$pdo->lastInsertId(),
                'image' => $filename,
                'url' => reviewImageUrl($filename)
            ];
        }

        $msg = count($uploaded) . ' photo(s) added successfully to review #' . $id . '.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'uploaded' => $newRecords,
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'success');
    }

    // ============================================
    // ACTION: ACCEPT / APPROVE
    // ============================================
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

    // ============================================
    // ACTION: DECLINE / REJECT
    // ============================================
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

    // ============================================
    // ACTION: DELETE ENTIRE REVIEW + IMAGES
    // ============================================
    } elseif ($action === 'delete') {
        // Fetch all images for this review and delete files from review_folder
        $imgStmt = $pdo->prepare("SELECT image FROM review_images WHERE review_id = ?");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($images as $imgFile) {
            deleteReviewImageFile($imgFile);
        }

        // Delete from review_images
        $pdo->prepare("DELETE FROM review_images WHERE review_id = ?")->execute([$id]);

        // Delete from user_reviews
        $del = $pdo->prepare("DELETE FROM user_reviews WHERE id = ?");
        $del->execute([$id]);
        $msg = 'Review and its photos have been permanently deleted.';

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
        echo json_encode(['ok' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
        exit;
    }
    redirect($backUrl, 'Database error occurred: ' . $e->getMessage(), 'danger');
}
