<?php
require_once 'config.php';

// Fetch all active itineraries
 $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE status = 1 ORDER BY created_at DESC");
 $stmt->execute();
 $itineraries = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — Discover Your Next Adventure</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="container navbar-inner">
        <a href="index.php" class="navbar-brand">
            <span class="brand-icon"><i class="fas fa-plane"></i></span>
            <?= e(APP_NAME) ?>
        </a>
        <ul class="navbar-nav">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="#itineraries">Itineraries</a></li>
            <li><a href="admin/index.php"><i class="fas fa-lock"></i> Admin</a></li>
        </ul>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-shapes">
        <div class="hero-shape" style="width:300px;height:300px;top:10%;right:10%;animation-delay:-3s;"></div>
        <div class="hero-shape" style="width:200px;height:200px;bottom:20%;left:5%;animation-delay:-7s;"></div>
        <div class="hero-shape" style="width:150px;height:150px;top:60%;right:30%;animation-delay:-12s;"></div>
    </div>
    <div class="container hero-content">
        <h1>Plan Your Dream <span>Journey</span> With Confidence</h1>
        <p>Curated travel itineraries crafted by experts. From serene backwaters to majestic peaks — your perfect trip awaits.</p>
        <a href="#itineraries" class="btn btn-accent btn-lg">
            <i class="fas fa-compass"></i> Explore Itineraries
        </a>
    </div>
</section>

<!-- Itineraries Section -->
<section class="section" id="itineraries">
    <div class="container">
        <div style="text-align:center;margin-bottom:48px;">
            <h2 style="font-size:36px;margin-bottom:10px;">Handcrafted Itineraries</h2>
            <p style="color:var(--text-muted);font-size:17px;max-width:550px;margin:0 auto;">Each itinerary is meticulously planned with day-wise schedules, handpicked stays, and local experiences.</p>
        </div>

        <?php if (empty($itineraries)): ?>
            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                <i class="fas fa-suitcase-rolling" style="font-size:48px;margin-bottom:16px;color:var(--border);"></i>
                <p style="font-size:18px;">No itineraries available yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($itineraries as $itin): ?>
                    <?php
                        $highlights = json_decode($itin['highlights'], true) ?? [];
                        $days = json_decode($itin['day_plan'], true) ?? [];
                    ?>
                    <div class="itin-card">
                        <div class="itin-card-img">
                            <img src="<?= e(imageUrl($itin['image'])) ?>" alt="<?= e($itin['title']) ?>" loading="lazy">
                            <span class="itin-card-badge"><?= (int)$itin['duration_days'] ?> Days</span>
                        </div>
                        <div class="itin-card-body">
                            <h3><?= e($itin['title']) ?></h3>
                            <div class="dest">
                                <i class="fas fa-map-marker-alt"></i> <?= e($itin['destination']) ?>
                            </div>
                            <p class="desc"><?= e($itin['description']) ?></p>
                            <div class="itin-card-meta">
                                <span><i class="fas fa-calendar-alt"></i> <?= (int)$itin['duration_days'] ?> Days</span>
                                <span><i class="fas fa-star"></i> <?= count($highlights) ?> Highlights</span>
                                <span><i class="fas fa-route"></i> <?= count($days) ?> Stops</span>
                            </div>
                            <div class="itin-card-footer">
                                <span class="itin-price"><?= e(formatPrice($itin['price'])) ?></span>
                                <a href="detail.php?id=<?= (int)$itin['id'] ?>" class="btn btn-primary btn-sm">
                                    View Details <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h4><i class="fas fa-plane"></i> <?= e(APP_NAME) ?></h4>
                <p style="max-width:320px;">Your trusted partner for unforgettable travel experiences. Expert-crafted itineraries for destinations across the globe.</p>
            </div>
            <div>
                <h4>Quick Links</h4>
                <ul style="list-style:none;">
                    <li style="margin-bottom:8px;"><a href="index.php">Home</a></li>
                    <li style="margin-bottom:8px;"><a href="#itineraries">Itineraries</a></li>
                    <li style="margin-bottom:8px;"><a href="admin/index.php">Admin Panel</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> info@sktravelplanner.in</p>
                <p><i class="fas fa-phone"></i> +91 98765 43210</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>