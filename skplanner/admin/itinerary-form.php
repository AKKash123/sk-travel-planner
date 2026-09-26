<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

 $id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
 $edit  = false;
 $itin  = [
    'title' => '', 'destination' => '', 'description' => '',
    'price' => '', 'duration_days' => 1, 'image' => '',
    'highlights' => '[]', 'inclusions' => '[]', 'exclusions' => '[]',
    'day_plan' => '[]', 'status' => 1
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $itin = $row;
        $edit = true;
    } else {
        redirect('dashboard.php', 'Itinerary not found.', 'danger');
    }
}

 $highlights = json_decode($itin['highlights'], true) ?? [];
 $inclusions = json_decode($itin['inclusions'], true) ?? [];
 $exclusions = json_decode($itin['exclusions'], true) ?? [];
 $dayPlan    = json_decode($itin['day_plan'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit ? 'Edit' : 'Create' ?> Itinerary — <?= e(APP_NAME) ?> Admin</title>
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
        >

  
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/icons/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32.png">
        <link rel="icon" href="../assets/icons/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="../assets/icons/apple-touch-icon.png">
</head>
<body>

<div class="admin-layout">
    <?php include 'sidebar-fragment.php'; ?>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1><i class="fas fa-<?= $edit ? 'edit' : 'plus-circle' ?>"></i> <?= $edit ? 'Edit' : 'Create' ?> Itinerary</h1>
            <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="admin-content">
            <?= flashMsg() ?>

            <form action="itinerary-action.php" method="POST" enctype="multipart/form-data" class="form-card">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" value="<?= e($itin['title']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Destination *</label>
                        <input type="text" name="destination" class="form-control" value="<?= e($itin['destination']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($itin['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (INR) *</label>
                        <input type="number" name="price" class="form-control" value="<?= e($itin['price']) ?>" min="0" step="1" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (Days) *</label>
                        <input type="number" name="duration_days" class="form-control" value="<?= (int)$itin['duration_days'] ?>" min="1" max="60" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Featured Image</label>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if ($edit && $itin['image']): ?>
                        <div class="image-preview">
                            <img src="<?= e(imageUrl($itin['image'])) ?>" alt="Current image" style="margin-top:10px;max-height:120px;border-radius:8px;">
                            <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">Current image. Upload new to replace.</p>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="old_image" value="<?= e($itin['image']) ?>">
                </div>

                <!-- Highlights -->
                <div class="form-group">
                    <label>Highlights (one per line)</label>
                    <textarea name="highlights" class="form-control" rows="5" placeholder="Visit Tegallalang Rice Terraces&#10;Sunset at Uluwatu Temple&#10;Snorkeling with manta rays"><?= e(implode("\n", $highlights)) ?></textarea>
                </div>

                <!-- Inclusions -->
                <div class="form-group">
                    <label>Inclusions (one per line)</label>
                    <textarea name="inclusions" class="form-control" rows="4" placeholder="Airport transfers&#10;4-star accommodation&#10;Daily breakfast"><?= e(implode("\n", $inclusions)) ?></textarea>
                </div>

                <!-- Exclusions -->
                <div class="form-group">
                    <label>Exclusions (one per line)</label>
                    <textarea name="exclusions" class="form-control" rows="4" placeholder="International flights&#10;Travel insurance&#10;Personal expenses"><?= e(implode("\n", $exclusions)) ?></textarea>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="1" <?= $itin['status'] ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= !$itin['status'] ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <hr style="margin:30px 0;border-color:var(--light-darker);">
                <h3 style="margin-bottom:20px;"><i class="fas fa-route"></i> Day-wise Itinerary</h3>

                <div id="day-plan-container">
                    <?php if (!empty($dayPlan)): ?>
                        <?php foreach ($dayPlan as $idx => $day): ?>
                        <div class="day-plan-row" data-index="<?= $idx ?>">
                            <button type="button" class="day-plan-remove" onclick="this.parentElement.remove();reindexDays();">&times;</button>
                            <h4>Day <span class="day-label"><?= (int)($day['day'] ?? $idx+1) ?></span></h4>
                            <input type="hidden" name="day_num[]" value="<?= (int)($day['day'] ?? $idx+1) ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Day Title</label>
                                    <input type="text" name="day_title[]" class="form-control" value="<?= e($day['title'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Meals</label>
                                    <input type="text" name="day_meals[]" class="form-control" value="<?= e($day['meals'] ?? '') ?>" placeholder="Breakfast, Lunch">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="day_desc[]" class="form-control" rows="2"><?= e($day['desc'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Accommodation</label>
                                <input type="text" name="day_hotel[]" class="form-control" value="<?= e($day['hotel'] ?? '') ?>" placeholder="Hotel name or N/A">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="btn btn-outline" onclick="addDay();" style="margin-bottom:30px;">
                    <i class="fas fa-plus"></i> Add Day
                </button>

                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="submit" class="btn btn-accent btn-lg">
                        <i class="fas fa-save"></i> <?= $edit ? 'Update Itinerary' : 'Create Itinerary' ?>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Reindex day numbers after removal
function reindexDays() {
    const rows = document.querySelectorAll('#day-plan-container .day-plan-row');
    rows.forEach((row, i) => {
        row.querySelector('.day-label').textContent = i + 1;
        row.querySelector('input[name="day_num[]"]').value = i + 1;
    });
}

// Add a new day plan row
function addDay() {
    const container = document.getElementById('day-plan-container');
    const idx = container.querySelectorAll('.day-plan-row').length + 1;
    const html = `
        <div class="day-plan-row" data-index="${idx}">
            <button type="button" class="day-plan-remove" onclick="this.parentElement.remove();reindexDays();">&times;</button>
            <h4>Day <span class="day-label">${idx}</span></h4>
            <input type="hidden" name="day_num[]" value="${idx}">
            <div class="form-row">
                <div class="form-group">
                    <label>Day Title</label>
                    <input type="text" name="day_title[]" class="form-control">
                </div>
                <div class="form-group">
                    <label>Meals</label>
                    <input type="text" name="day_meals[]" class="form-control" placeholder="Breakfast, Lunch">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="day_desc[]" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Accommodation</label>
                <input type="text" name="day_hotel[]" class="form-control" placeholder="Hotel name or N/A">
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

</body>
</html>