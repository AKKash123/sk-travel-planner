<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

 $action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================
// TOGGLE STATUS
// ============================================
if ($action === 'toggle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) redirect('dashboard.php', 'Invalid ID.', 'danger');

    $stmt = $pdo->prepare("UPDATE itineraries SET status = NOT status WHERE id = ?");
    $stmt->execute([$id]);
    redirect('dashboard.php', 'Status updated.', 'success');
}

// ============================================
// DELETE
// ============================================
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) redirect('dashboard.php', 'Invalid ID.', 'danger');

    // Fetch image to delete file
    $stmt = $pdo->prepare("SELECT image FROM itineraries WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row && $row['image'] && file_exists(UPLOAD_DIR . $row['image'])) {
        unlink(UPLOAD_DIR . $row['image']);
    }

    $stmt = $pdo->prepare("DELETE FROM itineraries WHERE id = ?");
    $stmt->execute([$id]);
    redirect('dashboard.php', 'Itinerary deleted.', 'success');
}

// ============================================
// SAVE (Create or Update)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        redirect('dashboard.php', 'CSRF token mismatch. Try again.', 'danger');
    }

    $id            = (int)($_POST['id'] ?? 0);
    $title         = clean($_POST['title'] ?? '');
    $destination   = clean($_POST['destination'] ?? '');
    $description   = clean($_POST['description'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 1);
    $status        = (int)($_POST['status'] ?? 1);
    $old_image     = clean($_POST['old_image'] ?? '');

    // Validate required fields
    if (empty($title) || empty($destination)) {
        redirect('itinerary-form.php' . ($id ? "?id=$id" : ''), 'Title and Destination are required.', 'danger');
    }

    // Handle image upload
    $image = uploadImage('image', $old_image ?: null);
    if ($image === false && !empty($_FILES['image']['name'])) {
        redirect('itinerary-form.php' . ($id ? "?id=$id" : ''), 'Image upload failed. Check file type/size.', 'danger');
    }
    // If no new image uploaded and no old image, keep as-is
    if ($image === null) $image = '';

    // Process highlights, inclusions, exclusions (one per line → JSON array)
    $highlightsRaw = $_POST['highlights'] ?? '';
    $highlights = array_values(array_filter(array_map('trim', explode("\n", $highlightsRaw))));
    $highlightsJson = json_encode($highlights, JSON_UNESCAPED_UNICODE);

    $inclusionsRaw = $_POST['inclusions'] ?? '';
    $inclusions = array_values(array_filter(array_map('trim', explode("\n", $inclusionsRaw))));
    $inclusionsJson = json_encode($inclusions, JSON_UNESCAPED_UNICODE);

    $exclusionsRaw = $_POST['exclusions'] ?? '';
    $exclusions = array_values(array_filter(array_map('trim', explode("\n", $exclusionsRaw))));
    $exclusionsJson = json_encode($exclusions, JSON_UNESCAPED_UNICODE);

    // Process day-wise plan
    $dayNums   = $_POST['day_num'] ?? [];
    $dayTitles = $_POST['day_title'] ?? [];
    $dayDescs  = $_POST['day_desc'] ?? [];
    $dayMeals  = $_POST['day_meals'] ?? [];
    $dayHotels = $_POST['day_hotel'] ?? [];

    $dayPlanArr = [];
    for ($i = 0; $i < count($dayNums); $i++) {
        $dayPlanArr[] = [
            'day'   => (int)$dayNums[$i],
            'title' => clean($dayTitles[$i] ?? ''),
            'desc'  => clean($dayDescs[$i] ?? ''),
            'meals' => clean($dayMeals[$i] ?? ''),
            'hotel' => clean($dayHotels[$i] ?? ''),
        ];
    }
    $dayPlanJson = json_encode($dayPlanArr, JSON_UNESCAPED_UNICODE);

    try {
        if ($id > 0) {
            // UPDATE
            $stmt = $pdo->prepare("
                UPDATE itineraries SET
                    title = ?, destination = ?, description = ?,
                    price = ?, duration_days = ?, image = ?,
                    highlights = ?, inclusions = ?, exclusions = ?,
                    day_plan = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $destination, $description,
                $price, $duration_days, $image,
                $highlightsJson, $inclusionsJson, $exclusionsJson,
                $dayPlanJson, $status, $id
            ]);
            redirect('dashboard.php', 'Itinerary updated successfully.', 'success');
        } else {
            // INSERT
            $stmt = $pdo->prepare("
                INSERT INTO itineraries
                    (title, destination, description, price, duration_days, image, highlights, inclusions, exclusions, day_plan, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $destination, $description,
                $price, $duration_days, $image,
                $highlightsJson, $inclusionsJson, $exclusionsJson,
                $dayPlanJson, $status
            ]);
            redirect('dashboard.php', 'Itinerary created successfully.', 'success');
        }
    } catch (PDOException $e) {
        redirect('dashboard.php', 'Database error: ' . $e->getMessage(), 'danger');
    }
}

// Default redirect
redirect('dashboard.php');
?>