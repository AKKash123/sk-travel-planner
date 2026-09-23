<?php
// ============================================
// SK Travel Planner — Itinerary Detail Page
// ============================================
require_once 'config.php';

// Validate ID — must be positive integer
 $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect('index.php', 'Invalid itinerary requested.', 'danger');
}

// Fetch itinerary — prepared statement prevents SQL injection
 $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id = ? AND status = 1");
 $stmt->execute([$id]);
 $itin = $stmt->fetch();

if (!$itin) {
    redirect('index.php', 'Itinerary not found or no longer available.', 'danger');
}

// Decode JSON fields safely
 $highlights  = json_decode($itin['highlights'], true) ?? [];
 $inclusions  = json_decode($itin['inclusions'], true) ?? [];
 $exclusions  = json_decode($itin['exclusions'], true) ?? [];
 $dayPlan     = json_decode($itin['day_plan'], true) ?? [];

// Calculate trip stats
 $totalDays    = (int)$itin['duration_days'];
 $perDay       = $totalDays > 0 ? round((float)$itin['price'] / $totalDays) : 0;
 $highlightCnt = count($highlights);
 $inclusionCnt = count($inclusions);
 $stopCnt      = count($dayPlan);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($itin['title']) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ============================================
           Detail Page — Extended Styles
           ============================================ */

        /* ---- Hero Banner ---- */
        .detail-hero {
            position: relative;
            height: 480px;
            overflow: hidden;
        }
        .detail-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .detail-hero:hover img {
            transform: scale(1.02);
        }
        .detail-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                0deg,
                rgba(27, 40, 56, 0.92) 0%,
                rgba(27, 40, 56, 0.5) 40%,
                rgba(27, 40, 56, 0.15) 70%,
                transparent 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 50px;
        }
        .detail-hero-overlay h1 {
            color: var(--white);
            font-size: 44px;
            font-weight: 900;
            margin-bottom: 10px;
            animation: fadeUp 0.8s ease both;
        }
        .detail-dest-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-light);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            animation: fadeUp 0.8s 0.15s ease both;
        }
        .detail-hero-badges {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            animation: fadeUp 0.8s 0.3s ease both;
        }
        .detail-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 8px 18px;
            border-radius: 30px;
            color: var(--white);
            font-size: 14px;
            font-weight: 500;
        }
        .detail-hero-badge i {
            color: var(--accent-light);
        }

        /* ---- Breadcrumb ---- */
        .breadcrumb {
            padding: 16px 0;
            font-size: 14px;
            color: var(--text-muted);
        }
        .breadcrumb a { color: var(--primary); font-weight: 500; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb span { margin: 0 8px; color: var(--border); }

        /* ---- Main Grid ---- */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 40px;
            align-items: start;
        }

        /* ---- Info Box ---- */
        .info-box {
            background: var(--white);
            border-radius: var(--radius);
            padding: 32px;
            box-shadow: var(--shadow);
            margin-bottom: 28px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .info-box.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .info-box h2 {
            font-size: 22px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--light-darker);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-box h2 i {
            color: var(--primary);
            font-size: 20px;
        }

        /* ---- Overview Text ---- */
        .overview-text {
            font-size: 16px;
            line-height: 1.9;
            color: var(--text);
        }

        /* ---- Stats Row ---- */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .stat-card.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--accent); }
        .stat-card:nth-child(3)::before { background: var(--success); }
        .stat-card:nth-child(4)::before { background: var(--warning); }
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .stat-card:nth-child(1) .stat-icon { background: rgba(13,115,119,0.1); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(232,145,45,0.1); color: var(--accent); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(46,139,87,0.1); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(212,160,23,0.1); color: var(--warning); }
        .stat-card .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--dark);
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ---- Highlights Grid ---- */
        .highlights-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .highlight-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: var(--light);
            border-radius: var(--radius-sm);
            font-size: 15px;
            transition: all var(--transition);
        }
        .highlight-item:hover {
            background: rgba(13,115,119,0.06);
            transform: translateX(4px);
        }
        .highlight-item .hi-icon {
            width: 28px;
            height: 28px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ---- Day Cards ---- */
        .day-card {
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary);
            opacity: 0;
            transform: translateX(-20px);
            transition: opacity 0.5s ease, transform 0.5s ease, box-shadow var(--transition);
        }
        .day-card.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .day-card:hover {
            box-shadow: var(--shadow-lg);
        }
        .day-card-header {
            background: linear-gradient(135deg, var(--light) 0%, var(--light-darker) 100%);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .day-num {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(13,115,119,0.25);
        }
        .day-card-header .day-header-title {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }
        .day-card-body {
            padding: 18px 22px;
        }
        .day-card-body .day-desc {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 14px;
        }
        .day-card-body .day-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .day-meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .day-meta-tag.meals {
            background: rgba(232,145,45,0.1);
            color: var(--accent-dark);
        }
        .day-meta-tag.hotel {
            background: rgba(13,115,119,0.1);
            color: var(--primary-dark);
        }

        /* ---- Inclusion/Exclusion Grid ---- */
        .inc-exc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .inc-list, .exc-list {
            list-style: none;
            padding: 0;
        }
        .inc-list li, .exc-list li {
            padding: 10px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 15px;
            border-bottom: 1px solid var(--light-darker);
        }
        .inc-list li:last-child, .exc-list li:last-child {
            border-bottom: none;
        }
        .inc-list li .list-dot {
            width: 22px;
            height: 22px;
            background: rgba(46,139,87,0.12);
            color: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .exc-list li .list-dot {
            width: 22px;
            height: 22px;
            background: rgba(220,53,69,0.1);
            color: var(--danger);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ---- Sidebar ---- */
        .sidebar-box {
            background: var(--white);
            border-radius: var(--radius);
            padding: 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 90px;
            overflow: hidden;
        }
        .sidebar-price-section {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary-dark) 100%);
            padding: 28px;
            text-align: center;
        }
        .sidebar-price-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .sidebar-price-big {
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            font-weight: 700;
            color: var(--white);
        }
        .sidebar-price-note {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-top: 4px;
        }
        .sidebar-per-day {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 14px;
            background: rgba(232,145,45,0.2);
            border-radius: 20px;
            font-size: 13px;
            color: var(--accent-light);
            font-weight: 600;
        }

        .sidebar-details {
            padding: 24px 28px;
        }
        .sidebar-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-darker);
            font-size: 15px;
        }
        .sidebar-detail-row:last-child {
            border-bottom: none;
        }
        .sidebar-detail-row .sdr-label {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-detail-row .sdr-label i {
            color: var(--primary);
            width: 18px;
            text-align: center;
        }
        .sidebar-detail-row .sdr-value {
            font-weight: 600;
            color: var(--dark);
        }

        .sidebar-actions {
            padding: 0 28px 28px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ---- Day Timeline Connector ---- */
        .day-timeline {
            position: relative;
        }
        .day-timeline::before {
            content: '';
            position: absolute;
            left: 22px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--accent), var(--primary-light));
            border-radius: 2px;
            z-index: 0;
        }
        .day-timeline .day-card {
            position: relative;
            z-index: 1;
            margin-left: 20px;
        }

        /* ---- CTA Banner ---- */
        .cta-banner {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            border-radius: var(--radius);
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            margin-top: 40px;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(232,145,45,0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        .cta-banner h3 {
            color: var(--white);
            font-size: 26px;
            margin-bottom: 6px;
        }
        .cta-banner p {
            color: rgba(255,255,255,0.75);
            font-size: 16px;
            max-width: 500px;
        }
        .cta-banner .btn {
            flex-shrink: 0;
        }

        /* ---- Animations ---- */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(25px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---- Responsive ---- */
        @media (max-width: 900px) {
            .detail-grid { grid-template-columns: 1fr; }
            .sidebar-box { position: static; }
            .detail-hero { height: 360px; }
            .detail-hero-overlay { padding: 30px; }
            .detail-hero-overlay h1 { font-size: 30px; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .highlights-grid { grid-template-columns: 1fr; }
            .inc-exc-grid { grid-template-columns: 1fr; }
            .cta-banner { flex-direction: column; text-align: center; }
            .cta-banner p { max-width: 100%; }
        }

        @media (max-width: 480px) {
            .detail-hero { height: 280px; }
            .detail-hero-overlay h1 { font-size: 24px; }
            .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card .stat-value { font-size: 20px; }
        }
    </style>
</head>
<body>

<!-- ============ NAVBAR ============ -->
<nav class="navbar">
    <div class="container navbar-inner">
        <a href="index.php" class="navbar-brand">
            <span class="brand-icon"><i class="fas fa-plane"></i></span>
            <?= e(APP_NAME) ?>
        </a>
        <ul class="navbar-nav">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="index.php#itineraries"><i class="fas fa-compass"></i> Itineraries</a></li>
        </ul>
    </div>
</nav>

<!-- ============ HERO BANNER ============ -->
<div class="detail-hero">
    <img src="<?= e(imageUrl($itin['image'])) ?>" alt="<?= e($itin['title']) ?>">
    <div class="detail-hero-overlay">
        <h1><?= e($itin['title']) ?></h1>
        <div class="detail-dest-tag">
            <i class="fas fa-map-marker-alt"></i> <?= e($itin['destination']) ?>
        </div>
        <div class="detail-hero-badges">
            <span class="detail-hero-badge">
                <i class="fas fa-calendar-alt"></i> <?= $totalDays ?> Days
            </span>
            <span class="detail-hero-badge">
                <i class="fas fa-star"></i> <?= $highlightCnt ?> Highlights
            </span>
            <span class="detail-hero-badge">
                <i class="fas fa-route"></i> <?= $stopCnt ?> Stops
            </span>
            <span class="detail-hero-badge">
                <i class="fas fa-tag"></i> <?= e(formatPrice($itin['price'])) ?>
            </span>
        </div>
    </div>
</div>

<!-- ============ BREADCRUMB ============ -->
<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>/</span>
        <a href="index.php#itineraries">Itineraries</a>
        <span>/</span>
        <?= e($itin['title']) ?>
    </div>
</div>

<!-- ============ STATS ROW ============ -->
<section class="container" style="margin-bottom: 40px;">
    <div class="stats-row" id="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?= $totalDays ?></div>
            <div class="stat-label">Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-value"><?= e(formatPrice($perDay)) ?></div>
            <div class="stat-label">Per Day</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $inclusionCnt ?></div>
            <div class="stat-label">Inclusions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-map-pin"></i></div>
            <div class="stat-value"><?= $stopCnt ?></div>
            <div class="stat-label">Destinations</div>
        </div>
    </div>
</section>

<!-- ============ MAIN CONTENT ============ -->
<section style="padding-bottom: 60px;">
    <div class="container detail-grid">

        <!-- ====== LEFT COLUMN ====== -->
        <div>

            <!-- Overview -->
            <div class="info-box" data-animate>
                <h2><i class="fas fa-info-circle"></i> Overview</h2>
                <p class="overview-text"><?= nl2br(e($itin['description'])) ?></p>
            </div>

            <!-- Highlights -->
            <?php if (!empty($highlights)): ?>
            <div class="info-box" data-animate>
                <h2><i class="fas fa-star"></i> Trip Highlights</h2>
                <div class="highlights-grid">
                    <?php foreach ($highlights as $idx => $h): ?>
                    <div class="highlight-item">
                        <span class="hi-icon"><i class="fas fa-check"></i></span>
                        <span><?= e($h) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Day-wise Itinerary -->
            <?php if (!empty($dayPlan)): ?>
            <div class="info-box" data-animate style="padding-bottom: 12px;">
                <h2><i class="fas fa-route"></i> Day-wise Itinerary</h2>
            </div>
            <div class="day-timeline">
                <?php foreach ($dayPlan as $idx => $d): ?>
                <div class="day-card" data-animate data-delay="<?= $idx * 80 ?>">
                    <div class="day-card-header">
                        <span class="day-num"><?= (int)$d['day'] ?></span>
                        <span class="day-header-title"><?= e($d['title'] ?? 'Day ' . $d['day']) ?></span>
                    </div>
                    <div class="day-card-body">
                        <p class="day-desc"><?= e($d['desc'] ?? 'Explore and enjoy the destination at your pace.') ?></p>
                        <div class="day-meta">
                            <?php if (!empty($d['meals'])): ?>
                            <span class="day-meta-tag meals">
                                <i class="fas fa-utensils"></i> <?= e($d['meals']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($d['hotel'])): ?>
                            <span class="day-meta-tag hotel">
                                <i class="fas fa-bed"></i> <?= e($d['hotel']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Inclusions & Exclusions -->
            <?php if (!empty($inclusions) || !empty($exclusions)): ?>
            <div class="inc-exc-grid">
                <?php if (!empty($inclusions)): ?>
                <div class="info-box" data-animate>
                    <h2 style="color: var(--success);"><i class="fas fa-check-circle"></i> Inclusions</h2>
                    <ul class="inc-list">
                        <?php foreach ($inclusions as $inc): ?>
                        <li>
                            <span class="list-dot"><i class="fas fa-check"></i></span>
                            <span><?= e($inc) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($exclusions)): ?>
                <div class="info-box" data-animate>
                    <h2 style="color: var(--danger);"><i class="fas fa-times-circle"></i> Exclusions</h2>
                    <ul class="exc-list">
                        <?php foreach ($exclusions as $exc): ?>
                        <li>
                            <span class="list-dot"><i class="fas fa-times"></i></span>
                            <span><?= e($exc) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- CTA Banner -->
            <div class="cta-banner" data-animate>
                <div style="position: relative; z-index: 1;">
                    <h3>Ready to embark on this journey?</h3>
                    <p>Contact us soon, lets <?= e($itin['destination']) ?> adventure today.</p>
                </div>
                                <!-- FIXED -->
                <!-- <a href="download-pdf.php?id=<?= (int)$itin['id'] ?>" class="btn btn-accent">
                    <i class="fas fa-file-pdf"></i> Download Itinerary PDF
                </a> -->
            </div>

        </div>

        <!-- ====== RIGHT SIDEBAR ====== -->
        <div>
            <div class="sidebar-box">

                <!-- Price Section -->
                <div class="sidebar-price-section">
                    <div class="sidebar-price-label">Starting from</div>
                    <div class="sidebar-price-big"><?= e(formatPrice($itin['price'])) ?></div>
                    <div class="sidebar-price-note">Per person</div>
                    <?php if ($perDay > 0): ?>
                    <div class="sidebar-per-day">
                        <i class="fas fa-calculator"></i> Approx. <?= e(formatPrice($perDay)) ?>/day
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Trip Details -->
                <div class="sidebar-details">
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-clock"></i> Duration</span>
                        <span class="sdr-value"><?= $totalDays ?> Days</span>
                    </div>
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-map-marker-alt"></i> Destination</span>
                        <span class="sdr-value"><?= e($itin['destination']) ?></span>
                    </div>
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-star"></i> Highlights</span>
                        <span class="sdr-value"><?= $highlightCnt ?></span>
                    </div>
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-route"></i> Stops</span>
                        <span class="sdr-value"><?= $stopCnt ?></span>
                    </div>
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-check-circle"></i> Included</span>
                        <span class="sdr-value"><?= $inclusionCnt ?> items</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="sidebar-actions">
                    <!-- <a href="download-pdf.php?id=<?= (int)$itin['id'] ?>" class="btn btn-accent" style="width:100%;justify-content:center;">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a> -->
                    <a href="index.php" class="btn btn-outline" style="width:100%;justify-content:center;">
                        <i class="fas fa-arrow-left"></i> All Itineraries
                    </a>
                </div>

            </div>
        </div>

    </div>
</section>

<!-- ============ FOOTER ============ -->
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
                    <li style="margin-bottom:8px;"><a href="index.php#itineraries">Itineraries</a></li>
                    <li style="margin-bottom:8px;"><a href="admin/index.php">Admin Panel</a></li>
                </ul>
            </div>
            <div>
                <h4>Get in Touch</h4>
                <p style="margin-bottom:6px;"><i class="fas fa-envelope" style="width:20px;"></i> info@sktravelplanner.com</p>
                <p style="margin-bottom:6px;"><i class="fas fa-phone" style="width:20px;"></i> +91 98765 43210</p>
                <p><i class="fas fa-map-marker-alt" style="width:20px;"></i> Mumbai, India</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- ============ SCROLL ANIMATIONS ============ -->
<script>
    // Intersection Observer for scroll-triggered animations
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const delay = parseInt(entry.target.getAttribute('data-delay') || '0', 10);
                    setTimeout(function() {
                        entry.target.classList.add('visible');
                    }, delay);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px'
        });

        // Observe all animatable elements
        document.querySelectorAll('[data-animate]').forEach(function(el) {
            observer.observe(el);
        });

        // Observe stat cards
        document.querySelectorAll('.stat-card').forEach(function(el, i) {
            el.setAttribute('data-delay', i * 100);
            observer.observe(el);
        });
    });

    // Smooth parallax on hero image (subtle)
    window.addEventListener('scroll', function() {
        var hero = document.querySelector('.detail-hero img');
        if (hero) {
            var scrolled = window.pageYOffset;
            hero.style.transform = 'translateY(' + (scrolled * 0.25) + 'px) scale(1.05)';
        }
    }, { passive: true });
</script>

</body>
</html>