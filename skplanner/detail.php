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
$highlights = json_decode($itin['highlights'], true) ?? [];
$inclusions = json_decode($itin['inclusions'], true) ?? [];
$exclusions = json_decode($itin['exclusions'], true) ?? [];
$dayPlan    = json_decode($itin['day_plan'], true) ?? [];

// Calculate trip stats
$totalDays    = (int)$itin['duration_days'];
$perDay       = $totalDays > 0 ? round((float)$itin['price'] / $totalDays) : 0;
$highlightCnt = count($highlights);
$inclusionCnt = count($inclusions);
$stopCnt      = count($dayPlan);

// Ratings — this itinerary only (fails gracefully if the table is missing)
$ratings = [];
try {
    $rs = $pdo->prepare(
        "SELECT itinerary_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM itinerary_reviews
         WHERE itinerary_id = ?
         GROUP BY itinerary_id"
    );
    $rs->execute([$id]);
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ratings[(int)$r['itinerary_id']] = [
            'avg'   => (float)$r['avg_rating'],
            'count' => (int)$r['total'],
        ];
    }
} catch (Throwable $e) {
    error_log('Ratings unavailable: ' . $e->getMessage());
}
$ratingAvg   = $ratings[$id]['avg']   ?? 0.0;
$ratingCount = $ratings[$id]['count'] ?? 0;

