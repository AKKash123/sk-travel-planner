<?php
declare(strict_types=1);

// ============================================================
// SK Travel Planner — Admin Gallery Action Handler
// Handles multiple image uploads, edits, status toggles,
// deletes, and bulk operations securely.
// ============================================================

require_once __DIR__ . '/../config.php';
requireAdmin();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax'])
    || isset($_GET['ajax']);

$action  = clean($_POST['action'] ?? $_GET['action'] ?? '');
$id      = filter_var($_POST['id'] ?? $_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$token   = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

// Check CSRF
if (!csrfVerify($token)) {
    if ($isAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Security token mismatch. Please refresh the page and try again.']);
        exit;
    }
    redirect('gallery.php', 'Security token mismatch. Please try again.', 'danger');
}

$backUrl = 'gallery.php';

try {

    // ============================================================
    // ACTION: UPLOAD MULTIPLE IMAGES
    // ============================================================
    if ($action === 'upload_multiple') {
        $destination = clean($_POST['destination'] ?? '');
        $baseTitle   = clean($_POST['base_title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;
        $status      = in_array($status, [0, 1], true) ? $status : 1;

        if (empty($_FILES['gallery_images']) || empty($_FILES['gallery_images']['name'])) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Please select at least one image to upload.']);
                exit;
            }
            redirect($backUrl, 'Please select at least one image to upload.', 'danger');
        }

        $files = $_FILES['gallery_images'];
        $isMultiple = is_array($files['name']);
        $totalFiles = $isMultiple ? count($files['name']) : 1;

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $extensionMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        if (!is_dir(GALLERY_UPLOAD_DIR)) {
            @mkdir(GALLERY_UPLOAD_DIR, 0755, true);
        }

        $uploadedCount = 0;
        $errors = [];

        // Determine current highest sort order
        $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM gallery_images")->fetchColumn();

        $insertStmt = $pdo->prepare(
            "INSERT INTO gallery_images (title, destination, description, image, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        for ($i = 0; $i < $totalFiles; $i++) {
            $name    = $isMultiple ? ($files['name'][$i] ?? '') : $files['name'];
            $error   = $isMultiple ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : $files['error'];
            $tmpName = $isMultiple ? ($files['tmp_name'][$i] ?? '') : $files['tmp_name'];
            $size    = $isMultiple ? ($files['size'][$i] ?? 0) : $files['size'];

            if ($error === UPLOAD_ERR_NO_FILE || empty($tmpName)) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "File '{$name}' could not be uploaded (code: {$error}).";
                continue;
            }

            if ($size <= 0 || $size > MAX_FILE_SIZE) {
                $errors[] = "File '{$name}' exceeds the 5 MB size limit.";
                continue;
            }

            if (!is_uploaded_file($tmpName)) {
                continue;
            }

            $imgInfo = @getimagesize($tmpName);
            if ($imgInfo === false) {
                $errors[] = "File '{$name}' is not a valid image.";
                continue;
            }

            $mime = $imgInfo['mime'] ?? '';
            if (!in_array($mime, $allowedMimes, true)) {
                $errors[] = "File '{$name}' has an unsupported format.";
                continue;
            }

            $origExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($origExt, ALLOWED_EXT, true)) {
                $errors[] = "File '{$name}' has an invalid extension.";
                continue;
            }

            $ext = $extensionMap[$mime] ?? 'jpg';
            try {
                $random = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $random = md5(uniqid((string)mt_rand(), true));
            }

            $newFilename = 'gal_' . $random . '.' . $ext;
            $targetPath  = GALLERY_UPLOAD_DIR . $newFilename;

            if (move_uploaded_file($tmpName, $targetPath)) {
                @chmod($targetPath, 0644);

                // Derive title
                if (!empty($baseTitle)) {
                    $imgTitle = $totalFiles > 1 ? ($baseTitle . ' ' . ($uploadedCount + 1)) : $baseTitle;
                } else {
                    $rawName = pathinfo($name, PATHINFO_FILENAME);
                    $imgTitle = ucwords(str_replace(['_', '-'], ' ', $rawName));
                    if (trim($imgTitle) === '') {
                        $imgTitle = !empty($destination) ? ($destination . ' Photo ' . ($uploadedCount + 1)) : ('Travel Photo ' . ($uploadedCount + 1));
                    }
                }

                $maxSort++;
                $dbImagePath = 'uploads/gallery/' . $newFilename;

                $insertStmt->execute([
                    $imgTitle,
                    !empty($destination) ? $destination : null,
                    !empty($description) ? $description : null,
                    $dbImagePath,
                    $maxSort,
                    $status
                ]);

                $uploadedCount++;
            } else {
                $errors[] = "Failed to save file '{$name}' to server storage.";
            }
        }

        if ($uploadedCount === 0) {
            $msg = !empty($errors) ? implode(' ', $errors) : 'No photos could be uploaded.';
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            redirect($backUrl, $msg, 'danger');
        }

        $successMsg = "Successfully uploaded {$uploadedCount} photo" . ($uploadedCount > 1 ? 's' : '') . " to the gallery!";
        if (!empty($errors)) {
            $successMsg .= ' (' . count($errors) . ' file(s) skipped due to errors)';
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'count' => $uploadedCount,
                'message' => $successMsg
            ]);
            exit;
        }

        redirect($backUrl, $successMsg, 'success');
    }

    // ============================================================
    // ACTION: ADD SINGLE PHOTO VIA URL OR SINGLE FILE
    // ============================================================
    if ($action === 'add_single') {
        $title       = clean($_POST['title'] ?? '');
        $destination = clean($_POST['destination'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $imageUrl    = trim($_POST['image_url'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if (empty($title)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Photo title is required.']);
                exit;
            }
            redirect($backUrl, 'Photo title is required.', 'danger');
        }

        $imagePath = '';

        // Check if file uploaded
        if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $imgInfo = @getimagesize($file['tmp_name']);
            if ($imgInfo !== false) {
                $ext = match($imgInfo['mime'] ?? '') {
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => 'jpg'
                };
                try {
                    $random = bin2hex(random_bytes(16));
                } catch (Throwable $e) {
                    $random = md5(uniqid((string)mt_rand(), true));
                }
                $newFilename = 'gal_' . $random . '.' . $ext;
                if (!is_dir(GALLERY_UPLOAD_DIR)) {
                    @mkdir(GALLERY_UPLOAD_DIR, 0755, true);
                }
                $dest = GALLERY_UPLOAD_DIR . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    @chmod($dest, 0644);
                    $imagePath = 'uploads/gallery/' . $newFilename;
                }
            }
        }

        // Fallback to URL if provided
        if (empty($imagePath) && !empty($imageUrl)) {
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imagePath = $imageUrl;
            } else {
                if ($isAjax) {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['ok' => false, 'error' => 'Invalid image URL provided.']);
                    exit;
                }
                redirect($backUrl, 'Invalid image URL provided.', 'danger');
            }
        }

        if (empty($imagePath)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Please upload an image file or provide a valid image URL.']);
                exit;
            }
            redirect($backUrl, 'Please upload an image file or provide a valid image URL.', 'danger');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO gallery_images (title, destination, description, image, sort_order, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $title,
            !empty($destination) ? $destination : null,
            !empty($description) ? $description : null,
            $imagePath,
            $sortOrder,
            $status
        ]);

        $msg = 'Photo successfully added to gallery!';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => $msg]);
            exit;
        }
        redirect($backUrl, $msg, 'success');
    }

    // ============================================================
    // ACTION: EDIT PHOTO
    // ============================================================
    if ($action === 'edit') {
        if (!$id || $id < 1) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid photo ID.']);
                exit;
            }
            redirect($backUrl, 'Invalid photo ID.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT * FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Gallery photo not found.']);
                exit;
            }
            redirect($backUrl, 'Gallery photo not found.', 'danger');
        }

        $title       = clean($_POST['title'] ?? '');
        $destination = clean($_POST['destination'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $imageUrl    = trim($_POST['image_url'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if (empty($title)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Photo title cannot be empty.']);
                exit;
            }
            redirect($backUrl, 'Photo title cannot be empty.', 'danger');
        }

        $imagePath = $row['image']; // Default keep old image

        // Check if replacing with new uploaded file
        if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            $imgInfo = @getimagesize($file['tmp_name']);
            if ($imgInfo !== false) {
                $ext = match($imgInfo['mime'] ?? '') {
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    default => 'jpg'
                };
                try {
                    $random = bin2hex(random_bytes(16));
                } catch (Throwable $e) {
                    $random = md5(uniqid((string)mt_rand(), true));
                }
                $newFilename = 'gal_' . $random . '.' . $ext;
                if (!is_dir(GALLERY_UPLOAD_DIR)) {
                    @mkdir(GALLERY_UPLOAD_DIR, 0755, true);
                }
                $dest = GALLERY_UPLOAD_DIR . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    @chmod($dest, 0644);
                    // Remove old file if it was a local upload
                    deleteGalleryImageFile($row['image']);
                    $imagePath = 'uploads/gallery/' . $newFilename;
                }
            }
        } elseif (!empty($imageUrl) && $imageUrl !== $row['image'] && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            // Replaced with URL
            deleteGalleryImageFile($row['image']);
            $imagePath = $imageUrl;
        }

        $upStmt = $pdo->prepare(
            "UPDATE gallery_images
             SET title = ?, destination = ?, description = ?, image = ?, sort_order = ?, status = ?
             WHERE id = ?"
        );
        $upStmt->execute([
            $title,
            !empty($destination) ? $destination : null,
            !empty($description) ? $description : null,
            $imagePath,
            $sortOrder,
            $status,
            $id
        ]);

        $msg = 'Photo details updated successfully.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'title' => $title,
                'destination' => $destination,
                'description' => $description,
                'image' => galleryImageUrl($imagePath, '../'),
                'sort_order' => $sortOrder,
                'status' => $status,
                'message' => $msg
            ]);
            exit;
        }
        redirect($backUrl, $msg, 'success');
    }

    // ============================================================
    // ACTION: TOGGLE STATUS (ACTIVE / INACTIVE)
    // ============================================================
    if ($action === 'toggle_status') {
        if (!$id || $id < 1) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid photo ID.']);
                exit;
            }
            redirect($backUrl, 'Invalid photo ID.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT id, status, title FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Photo not found.']);
                exit;
            }
            redirect($backUrl, 'Photo not found.', 'danger');
        }

        $newStatus = (int)$row['status'] === 1 ? 0 : 1;
        $upStmt = $pdo->prepare("UPDATE gallery_images SET status = ? WHERE id = ?");
        $upStmt->execute([$newStatus, $id]);

        $msg = $newStatus === 1 ? 'Photo is now Active (visible on website).' : 'Photo is now Inactive (hidden from website).';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'new_status' => $newStatus,
                'status_label' => $newStatus === 1 ? 'Active' : 'Inactive',
                'message' => $msg
            ]);
            exit;
        }

        redirect($backUrl, $msg, 'success');
    }

    // ============================================================
    // ACTION: DELETE SINGLE PHOTO
    // ============================================================
    if ($action === 'delete') {
        if (!$id || $id < 1) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid photo ID.']);
                exit;
            }
            redirect($backUrl, 'Invalid photo ID.', 'danger');
        }

        $stmt = $pdo->prepare("SELECT * FROM gallery_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Photo not found.']);
                exit;
            }
            redirect($backUrl, 'Photo not found.', 'danger');
        }

        // Clean up file if local
        deleteGalleryImageFile($row['image']);

        $delStmt = $pdo->prepare("DELETE FROM gallery_images WHERE id = ?");
        $delStmt->execute([$id]);

        $msg = 'Photo successfully deleted from gallery.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'id' => $id,
                'message' => $msg
            ]);
            exit;
        }

        redirect($backUrl, $msg, 'success');
    }

    // ============================================================
    // ACTION: BULK ACTIONS (ACTIVATE, DEACTIVATE, DELETE)
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
                if ($cleanId && $cleanId > 0) {
                    $ids[] = $cleanId;
                }
            }
        }

        if (empty($ids)) {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'No photos selected for bulk action.']);
                exit;
            }
            redirect($backUrl, 'No photos selected for bulk action.', 'danger');
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
            // Fetch images to delete physical files
            $fStmt = $pdo->prepare("SELECT image FROM gallery_images WHERE id IN ($placeholders)");
            $fStmt->execute($ids);
            while ($imgRow = $fStmt->fetch(PDO::FETCH_ASSOC)) {
                deleteGalleryImageFile($imgRow['image']);
            }

            $dStmt = $pdo->prepare("DELETE FROM gallery_images WHERE id IN ($placeholders)");
            $dStmt->execute($ids);
            $msg = count($ids) . ' photo(s) deleted permanently.';
        } else {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid bulk action requested.']);
                exit;
            }
            redirect($backUrl, 'Invalid bulk action requested.', 'danger');
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'affected' => count($ids),
                'action' => $bulkType,
                'message' => $msg
            ]);
            exit;
        }

        redirect($backUrl, $msg, 'success');
    }

    // Default unknown action
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unknown action requested.']);
        exit;
    }
    redirect($backUrl, 'Unknown action requested.', 'danger');

} catch (Throwable $e) {
    error_log('Admin Gallery Action Error: ' . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'An unexpected server error occurred: ' . $e->getMessage()]);
        exit;
    }
    redirect($backUrl, 'An unexpected server error occurred. Please try again.', 'danger');
}