// Renders 5 Font Awesome stars (full / half / empty) for a 0–5 average
if (!function_exists('renderStars')) {
    function renderStars(float $avg): string {
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($avg >= $i)            $html .= '<i class="fas fa-star"></i>';
            elseif ($avg >= $i - 0.5)  $html .= '<i class="fas fa-star-half-alt"></i>';
            else                       $html .= '<i class="far fa-star"></i>';
        }
        return $html;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($itin['title']) ?> — <?= e(APP_NAME) ?></title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" href="assets/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($ratingCount > 0): ?>
    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'TouristTrip',
        'name'     => $itin['title'],
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => $ratingAvg,
            'reviewCount' => $ratingCount,
            'bestRating'  => 5,
            'worstRating' => 1,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
    </script>
    <?php endif; ?>
    <style>
        /* ============================================
           Detail Page — Extended Styles
           ============================================ */

        /* ---- Ratings ---- */
        .stars { display: inline-flex; gap: 2px; color: #f5b301; }
        .detail-hero-badge.rating .stars { font-size: 13px; }
        .detail-hero-badge.rating .stars i { color: #f5b301; }
        .rating-summary {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 12px; font-size: 14px; color: rgba(255,255,255,0.85);
        }
        .rating-summary .rating-num { font-weight: 700; color: #fff; font-size: 16px; }
        .rating-summary .rating-count { color: rgba(255,255,255,0.6); font-size: 13px; }
        .rating-empty { margin-top: 12px; font-size: 13px; color: rgba(255,255,255,0.6); }
        html { scroll-behavior: smooth; scroll-padding-top: 130px; }

        body.detail-page {
            background:
                radial-gradient(circle at 0% 0%, rgba(13, 115, 119, 0.09), transparent 32%),
                radial-gradient(circle at 100% 8%, rgba(232, 145, 45, 0.12), transparent 30%),
                radial-gradient(ellipse at 5% 92%, rgba(72, 112, 91, 0.13) 0%, rgba(72, 112, 91, 0.05) 25%, transparent 48%),
                linear-gradient(180deg, #fffaf3 0%, #f7f3ed 50%, #efe7da 100%);
            color: var(--text);
        }
        .detail-card {
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(27, 40, 56, 0.08);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(27, 40, 56, 0.09);
            backdrop-filter: blur(12px);
        }

        /* ---- Scroll progress ---- */
        .scroll-progress {
            position: fixed; top: 0; left: 0; height: 4px; width: 0;
            background: linear-gradient(90deg, var(--accent), var(--primary));
            z-index: 2000; transition: width .1s linear;
        }

        /* ---- Hero Banner ---- */
        .detail-hero { position: relative; height: 480px; overflow: hidden; }
        .detail-hero img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
        .detail-hero:hover img { transform: scale(1.02); }
        .detail-hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(0deg, rgba(27,40,56,0.92) 0%, rgba(27,40,56,0.5) 40%, rgba(27,40,56,0.15) 70%, transparent 100%);
            display: flex; flex-direction: column; justify-content: flex-end; padding: 50px;
        }
        .detail-hero-overlay h1 { color: var(--white); font-size: 44px; font-weight: 900; margin-bottom: 10px; animation: fadeUp 0.8s ease both; }
        .detail-dest-tag {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--accent-light); font-size: 18px; font-weight: 600; margin-bottom: 20px;
            animation: fadeUp 0.8s 0.15s ease both;
        }
        .detail-hero-badges { display: flex; gap: 12px; flex-wrap: wrap; animation: fadeUp 0.8s 0.3s ease both; }
        .detail-hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 8px 18px; border-radius: 30px; color: var(--white); font-size: 14px; font-weight: 500;
        }
        .detail-hero-badge i { color: var(--accent-light); }

        /* ---- Breadcrumb ---- */
        .breadcrumb { padding: 16px 0; font-size: 14px; color: var(--text-muted); }
        .breadcrumb a { color: var(--primary); font-weight: 500; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb span { margin: 0 8px; color: var(--border); }

        /* ---- Main Grid ---- */
        .detail-grid { display: grid; grid-template-columns: 1fr 360px; gap: 40px; align-items: start; }

        /* ---- Section nav (adjust top to your navbar height) ---- */
        .section-nav {
            position: sticky; top: 70px; z-index: 50;
            display: flex; gap: 8px; overflow-x: auto;
            padding: 10px; margin-bottom: 24px;
            background: rgba(255,255,255,.9); backdrop-filter: blur(10px);
            border: 1px solid rgba(27,40,56,.08); border-radius: 40px;
            box-shadow: var(--shadow);
        }
        .section-nav a {
            padding: 8px 18px; border-radius: 30px; white-space: nowrap;
            font-size: 14px; font-weight: 600; color: var(--text-muted); transition: all .25s ease;
        }
        .section-nav a:hover { color: var(--primary); background: rgba(13,115,119,.08); }
        .section-nav a.active { color: #fff; background: linear-gradient(135deg, var(--accent), var(--primary)); }

        /* ---- Info Box ---- */
        .info-box {
            background: var(--white); border-radius: var(--radius); padding: 32px;
            box-shadow: var(--shadow); margin-bottom: 28px;
            opacity: 0; transform: translateY(20px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .info-box.visible { opacity: 1; transform: translateY(0); }
        .info-box h2 {
            font-size: 22px; margin-bottom: 18px; padding-bottom: 14px;
            border-bottom: 2px solid var(--light-darker);
            display: flex; align-items: center; gap: 10px;
        }
        .info-box h2 i { color: var(--primary); font-size: 20px; }

        .overview-text { font-size: 16px; line-height: 1.9; color: var(--text); }

        /* ---- Stats Row ---- */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: var(--white); border-radius: var(--radius); padding: 20px;
            box-shadow: var(--shadow); text-align: center; position: relative; overflow: hidden;
            opacity: 0; transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .stat-card.visible { opacity: 1; transform: translateY(0); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--accent); }
        .stat-card:nth-child(3)::before { background: var(--success); }
        .stat-card:nth-child(4)::before { background: var(--warning); }
        .stat-card .stat-icon {
            width: 44px; height: 44px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 18px; margin-bottom: 8px;
        }
        .stat-card:nth-child(1) .stat-icon { background: rgba(13,115,119,0.1); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: rgba(232,145,45,0.1); color: var(--accent); }
        .stat-card:nth-child(3) .stat-icon { background: rgba(46,139,87,0.1); color: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: rgba(212,160,23,0.1); color: var(--warning); }
        .stat-card .stat-value { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 700; color: var(--dark); }
        .stat-card .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

        /* ---- Highlights Grid ---- */
        .highlights-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .highlight-item {
            display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px;
            background: var(--light); border-radius: var(--radius-sm); font-size: 15px;
            transition: all var(--transition);
        }
        .highlight-item:hover { background: rgba(13,115,119,0.06); transform: translateX(4px); }
        .highlight-item .hi-icon {
            width: 28px; height: 28px; background: var(--primary); color: var(--white);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 12px; flex-shrink: 0; margin-top: 1px;
        }

        /* ---- Day toolbar ---- */
        .day-toolbar { display: flex; justify-content: flex-end; gap: 10px; margin: -6px 0 16px; }
        .day-toolbar button {
            border: 1px solid var(--border); background: var(--white); color: var(--primary);
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all .2s ease;
        }
        .day-toolbar button:hover { background: var(--primary); color: #fff; }

        /* ---- Day Cards ---- */
        .day-card {
            background: var(--white); border-radius: var(--radius); overflow: hidden;
            box-shadow: var(--shadow); margin-bottom: 20px; border-left: 5px solid var(--primary);
            opacity: 0; transform: translateX(-20px);
            transition: opacity 0.5s ease, transform 0.5s ease, box-shadow var(--transition);
        }
        .day-card.visible { opacity: 1; transform: translateX(0); }
        .day-card:hover { box-shadow: var(--shadow-lg); }
        .day-card-header {
            background: linear-gradient(135deg, var(--light) 0%, var(--light-darker) 100%);
            padding: 18px 22px; display: flex; align-items: center; gap: 14px;
            cursor: pointer; user-select: none;
        }
        .day-card-header .chevron { margin-left: auto; color: var(--primary); transition: transform .3s ease; }
        .day-card.collapsed .chevron { transform: rotate(-90deg); }
        .day-num {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 16px; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(13,115,119,0.25);
        }
        .day-card-header .day-header-title { font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 700; color: var(--dark); }
        .day-card-body { padding: 18px 22px; max-height: 1500px; overflow: hidden; transition: max-height .4s ease, padding .4s ease; }
        .day-card.collapsed .day-card-body { max-height: 0; padding-top: 0; padding-bottom: 0; }
        .day-card-body .day-desc { color: var(--text-muted); font-size: 15px; line-height: 1.7; margin-bottom: 14px; }
        .day-card-body .day-meta { display: flex; gap: 20px; flex-wrap: wrap; }
        .day-meta-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;
        }
        .day-meta-tag.meals { background: rgba(232,145,45,0.1); color: var(--accent-dark); }
        .day-meta-tag.hotel { background: rgba(13,115,119,0.1); color: var(--primary-dark); }

        /* ---- Inclusion/Exclusion Grid ---- */
        .inc-exc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .inc-list, .exc-list { list-style: none; padding: 0; }
        .inc-list li, .exc-list li {
            padding: 10px 0; display: flex; align-items: flex-start; gap: 10px;
            font-size: 15px; border-bottom: 1px solid var(--light-darker);
        }
        .inc-list li:last-child, .exc-list li:last-child { border-bottom: none; }
        .inc-list li .list-dot, .exc-list li .list-dot {
            width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; flex-shrink: 0; margin-top: 2px;
        }
        .inc-list li .list-dot { background: rgba(46,139,87,0.12); color: var(--success); }
        .exc-list li .list-dot { background: rgba(220,53,69,0.1); color: var(--danger); }

        /* ---- Sidebar ---- */
        .sidebar-box {
            background: var(--white); border-radius: var(--radius); padding: 0;
            box-shadow: var(--shadow); position: sticky; top: 90px; overflow: hidden;
        }
        .sidebar-price-section {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary-dark) 100%);
            padding: 28px; text-align: center;
        }
        .sidebar-price-label { font-size: 13px; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .sidebar-price-big { font-family: 'Playfair Display', serif; font-size: 40px; font-weight: 700; color: var(--white); }
        .sidebar-price-note { font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 4px; }
        .sidebar-per-day {
            display: inline-block; margin-top: 8px; padding: 4px 14px;
            background: rgba(232,145,45,0.2); border-radius: 20px;
            font-size: 13px; color: var(--accent-light); font-weight: 600;
        }
        .sidebar-details { padding: 24px 28px; }
        .sidebar-detail-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid var(--light-darker); font-size: 15px;
        }
        .sidebar-detail-row:last-child { border-bottom: none; }
        .sidebar-detail-row .sdr-label { color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .sidebar-detail-row .sdr-label i { color: var(--primary); width: 18px; text-align: center; }
        .sidebar-detail-row .sdr-value { font-weight: 600; color: var(--dark); }

        /* ---- Traveler calculator ---- */
        .traveler-calc { padding: 0 28px 20px; }
        .traveler-calc label { font-size: 13px; color: var(--text-muted); font-weight: 600; }
        .stepper {
            display: flex; align-items: center; justify-content: space-between;
            margin: 8px 0 14px; padding: 6px; background: var(--light); border-radius: 40px;
        }
        .stepper button {
            width: 38px; height: 38px; border: 0; border-radius: 50%; cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: #fff; font-size: 16px; transition: transform .15s ease;
        }
        .stepper button:hover { transform: scale(1.1); }
        .stepper button:active { transform: scale(.92); }
        .stepper .count { font-weight: 700; font-size: 18px; color: var(--dark); }
        .calc-total {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 16px; border-radius: 12px; background: rgba(13,115,119,.08); font-weight: 600;
        }
        .calc-total strong { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--primary-dark); }

        /* ---- Sidebar actions ---- */
        .sidebar-actions { padding: 0 28px 28px; display: flex; flex-direction: column; gap: 10px; }
        .btn-whatsapp {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px; border-radius: 12px; font-weight: 700; color: #fff; background: #25D366;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,211,102,.4); }
        .action-row { display: flex; gap: 10px; }
        .action-row button {
            flex: 1; padding: 11px; border-radius: 12px; cursor: pointer; font-weight: 600;
            border: 1px solid var(--border); background: var(--white); color: var(--dark);
            transition: all .2s ease;
        }
        .action-row button:hover { border-color: var(--primary); color: var(--primary); }
        .action-row button.saved { color: #e63946; border-color: #e63946; }
        .action-row button.saved i { animation: pop .35s ease; }
        @keyframes pop { 50% { transform: scale(1.5); } }

        /* ---- Day Timeline Connector ---- */
        .day-timeline { position: relative; }
        .day-timeline::before {
            content: ''; position: absolute; left: 22px; top: 0; bottom: 0; width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--accent), var(--primary-light));
            border-radius: 2px; z-index: 0;
        }
        .day-timeline .day-card { position: relative; z-index: 1; margin-left: 20px; }

        /* ---- CTA Banner ---- */
        .cta-banner {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            border-radius: var(--radius); padding: 40px;
            display: flex; align-items: center; justify-content: space-between; gap: 30px;
            margin-top: 40px; color: var(--white); position: relative; overflow: hidden;
        }
        .cta-banner::before {
            content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(232,145,45,0.2) 0%, transparent 70%); border-radius: 50%;
        }
        .cta-banner h3 { color: var(--white); font-size: 26px; margin-bottom: 6px; }
        .cta-banner p { color: rgba(255,255,255,0.75); font-size: 16px; max-width: 500px; }
        .cta-banner .btn { flex-shrink: 0; }

        /* ---- Back to top + toast ---- */
        .back-top {
            position: fixed; right: 24px; bottom: 24px; width: 46px; height: 46px;
            border: 0; border-radius: 50%; cursor: pointer; color: #fff; font-size: 16px;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            box-shadow: 0 8px 20px rgba(0,0,0,.2);
            opacity: 0; pointer-events: none; transform: translateY(20px);
            transition: all .3s ease; z-index: 100;
        }
        .back-top.show { opacity: 1; pointer-events: auto; transform: none; }
        .toast {
            position: fixed; left: 50%; bottom: 30px; transform: translate(-50%, 20px);
            background: var(--dark); color: #fff; padding: 10px 22px; border-radius: 30px;
            font-size: 14px; opacity: 0; transition: all .3s ease; z-index: 3000;
        }
        .toast.show { opacity: 1; transform: translate(-50%, 0); }

        /* ---- Animations ---- */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(25px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* =====================================================
           RESPONSIVE SYSTEM  (320px phones  →  4K / ultra-wide)
           Fluid sizing via clamp(), then breakpoints for layout.
           ===================================================== */

        /* ---- Fluid base (applies at every size) ---- */
        :root { --nav-h: 70px; }
        html { scroll-padding-top: calc(var(--nav-h) + 70px); -webkit-text-size-adjust: 100%; }
        img { max-width: 100%; }
        .detail-grid > *, .inc-exc-grid > * { min-width: 0; }   /* stop grid blow-out */
        .info-box, .day-card, .sidebar-box, .highlight-item,
        .inc-list li, .exc-list li, .breadcrumb { overflow-wrap: anywhere; }

        .section-nav { top: var(--nav-h); scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        .section-nav::-webkit-scrollbar { display: none; }

        .detail-grid {
            grid-template-columns: minmax(0, 1fr) clamp(300px, 28vw, 420px);
            gap: clamp(20px, 3vw, 48px);
        }
        .detail-hero { height: clamp(260px, 42vw, 580px); }
        .detail-hero-overlay { padding: clamp(18px, 4vw, 56px); }
        .detail-hero-overlay h1 { font-size: clamp(22px, 4.2vw, 54px); line-height: 1.15; }
        .detail-dest-tag { font-size: clamp(14px, 1.6vw, 20px); margin-bottom: clamp(12px, 2vw, 20px); }
        .detail-hero-badges { gap: clamp(8px, 1vw, 12px); }
        .detail-hero-badge { font-size: clamp(12px, 1.2vw, 15px); padding: 7px clamp(12px, 1.5vw, 18px); }

        .info-box { padding: clamp(18px, 3vw, 36px); margin-bottom: clamp(16px, 2.5vw, 28px); }
        .info-box h2 { font-size: clamp(18px, 2.2vw, 24px); }
        .overview-text { font-size: clamp(15px, 1.4vw, 17px); }
        .stats-row { gap: clamp(10px, 1.6vw, 20px); }
        .stat-card { padding: clamp(14px, 2vw, 24px); }
        .stat-card .stat-value { font-size: clamp(19px, 2.4vw, 30px); }
        .sidebar-price-big { font-size: clamp(30px, 3.2vw, 44px); }
        .cta-banner { padding: clamp(24px, 4vw, 48px); }
        .cta-banner h3 { font-size: clamp(20px, 2.4vw, 30px); }
        .day-card-header .day-header-title { font-size: clamp(16px, 1.6vw, 19px); }

        /* Safe-area aware floating elements (notches / home bars) */
        .back-top { right: max(16px, env(safe-area-inset-right)); bottom: max(16px, env(safe-area-inset-bottom)); }
        .toast { bottom: max(24px, calc(env(safe-area-inset-bottom) + 12px)); max-width: 90vw; text-align: center; }

        /* ---- Ultra-large: 4K / ultra-wide monitors ---- */
        @media (min-width: 1600px) {
            .container { max-width: 1500px; }
        }
        @media (min-width: 2200px) {
            .container { max-width: 1900px; }
            .detail-hero { height: 680px; }
            .detail-hero-overlay { padding-left: calc((100% - 1900px) / 2 + 56px); padding-right: calc((100% - 1900px) / 2 + 56px); }
            .overview-text, .highlight-item, .inc-list li, .exc-list li, .day-card-body .day-desc { font-size: 18px; }
            .section-nav a { font-size: 16px; }
        }
        @media (min-width: 3000px) {
            .container { max-width: 2400px; }
            .detail-hero { height: 820px; }
            .detail-hero-overlay { padding-left: calc((100% - 2400px) / 2 + 56px); padding-right: calc((100% - 2400px) / 2 + 56px); }
        }

        /* ---- Laptops / small desktops ---- */
        @media (max-width: 1100px) {
            .detail-grid { gap: 24px; }
            .highlights-grid { gap: 10px; }
        }

        /* ---- Tablets & below: single column ---- */
        @media (max-width: 900px) {
            :root { --nav-h: 64px; }
            .detail-grid { grid-template-columns: minmax(0, 1fr); }
            .sidebar-box { position: static; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .cta-banner { flex-direction: column; text-align: center; }
            .cta-banner p { max-width: 100%; }
            .breadcrumb { font-size: 13px; }
        }
        @media (max-width: 760px) {
            .inc-exc-grid { grid-template-columns: 1fr; gap: 0; }
        }

        /* ---- Large phones ---- */
        @media (max-width: 640px) {
            :root { --nav-h: 60px; }
            .highlights-grid { grid-template-columns: 1fr; }
            .section-nav { padding: 8px; border-radius: 30px; margin-bottom: 18px; }
            .section-nav a { padding: 7px 14px; font-size: 13px; }
            .day-toolbar { justify-content: stretch; }
            .day-toolbar button { flex: 1; }
            .day-timeline::before { left: 12px; }
            .day-timeline .day-card { margin-left: 12px; border-left-width: 4px; }
            .day-card-header { padding: 14px 16px; gap: 10px; }
            .day-num { width: 34px; height: 34px; font-size: 14px; }
            .day-card-body { padding: 14px 16px; }
            .day-card-body .day-meta { gap: 8px; }
            .day-meta-tag { font-size: 12px; padding: 4px 11px; }
            .sidebar-price-section { padding: 22px; }
            .sidebar-details { padding: 16px 20px; }
            .sidebar-detail-row { font-size: 14px; padding: 10px 0; gap: 12px; }
            .sidebar-detail-row .sdr-value { text-align: right; }
            .traveler-calc { padding: 0 20px 16px; }
            .sidebar-actions { padding: 0 20px 22px; }
            .stepper button, .action-row button, .btn-whatsapp { min-height: 44px; } /* touch targets */
        }

        /* ---- Small phones ---- */
        @media (max-width: 480px) {
            .stats-row { gap: 10px; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 15px; }
            .stat-card .stat-label { font-size: 12px; }
            .detail-hero-badge { padding: 6px 12px; }
            .detail-hero-badge.rating .stars { font-size: 11px; }
            .info-box h2 { gap: 8px; padding-bottom: 10px; margin-bottom: 14px; }
            .highlight-item { padding: 10px 12px; font-size: 14px; }
            .inc-list li, .exc-list li { font-size: 14px; }
            .cta-banner { gap: 18px; }
            .back-top { width: 42px; height: 42px; }
        }

        /* ---- Ultra-small phones (≤360px, e.g. 320px devices) ---- */
        @media (max-width: 360px) {
            .stats-row { gap: 8px; }
            .stat-card { padding: 12px 8px; }
            .stat-card .stat-value { font-size: 18px; }
            .detail-hero { height: 240px; }
            .detail-hero-overlay { padding: 14px; }
            .detail-hero-overlay h1 { font-size: 20px; }
            .detail-hero-badges { gap: 6px; }
            .detail-hero-badge { font-size: 11px; padding: 5px 10px; gap: 5px; }
            .section-nav a { padding: 6px 11px; font-size: 12px; }
            .info-box { padding: 16px 14px; border-radius: 14px; }
            .info-box h2 { font-size: 17px; }
            .overview-text { font-size: 14.5px; line-height: 1.75; }
            .day-timeline::before { display: none; }              /* save horizontal space */
            .day-timeline .day-card { margin-left: 0; }
            .day-toolbar button { font-size: 12px; padding: 6px 8px; }
            .sidebar-price-big { font-size: 28px; }
            .sidebar-detail-row { flex-wrap: wrap; }
            .action-row { flex-direction: column; }
            .calc-total strong { font-size: 19px; }
            .cta-banner { padding: 22px 16px; }
        }

        /* ---- Landscape phones (short viewports) ---- */
        @media (max-height: 500px) and (orientation: landscape) {
            .detail-hero { height: 300px; }
            .section-nav { position: static; }
            .sidebar-box { position: static; }
        }

        /* ---- Touch devices: no sticky hover effects ---- */
        @media (hover: none) {
            .detail-hero:hover img,
            .highlight-item:hover,
            .btn-whatsapp:hover,
            .stepper button:hover { transform: none; }
        }

        /* ---- Respect reduced-motion preference ---- */
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .info-box, .stat-card, .day-card { opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>
<body class="detail-page">

<!-- ============ INTERACTIVE HELPERS ============ -->
<div class="scroll-progress" id="scroll-progress"></div>
<button class="back-top" id="back-top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>
<div class="toast" id="toast"></div>

<!-- ============ NAVBAR ============ -->
<nav class="navbar">
    <div class="container navbar-inner">
        <a href="index.php" class="navbar-brand">
            <span class="brand-icon"><img src="logo.jpg" alt="Logo"></span>
            <?= e(APP_NAME) ?>
        </a>
        <ul class="navbar-nav">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="index.php#itineraries"><i class="fas fa-compass"></i>Destinations</a></li>
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
            <span class="detail-hero-badge"><i class="fas fa-calendar-alt"></i> <?= $totalDays ?> Days</span>
            <span class="detail-hero-badge"><i class="fas fa-star"></i> <?= $highlightCnt ?> Highlights</span>
            <span class="detail-hero-badge"><i class="fas fa-route"></i> <?= $stopCnt ?> Stops</span>
            <span class="detail-hero-badge"><i class="fas fa-tag"></i> <?= e(formatPrice($itin['price'])) ?></span>
            <?php if ($ratingCount > 0): ?>
            <span class="detail-hero-badge rating" title="<?= e(number_format($ratingAvg, 1)) ?> out of 5">
                <span class="stars"><?= renderStars($ratingAvg) ?></span>
                <?= e(number_format($ratingAvg, 1)) ?> (<?= $ratingCount ?>)
            </span>
            <?php endif; ?>
            <a href="tel:+917810807552" class="call-button" aria-label="Call us at +91 78108 07552">
                <span class="call-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </span>
                <span class="call-content">
                    <small>Call Us</small>
                    <strong>+91 78108 07552</strong>
                </span>
            </a>
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
            <div class="stat-value" data-count="<?= $totalDays ?>"><?= $totalDays ?></div>
            <div class="stat-label">Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-value"><?= e(formatPrice($perDay)) ?></div>
            <div class="stat-label">Per Day</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" data-count="<?= $inclusionCnt ?>"><?= $inclusionCnt ?></div>
            <div class="stat-label">Inclusions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-map-pin"></i></div>
            <div class="stat-value" data-count="<?= $stopCnt ?>"><?= $stopCnt ?></div>
            <div class="stat-label">Destinations</div>
        </div>
    </div>
</section>

<!-- ============ MAIN CONTENT ============ -->
<section style="padding-bottom: 60px;">
    <div class="container detail-grid">

        <!-- ====== LEFT COLUMN ====== -->
        <div>

            <!-- Sticky section nav -->
            <nav class="section-nav" id="section-nav">
                <a href="#overview" class="active">Overview</a>
                <?php if (!empty($highlights)): ?><a href="#highlights">Highlights</a><?php endif; ?>
                <?php if (!empty($dayPlan)): ?><a href="#itinerary">Itinerary</a><?php endif; ?>
                <?php if (!empty($inclusions) || !empty($exclusions)): ?><a href="#inclusions">Inclusions</a><?php endif; ?>
            </nav>

            <!-- Overview -->
            <div class="info-box" id="overview" data-animate>
                <h2><i class="fas fa-info-circle"></i> Overview</h2>
                <p class="overview-text"><?= nl2br(e($itin['description'])) ?></p>
            </div>

            <!-- Highlights -->
            <?php if (!empty($highlights)): ?>
            <div class="info-box" id="highlights" data-animate>
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
            <div class="info-box" id="itinerary" data-animate style="padding-bottom: 12px;">
                <h2><i class="fas fa-route"></i> Day-wise Itinerary</h2>
            </div>

            <div class="day-toolbar">
                <button type="button" id="expand-all"><i class="fas fa-expand-alt"></i> Expand all</button>
                <button type="button" id="collapse-all"><i class="fas fa-compress-alt"></i> Collapse all</button>
            </div>

            <div class="day-timeline">
                <?php foreach ($dayPlan as $idx => $d): ?>
                <div class="day-card" data-animate data-delay="<?= $idx * 80 ?>">
                    <div class="day-card-header">
                        <span class="day-num"><?= (int)$d['day'] ?></span>
                        <span class="day-header-title"><?= e($d['title'] ?? 'Day ' . $d['day']) ?></span>
                        <i class="fas fa-chevron-down chevron"></i>
                    </div>
                    <div class="day-card-body">
                        <p class="day-desc"><?= e($d['desc'] ?? 'Explore and enjoy the destination at your pace.') ?></p>
                        <div class="day-meta">
                            <?php if (!empty($d['meals'])): ?>
                            <span class="day-meta-tag meals"><i class="fas fa-utensils"></i> <?= e($d['meals']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($d['hotel'])): ?>
                            <span class="day-meta-tag hotel"><i class="fas fa-bed"></i> <?= e($d['hotel']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Inclusions & Exclusions -->
            <?php if (!empty($inclusions) || !empty($exclusions)): ?>
            <div class="inc-exc-grid" id="inclusions">
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
                    <p>Contact us today and let's start your <?= e($itin['destination']) ?> adventure.</p>
                </div>
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

                    <?php if ($ratingCount > 0): ?>
                    <div class="rating-summary">
                        <span class="stars"><?= renderStars($ratingAvg) ?></span>
                        <span class="rating-num"><?= e(number_format($ratingAvg, 1)) ?></span>
                        <span class="rating-count">(<?= $ratingCount ?> review<?= $ratingCount === 1 ? '' : 's' ?>)</span>
                    </div>
                    <?php else: ?>
                    <div class="rating-empty"><i class="far fa-star"></i> No reviews yet</div>
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
                    <?php if ($ratingCount > 0): ?>
                    <div class="sidebar-detail-row">
                        <span class="sdr-label"><i class="fas fa-star"></i> Rating</span>
                        <span class="sdr-value"><?= e(number_format($ratingAvg, 1)) ?> / 5</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Traveler Calculator -->
                <div class="traveler-calc" id="calc" data-price="<?= (float)$itin['price'] ?>">
                    <label>Number of travelers</label>
                    <div class="stepper">
                        <button type="button" id="minus" aria-label="Fewer travelers"><i class="fas fa-minus"></i></button>
                        <span class="count" id="traveler-count">2</span>
                        <button type="button" id="plus" aria-label="More travelers"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="calc-total"><span>Estimated total</span><strong id="calc-total"></strong></div>
                </div>

                <!-- Action Buttons -->
                <div class="sidebar-actions">
                    <a href="#" id="wa-btn" class="btn-whatsapp" target="_blank" rel="noopener noreferrer"
                       data-title="<?= e($itin['title']) ?>">
                        <i class="fab fa-whatsapp"></i> Enquire on WhatsApp
                    </a>
                    <div class="action-row">
                        <button type="button" id="save-btn"><i class="far fa-heart"></i> Save</button>
                        <button type="button" id="share-btn"><i class="fas fa-share-alt"></i> Share</button>
                    </div>
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
                <h4><img class="footer-logo" src="logo.jpg" alt="Logo"><?= e(APP_NAME) ?></h4>
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
                <p style="margin-bottom:6px;"><i class="fas fa-envelope" style="width:20px;"></i> <a href="mailto:info@sktravelplanners.in">info@sktravelplanners.in</a></p>
                <p style="margin-bottom:6px;"><i class="fab fa-whatsapp" style="width:20px;"></i> <a href="https://wa.me/917810807552" target="_blank" rel="noopener noreferrer">+91 78108 07552</a></p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- ============ INTERACTIVITY ============ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $  = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => [...c.querySelectorAll(s)];
    const ITIN_ID = <?= (int)$itin['id'] ?>;

    /* ---------- Toast ---------- */
    const toast = $('#toast');
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2200);
    }

    /* ---------- Count-up animation ---------- */
    function countUp(el) {
        const target = parseInt(el.dataset.count, 10);
        if (!target) return;
        const start = performance.now(), dur = 900;
        (function tick(now) {
            const p = Math.min((now - start) / dur, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(tick);
        })(start);
    }

    /* ---------- Scroll reveal ---------- */
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const delay = parseInt(el.getAttribute('data-delay') || '0', 10);
            setTimeout(() => {
                el.classList.add('visible');
                const num = $('[data-count]', el);
                if (num) countUp(num);
            }, delay);
            observer.unobserve(el);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    $$('[data-animate]').forEach(el => observer.observe(el));
    $$('.stat-card').forEach((el, i) => {
        el.setAttribute('data-delay', i * 100);
        observer.observe(el);
    });

    /* ---------- Progress bar, back-to-top, parallax, scroll-spy ---------- */
    const bar = $('#scroll-progress'), topBtn = $('#back-top'), heroImg = $('.detail-hero img');
    const links = $$('#section-nav a');
    const sections = links.map(a => $(a.getAttribute('href')));

    function onScroll() {
        const y = window.pageYOffset;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.width = (max > 0 ? (y / max) * 100 : 0) + '%';
        topBtn.classList.toggle('show', y > 600);
        if (heroImg) heroImg.style.transform = 'translateY(' + (y * 0.25) + 'px) scale(1.05)';

        let current = 0;
        sections.forEach((s, i) => { if (s && s.getBoundingClientRect().top < 180) current = i; });
        links.forEach((a, i) => a.classList.toggle('active', i === current));
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    /* ---------- Collapsible day cards ---------- */
    $$('.day-card-header').forEach(h => {
        h.addEventListener('click', () => h.parentElement.classList.toggle('collapsed'));
    });
    const expandBtn = $('#expand-all'), collapseBtn = $('#collapse-all');
    if (expandBtn) expandBtn.addEventListener('click', () => $$('.day-card').forEach(c => c.classList.remove('collapsed')));
    if (collapseBtn) collapseBtn.addEventListener('click', () => $$('.day-card').forEach(c => c.classList.add('collapsed')));

    /* ---------- Traveler calculator + WhatsApp link ---------- */
    const calc = $('#calc'), countEl = $('#traveler-count'), totalEl = $('#calc-total'), wa = $('#wa-btn');
    const unitPrice = parseFloat(calc.dataset.price) || 0;
    let travelers = 2;

    function updateCalc() {
        countEl.textContent = travelers;
        totalEl.textContent = '₹' + Math.round(unitPrice * travelers).toLocaleString('en-IN');
        const msg = 'Hi! I am interested in "' + wa.dataset.title + '" for ' + travelers +
                    ' traveler(s). Estimated total: ' + totalEl.textContent + '. Please share details.';
        wa.href = 'https://wa.me/917810807552?text=' + encodeURIComponent(msg);
    }
    $('#minus').addEventListener('click', () => { if (travelers > 1)  { travelers--; updateCalc(); } });
    $('#plus').addEventListener('click',  () => { if (travelers < 20) { travelers++; updateCalc(); } });
    updateCalc();

    /* ---------- Save (wishlist) ---------- */
    const saveBtn = $('#save-btn'), key = 'sk_saved_' + ITIN_ID;
    function paintSaved(on) {
        saveBtn.classList.toggle('saved', on);
        saveBtn.innerHTML = on ? '<i class="fas fa-heart"></i> Saved' : '<i class="far fa-heart"></i> Save';
    }
    try { paintSaved(localStorage.getItem(key) === '1'); } catch (e) {}
    saveBtn.addEventListener('click', () => {
        const on = !saveBtn.classList.contains('saved');
        try { on ? localStorage.setItem(key, '1') : localStorage.removeItem(key); } catch (e) {}
        paintSaved(on);
        showToast(on ? 'Saved to your wishlist' : 'Removed from wishlist');
    });

    /* ---------- Share ---------- */
    $('#share-btn').addEventListener('click', async () => {
        const data = { title: document.title, url: location.href };
        try {
            if (navigator.share) { await navigator.share(data); }
            else { await navigator.clipboard.writeText(location.href); showToast('Link copied!'); }
        } catch (e) { /* user cancelled */ }
    });
});
</script>

</body>
</html>