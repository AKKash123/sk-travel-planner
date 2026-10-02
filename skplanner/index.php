<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* =========================================================
   DATA
========================================================= */

// Active itineraries
$stmt = $pdo->prepare("SELECT * FROM itineraries WHERE status = 1 ORDER BY created_at DESC");
$stmt->execute();
$itineraries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ratings per itinerary (page still works before the table exists)
$ratings = [];
try {
    $rs = $pdo->query(
        "SELECT itinerary_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM itinerary_reviews GROUP BY itinerary_id"
    );
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ratings[(int)$r['itinerary_id']] = ['avg' => (float)$r['avg_rating'], 'count' => (int)$r['total']];
    }
} catch (Throwable $e) {
    error_log('Ratings unavailable: ' . $e->getMessage());
}

// Approved user reviews + their photos
$bestReviews = [];
$bestReviewImages = [];
try {
    $brStmt = $pdo->prepare(
        "SELECT * FROM user_reviews WHERE status = 'approved'
         ORDER BY rating DESC, created_at DESC, id DESC LIMIT 15"
    );
    $brStmt->execute();
    $bestReviews = $brStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($bestReviews) {
        $brIds = array_column($bestReviews, 'id');
        $in    = implode(',', array_fill(0, count($brIds), '?'));
        $imgStmt = $pdo->prepare("SELECT * FROM review_images WHERE review_id IN ($in) ORDER BY id ASC");
        $imgStmt->execute($brIds);
        while ($row = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
            $bestReviewImages[$row['review_id']][] = $row['image'];
        }
    }
} catch (Throwable $e) {
    error_log('User reviews query failed: ' . $e->getMessage());
}

// Active travel gallery images & destinations
$galleryImages = [];
$galleryDestinations = [];
try {
    $galStmt = $pdo->prepare("SELECT * FROM gallery_images WHERE status = 1 ORDER BY sort_order ASC, created_at DESC");
    $galStmt->execute();
    $galleryImages = $galStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($galleryImages as $gImg) {
        $gDest = trim((string)($gImg['destination'] ?? ''));
        if ($gDest !== '') {
            $destKey = mb_strtolower($gDest);
            $galleryDestinations[$destKey] ??= ['label' => $gDest, 'count' => 0];
            $galleryDestinations[$destKey]['count']++;
        }
    }
    uasort($galleryDestinations, static fn($a, $b) => $b['count'] <=> $a['count']);
} catch (Throwable $e) {
    error_log('Gallery query failed: ' . $e->getMessage());
}

// Primary destinations ("Darjeeling, Sikkim" -> "Darjeeling")
$destCounts = [];
foreach ($itineraries as $it) {
    $primary = trim(explode(',', (string)$it['destination'])[0]);
    if ($primary === '') continue;
    $key = mb_strtolower($primary);
    $destCounts[$key] ??= ['label' => $primary, 'count' => 0];
    $destCounts[$key]['count']++;
}
uasort($destCounts, static fn($a, $b) => $b['count'] <=> $a['count']);
$primaryDestinations = array_slice($destCounts, 0, 8, true);

// Best sellers (rating-weighted; add real bookings to $sales if you have them)
$sales = [];
$bestSellers = $itineraries;
usort($bestSellers, static function ($a, $b) use ($ratings, $sales) {
    $score = static fn($i) => ($sales[(int)$i['id']] ?? 0) * 100
        + ($ratings[(int)$i['id']]['count'] ?? 0) * ($ratings[(int)$i['id']]['avg'] ?? 0);
    return $score($b) <=> $score($a) ?: strcmp((string)$b['created_at'], (string)$a['created_at']);
});
$bestSellers = array_slice($bestSellers, 0, 8);

// Overall rating for the trust line
$bsReviewTotal = 0; $bsWeighted = 0.0;
foreach ($ratings as $r) { $bsReviewTotal += $r['count']; $bsWeighted += $r['avg'] * $r['count']; }
$bsOverallAvg = $bsReviewTotal ? $bsWeighted / $bsReviewTotal : 0;

/* =========================================================
   HERO SLIDES (Wikimedia Commons; drop a file in assets/hero/<slug>.jpg to override)
========================================================= */
$heroSlides = [
    ['darjeeling-pine-forest', 'Pine Forests of Darjeeling', 'Darjeeling', '#2f4a3a', 'Pine trees Darjeeling.jpg'],
    ['yumthang-valley', 'Yumthang Valley of Flowers', 'North Sikkim', '#4a6a4a', 'Yumthang Valley at North Sikkim, India 01.jpg'],
    ['ghum-toy-train', 'Toy Train at Ghum Station', 'Ghum', '#3b4a5a', 'A train of Darjeeling Himalayan Railway at Ghoom Station.jpg'],
    ['kolakham-view', 'Kolakham Viewpoint', 'Kalimpong', '#3f6b55', 'Kolakham view.jpg'],
    ['north-sikkim-zero-point', 'Zero Point, North Sikkim', 'North Sikkim', '#5a6f85', 'Zero Point, Sikkim.jpg'],
    ['kurseong-tea-estates', 'Kurseong Tea Estates', 'Kurseong', '#3f6b45', 'Tea estate in kurseong.jpg'],
    ['dow-hill', 'Dow Hill Forest', 'Kurseong', '#2f4a35', 'Dow hill Kurseong.jpg'],
    ['kanchenjunga-view', 'Kanchenjunga Panorama', 'Darjeeling', '#5a6a80', 'Darjeeling-panoramic.jpg'],
    ['mirik-pine-road', 'Pine Road to Mirik', 'Mirik', '#2f4f3a', 'A Route through Pine Forest.jpg'],
    ['sumendu-lake', 'Sumendu Lake', 'Mirik', '#2d6f86', 'Sumendu Lake, Mirik.jpg'],
    ['dooars-tea-garden', 'Tea Gardens of the Dooars', 'Dooars', '#3a5a30', 'Tea garden in dooars.jpg'],
    ['tawang-monastery', 'Tawang Monastery', 'Arunachal Pradesh', '#6a4a2f', 'Tawang Monastery (Tibetan Buddhist).jpg'],
    ['ziro-valley', 'Ziro Valley Paddy Fields', 'Arunachal Pradesh', '#4a7a3a', 'Ziro Valley.jpg'],
    ['sela-pass', 'Sela Pass', 'Arunachal Pradesh', '#4a6a85', 'Sela Pass, Arunachal Pradesh.jpg'],
    ['kaziranga-rhino', 'One-Horned Rhino, Kaziranga', 'Assam', '#5a6a3a', 'One-Horned Rhino at the Kaziranga National Park, Assam.jpg'],
    ['majuli-island', 'Majuli River Island', 'Assam', '#3a6a70', 'Majuli Island.jpg'],
    ['assam-tea-garden', 'Tea Gardens of Assam', 'Assam', '#3f6b30', 'Tea Garden at Indo-Bhutan Border at Darranga, Assam.jpg'],
    ['palolem-beach', 'Palolem Beach', 'Goa', '#2d7a8a', 'Palolem Beach.jpg'],
    ['basilica-bom-jesus', 'Basilica of Bom Jesus', 'Goa', '#8a5a3a', 'Basilica of Bom Jesus.jpg'],
    ['dudhsagar-falls', 'Dudhsagar Falls', 'Goa', '#3a5a4a', 'Dudhsagar Falls.jpg'],
];
$heroSlides = array_map(static function (array $s): array {
    $slide = ['slug' => $s[0], 'title' => $s[1], 'state' => $s[2], 'tint' => $s[3], 'wiki' => $s[4], 'url' => null];
    foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
        if (is_file(__DIR__ . "/assets/hero/{$s[0]}.{$ext}")) { $slide['url'] = "assets/hero/{$s[0]}.{$ext}"; break; }
    }
    return $slide;
}, $heroSlides);

$heroSrc = static function (array $s, int $w): string {
    return !empty($s['url'])
        ? $s['url']
        : 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $s['wiki'])) . '?width=' . $w;
};
$heroSrcset = static function (array $s) use ($heroSrc): string {
    if (!empty($s['url'])) return '';
    return implode(', ', array_map(static fn($w) => $heroSrc($s, $w) . ' ' . $w . 'w', [640, 1024, 1600]));
};

/* =========================================================
   SETTINGS
========================================================= */
$siteUrl        = 'https://sktravelplanners.com';
$whatsappNumber = '917810807552';
$whatsappMsg    = rawurlencode("Hello! I found your travel packages on " . APP_NAME . ". I would like to know more about your itineraries.");
$pageSize       = 3;   // cards shown first and added on every "Load more"

$faqs = [
    ['How do I book a tour with SK Travel Planners?',
     'Browse our itineraries and click "View Details", or reach us by WhatsApp, phone or email. The process is simple: 1. Consult (share dates and preferences), 2. Customize (we design and quote), 3. Confirm (secure with a deposit), 4. Prepare (we handle the logistics).'],
    ['Are the tour prices per person or per group?',
     'Listed prices are per person, typically based on double/twin occupancy. Solo travellers pay a single supplement. Private groups can request a flat group rate.'],
    ["What's included in the tour price?",
     'Standard tours include accommodation, private air-conditioned transport with a driver, local expert guides, daily breakfast and the listed entrance fees. International flights, visa fees and personal expenses are generally not included.'],
    ['Can I customize a tour to my preferences?',
     'Yes. You can adjust the duration, upgrade hotels, add activities or change the route. Tell us what you have in mind and we will build it.'],
    ['What is the cancellation policy?',
     'From the date of departure: 60+ days prior, deposit refunded minus an admin fee; 30–59 days, 50% non-refundable; 0–29 days, 100% non-refundable. Peak season bookings may have stricter terms. We recommend travel insurance.'],
    ['Do you provide visa assistance?',
     'We supply supporting documents (hotel vouchers, itinerary) and guide you step by step through the Indian e-Visa process. We do not process visas directly.'],
    ['Are India tours safe for solo female travellers?',
     'Yes. We use vetted private transport, centrally located hotels, trained guides and 24/7 on-ground support. Female guides can be requested in certain cities.'],
    ['What is the best time to visit India?',
     'October to March suits most of the country. April to June is ideal for the Himalayas. July to September is the monsoon, great for lush landscapes and Ayurvedic treatments in Kerala.'],
];

$chatPackages = array_map(static fn($i) => [
    'id'          => (int)$i['id'],
    'title'       => $i['title'],
    'destination' => $i['destination'],
    'price'       => (float)$i['price'],
    'priceLabel'  => formatPrice($i['price']),
    'days'        => (int)$i['duration_days'],
    'url'         => 'detail.php?id=' . (int)$i['id'],
], $itineraries);

$appConfig = [
    'name' => APP_NAME, 'whatsapp' => $whatsappNumber,
    'pageSize' => $pageSize, 'packages' => $chatPackages,
];

$faqSchema = [
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn($f) => [
        '@type' => 'Question', 'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
];
$agencySchema = [
    '@context' => 'https://schema.org', '@type' => 'TravelAgency',
    'name' => 'SK Travel Planners', 'alternateName' => 'SKTravel', 'url' => $siteUrl,
    'logo' => $siteUrl . '/logo.jpg',
    'description' => 'Tour and travel agency offering custom itineraries, holiday packages, honeymoon packages and adventure tours across North Bengal, Sikkim, Darjeeling, Bhutan and more.',
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Alipurduar', 'addressRegion' => 'West Bengal', 'addressCountry' => 'IN'],
    'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 26.489, 'longitude' => 89.527],
    'telephone' => '+91-7810807552', 'priceRange' => '₹₹',
    'areaServed' => ['North Bengal', 'Sikkim', 'Darjeeling', 'Kalimpong', 'Gangtok', 'Bhutan', 'Nepal', 'North East India'],
    'sameAs' => ['https://wa.me/' . $whatsappNumber],
];
$breadcrumbSchema = [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $siteUrl . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Itineraries', 'item' => $siteUrl . '/#itineraries'],
    ],
];
$ld = static fn(array $d): string => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
$pageTitle = APP_NAME . ' | Tour Packages & Custom Itineraries';
$pageDesc  = 'Plan your trip with SK Travel Planners: expert-curated day-wise itineraries, handpicked stays and local experiences across North Bengal, Sikkim, Darjeeling, Bhutan and more. Chat on WhatsApp to book.';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>

    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="keywords" content="SK Travel Planners, tour packages, custom itineraries, holiday packages, honeymoon packages, North Bengal tours, Darjeeling tour package, Sikkim tour package, Kalimpong, Gangtok, Siliguri travel agency, Bhutan tour package, Nepal travel package, North East India tours, travel agency in Alipurduar">
    <meta name="author" content="SK Travel Planners">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <link rel="canonical" href="<?= e($siteUrl) ?>/">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SK Travel Planners">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:url" content="<?= e($siteUrl) ?>/">
    <meta property="og:image" content="<?= e($siteUrl) ?>/assets/og-image.jpg">
    <meta property="og:image:alt" content="SK Travel Planners: curated travel itineraries">
    <meta property="og:locale" content="en_IN">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <meta name="twitter:image" content="<?= e($siteUrl) ?>/assets/og-image.jpg">

    <meta name="theme-color" content="#2d1f3d">
    <meta name="format-detection" content="telephone=no">
    <meta name="geo.region" content="IN-WB">
    <meta name="geo.placename" content="Alipurduar">
    <meta name="geo.position" content="26.489;89.527">
    <meta name="ICBM" content="26.489, 89.527">

    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="icon" href="assets/icons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">

    <script type="application/ld+json"><?= $ld($agencySchema) ?></script>
    <script type="application/ld+json"><?= $ld($breadcrumbSchema) ?></script>
    <script type="application/ld+json"><?= $ld($faqSchema) ?></script>

    <script>document.documentElement.classList.add('js');</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="extra.css">
    <link rel="stylesheet" href="carousel-extras.css">

    <?php if ($heroSlides): ?>
    <link rel="preload" as="image" href="<?= e($heroSrc($heroSlides[0], 1600)) ?>"
          <?php if ($heroSrcset($heroSlides[0])): ?>imagesrcset="<?= e($heroSrcset($heroSlides[0])) ?>" imagesizes="100vw"<?php endif; ?>
          fetchpriority="high">
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>

    <style>
    :root{--ink:#2d1f3d;--accent-orange:#e8590c}

    /* ---------- Filter section (inherits the page/body background) ---------- */
    .sf-section{position:relative;padding:56px 0 48px;background:transparent;color:#2d1f3d}
    .sf-inner{position:relative;z-index:1;max-width:900px;margin:0 auto;text-align:center}
    .sf-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:999px;
      background:rgba(232,89,12,.1);color:#e8590c;font-size:.8rem;font-weight:700;letter-spacing:.08em}
    .sf-heading{margin:16px 0 8px;font-family:'Playfair Display',serif;font-size:clamp(1.8rem,4vw,2.8rem);line-height:1.15;color:#2d1f3d}
    .sf-sub{margin:0 auto 28px;max-width:520px;color:#2d1f3d;opacity:.72;font-size:1.02rem;line-height:1.6}

    .sf-card{position:relative;text-align:left;color:#2d1f3d;border-radius:22px;padding:22px 24px 20px;
      background:rgba(255,255,255,.55);border:1px solid rgba(45,31,61,.1);
      -webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);
      box-shadow:0 14px 40px rgba(45,31,61,.1);animation:sfRise .7s cubic-bezier(.16,1,.3,1) both}
    @keyframes sfRise{from{opacity:0;transform:translateY(24px) scale(.98)}to{opacity:1;transform:none}}

    .sf-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}
    .sf-title{display:flex;align-items:center;gap:10px;font-weight:700;font-size:1.02rem;color:#2d1f3d}
    .sf-title i{display:grid;place-items:center;width:36px;height:36px;border-radius:11px;color:#fff;font-size:.9rem;
      background:linear-gradient(135deg,#e8590c,#f08a3c);box-shadow:0 6px 14px rgba(232,89,12,.35)}
    .sf-count{font-size:.85rem;color:#6b6478;background:rgba(45,31,61,.06);padding:6px 14px;border-radius:999px}
    .sf-count strong{color:#2d1f3d}
    .sf-row{display:flex;gap:12px;align-items:stretch}
    .sf-search{position:relative;flex:1}
    .sf-search i{position:absolute;left:18px;top:50%;transform:translateY(-50%);color:#e8590c;pointer-events:none;transition:transform .25s}
    .sf-search input{width:100%;height:54px;padding:0 18px 0 48px;border:1.5px solid rgba(45,31,61,.15);border-radius:14px;
      font:inherit;font-size:1rem;color:#2d1f3d;background:rgba(255,255,255,.8);transition:border-color .25s,box-shadow .25s,background .25s}
    .sf-search input::placeholder{color:#8a8396}
    .sf-search input:focus{outline:none;border-color:#e8590c;background:#fff;box-shadow:0 0 0 4px rgba(232,89,12,.15)}
    .sf-search:focus-within i{transform:translateY(-50%) scale(1.15)}
    .sf-reset{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 24px;height:54px;border-radius:14px;
      border:1.5px solid rgba(45,31,61,.2);color:#2d1f3d;font:inherit;font-weight:600;cursor:pointer;background:rgba(255,255,255,.8);
      transition:transform .2s,background .2s,color .2s}
    .sf-reset i{transition:transform .5s}
    .sf-reset:hover{transform:translateY(-2px);background:#2d1f3d;color:#fff}
    .sf-reset:hover i{transform:rotate(-360deg)}
    .sf-chips{display:flex;gap:9px;overflow-x:auto;margin-top:16px;padding:4px 2px 6px;scrollbar-width:none}
    .sf-chips::-webkit-scrollbar{display:none}
    .sf-chip{flex:0 0 auto;border:1.5px solid rgba(45,31,61,.15);background:rgba(255,255,255,.7);color:#2d1f3d;border-radius:999px;
      padding:8px 16px;font:inherit;font-size:.88rem;font-weight:500;cursor:pointer;white-space:nowrap;
      transition:transform .2s,border-color .2s,background .2s,box-shadow .2s}
    .sf-chip em{font-style:normal;font-size:.72rem;margin-left:7px;padding:2px 7px;border-radius:999px;background:rgba(45,31,61,.08);color:#6b6478}
    .sf-chip:hover{transform:translateY(-2px);border-color:#e8590c;box-shadow:0 8px 16px rgba(232,89,12,.14)}
    .sf-chip.is-active{background:linear-gradient(135deg,#e8590c,#f08a3c);border-color:transparent;color:#fff;box-shadow:0 8px 18px rgba(232,89,12,.35)}
    .sf-chip.is-active em{background:rgba(255,255,255,.25);color:#fff}
    .sf-chip:focus-visible,.sf-reset:focus-visible{outline:3px solid rgba(232,89,12,.5);outline-offset:2px}
    .sf-card .active-filters:empty{display:none}
    .sf-card .active-filters{margin-top:12px}
    @media(max-width:600px){
      .sf-section{padding:40px 0 36px}
      .sf-card{padding:18px 16px;border-radius:18px}
      .sf-row{flex-direction:column}
      .sf-search input,.sf-reset{height:50px}
    }
    @media(prefers-reduced-motion:reduce){.sf-card{animation:none}}

    
/* ---------- Shared headings / carousels ---------- */
    .bs-heading{text-align:center;max-width:760px;margin:0 auto 38px;position:relative}
    .bs-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:999px;
      background:rgba(232,89,12,.1);color:var(--accent-orange);font-size:.78rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
    .bs-heading h2{margin:14px 0 10px;font-size:clamp(1.8rem,3.6vw,2.6rem);line-height:1.15;color:var(--ink)}
    .bs-heading h2 span{color:var(--accent-orange)}
    .bs-divider{display:flex;align-items:center;justify-content:center;gap:12px;margin:0 auto 14px;color:var(--accent-orange)}
    .bs-divider i:not(.fas){display:block;width:56px;height:2px;background:linear-gradient(90deg,transparent,var(--accent-orange))}
    .bs-divider i:last-child{background:linear-gradient(270deg,transparent,var(--accent-orange))}
    .bs-heading p{margin:0 auto;max-width:600px;font-size:1.05rem;line-height:1.65;opacity:.75}
    .bs-trust{list-style:none;display:flex;flex-wrap:wrap;justify-content:center;gap:10px 26px;margin:22px 0 0;padding:14px 0 0;border-top:1px solid rgba(0,0,0,.08);font-size:.9rem}
    .bs-trust li{display:flex;align-items:center;gap:8px}
    .bs-trust i{color:var(--accent-orange)}
    .bs-trust b{color:var(--ink)}
    .bs-viewall{display:inline-flex;align-items:center;gap:8px;margin-top:18px;font-weight:600;font-size:.92rem;color:var(--ink);text-decoration:none;border-bottom:2px solid var(--accent-orange);padding-bottom:2px}

    .car{position:relative}
    .car-track{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:6px 2px 14px;scrollbar-width:none}
    .car-track::-webkit-scrollbar{display:none}
    .car-arrow{position:absolute;top:42%;transform:translateY(-50%);z-index:2;width:42px;height:42px;border-radius:50%;
      border:0;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer;color:var(--ink)}
    .car-prev{left:-14px}.car-next{right:-14px}
    .car-dots{display:flex;justify-content:center;gap:8px;margin-top:8px}
    .car-dots button{width:9px;height:9px;border-radius:50%;border:0;background:#c9c9c9;padding:0;cursor:pointer;transition:.25s}
    .car-dots button.on{background:var(--ink);width:24px;border-radius:6px}

    .bs-card{flex:0 0 calc((100% - 40px)/3);scroll-snap-align:start;background:#fff;border-radius:14px;overflow:hidden;
      box-shadow:0 6px 20px rgba(0,0,0,.09);display:flex;flex-direction:column}
    .bs-img{position:relative;aspect-ratio:16/10;background:#ddd}
    .bs-img img{width:100%;height:100%;object-fit:cover;display:block}
    .bs-badge{position:absolute;top:12px;left:12px;background:var(--accent-orange);color:#fff;font-size:.75rem;font-weight:600;padding:5px 10px;border-radius:999px}
    .bs-days{position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.65);color:#fff;font-size:.75rem;padding:4px 10px;border-radius:999px}
    .bs-body{padding:16px;display:flex;flex-direction:column;gap:6px;flex:1}
    .bs-body h3{font-size:1.1rem;margin:0}
    .bs-dest,.bs-rate{font-size:.88rem;opacity:.8}
    .bs-rate i{color:#f5a623}
    .bs-foot{margin-top:auto;display:flex;justify-content:space-between;align-items:center;padding-top:10px}
    .bs-price{font-weight:700;font-size:1.1rem}
    @media(max-width:900px){.bs-card{flex-basis:calc((100% - 20px)/2)}}
    @media(max-width:600px){.bs-card{flex-basis:88%}.car-arrow{display:none}}

    /* ---------- Reviews ---------- */
    .reviews-section{padding:70px 0;background:linear-gradient(180deg,#FFF9F2,#FDF3E5)}
    .rv-card{flex:0 0 calc((100% - 40px)/3);scroll-snap-align:start;background:#fff;border-radius:20px;padding:28px 24px;
      box-shadow:0 10px 30px rgba(0,0,0,.05);border:1px solid rgba(217,108,63,.12);display:flex;flex-direction:column;position:relative}
    .rv-quote{position:absolute;top:18px;right:20px;font-size:30px;color:rgba(217,108,63,.1)}
    .rv-stars{color:#f59e0b;font-size:16px;display:flex;align-items:center;gap:2px;margin-bottom:12px}
    .rv-pill{font-size:12px;font-weight:700;margin-left:8px;background:#fef3c7;color:#b45309;padding:2px 8px;border-radius:12px}
    .rv-card h4{font-family:'Playfair Display',serif;font-size:18px;margin:0 0 10px;color:var(--ink)}
    .rv-text{font-size:14.5px;line-height:1.65;color:#475569;font-style:italic;margin:0 0 16px}
    .rv-photos{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
    .rv-photos button{width:60px;height:60px;border-radius:10px;overflow:hidden;border:2px solid #fed7aa;padding:0;cursor:pointer;background:none}
    .rv-photos img{width:100%;height:100%;object-fit:cover;display:block}
    .rv-foot{border-top:1px solid #f1f5f9;padding-top:14px;display:flex;align-items:center;gap:12px;margin-top:auto}
    .rv-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#d96c3f,#f4b942);color:#fff;
      display:grid;place-items:center;font-weight:700;text-transform:uppercase;flex-shrink:0}
    .rv-name{font-weight:700;color:var(--ink);font-size:15px}
    .rv-sub{font-size:12px;color:#64748b;display:flex;gap:6px;flex-wrap:wrap}
    .rv-ok{color:#059669;font-weight:600}
    .rv-empty{background:#fff;border-radius:16px;padding:40px;text-align:center;max-width:600px;margin:0 auto 30px;box-shadow:0 4px 20px rgba(0,0,0,.05)}
    @media(max-width:900px){.rv-card{flex-basis:calc((100% - 20px)/2)}}
    @media(max-width:600px){.rv-card{flex-basis:90%}}

    /* ---------- Modal + lightbox ---------- */
    .ov{display:none;position:fixed;inset:0;background:rgba(27,40,56,.75);backdrop-filter:blur(5px);z-index:999999;
      align-items:center;justify-content:center;padding:16px}
    .ov.open{display:flex}
    .md{background:#fff;width:100%;max-width:540px;border-radius:20px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);animation:pop .3s ease}
    @keyframes pop{from{opacity:0;transform:scale(.94) translateY(12px)}to{opacity:1;transform:none}}
    .md-head{background:linear-gradient(135deg,#2d1f3d,#4a3563);color:#fff;padding:20px 26px;display:flex;justify-content:space-between;align-items:center}
    .md-head h3{margin:0;font-family:'Playfair Display',serif;font-size:21px;color:#fff}
    .md-head p{margin:4px 0 0;font-size:13px;opacity:.75}
    .md-x{background:rgba(255,255,255,.14);border:0;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer}
    .md-body{padding:24px 26px;max-height:calc(90vh - 110px);overflow-y:auto}
    .fld{margin-bottom:14px}
    .fld label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--ink)}
    .fld input,.fld textarea{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font:inherit;font-size:14px;box-sizing:border-box}
    .fld input:focus,.fld textarea:focus{outline:none;border-color:var(--accent-orange)}
    .fld small{font-size:12px;color:#64748b}
    .two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .stars-pick{text-align:center;margin-bottom:18px;padding:14px;background:#fffbf5;border-radius:12px;border:1px solid #fed7aa}
    .stars-pick .m-star{font-size:28px;cursor:pointer;color:#fbbf24;margin:0 3px;transition:transform .15s}
    .stars-pick .m-star:hover{transform:scale(1.2)}
    #modalRatingText{font-size:13px;font-weight:700;color:#d97706;margin-top:6px}
    .drop{border:2px dashed #fed7aa;border-radius:12px;padding:16px;text-align:center;background:#fffcf9;cursor:pointer;font-size:13px}
    .drop:hover{border-color:var(--accent-orange);background:#fff7ed}
    .thumbs{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .thumb{position:relative;width:64px;height:64px;border-radius:8px;overflow:hidden;border:2px solid #fed7aa}
    .thumb img{width:100%;height:100%;object-fit:cover}
    .thumb button{position:absolute;top:2px;right:2px;width:18px;height:18px;border:0;border-radius:50%;background:rgba(239,68,68,.92);color:#fff;font-size:10px;cursor:pointer;padding:0}
    .alert{display:none;padding:12px 16px;border-radius:8px;font-size:14px;margin-bottom:16px;background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444}
    .actions{display:flex;justify-content:flex-end;gap:12px}
    .btn-ghost{padding:10px 18px;border:1.5px solid #cbd5e1;background:#fff;color:#475569;border-radius:8px;font-weight:600;cursor:pointer}
    #publicReviewLightbox{background:rgba(0,0,0,.85);z-index:9999999;cursor:zoom-out}
    #publicReviewLightbox img{max-width:90vw;max-height:85vh;border-radius:12px;object-fit:contain}
    @media(max-width:640px){.two{grid-template-columns:1fr}.fld input,.fld textarea{font-size:16px}}

    /* ---------- Travel Gallery Section ---------- */
    .gallery-section{padding:70px 0;background:linear-gradient(180deg,#FDF8F3 0%,#F6EFE6 100%)}
    .gal-filter-chips{display:flex;gap:9px;justify-content:center;flex-wrap:wrap;margin:0 auto 32px;max-width:920px;padding:4px}
    .gal-chip{border:1.5px solid rgba(45,31,61,.15);background:rgba(255,255,255,.8);color:#2d1f3d;border-radius:999px;
      padding:8px 18px;font:inherit;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .25s ease;display:inline-flex;align-items:center;gap:7px}
    .gal-chip em{font-style:normal;font-size:.74rem;padding:2px 7px;border-radius:999px;background:rgba(45,31,61,.08);color:#6b6478}
    .gal-chip:hover{transform:translateY(-2px);border-color:#e8590c;box-shadow:0 8px 16px rgba(232,89,12,.15)}
    .gal-chip.is-active{background:linear-gradient(135deg,#e8590c,#f08a3c);border-color:transparent;color:#fff;box-shadow:0 8px 20px rgba(232,89,12,.35)}
    .gal-chip.is-active em{background:rgba(255,255,255,.25);color:#fff}

    .gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px;margin-bottom:28px}
    .gal-item{position:relative;border-radius:18px;overflow:hidden;background:#2d1f3d;aspect-ratio:4/3;cursor:pointer;
      box-shadow:0 10px 28px rgba(45,31,61,.08);transition:transform .35s cubic-bezier(.16,1,.3,1),box-shadow .35s ease;display:block}
    .gal-item:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(45,31,61,.2)}
    .gal-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .6s cubic-bezier(.16,1,.3,1),filter .4s ease}
    .gal-item:hover img{transform:scale(1.08);filter:brightness(.9)}

    .gal-badge{position:absolute;top:14px;left:14px;background:rgba(45,31,61,.78);color:#fff;
      padding:4px 12px;border-radius:999px;font-size:.76rem;font-weight:600;backdrop-filter:blur(6px);z-index:2;display:inline-flex;align-items:center;gap:6px}
    .gal-badge i{color:var(--accent-orange)}

    .gal-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 35%,rgba(45,31,61,.92) 100%);
      display:flex;flex-direction:column;justify-content:flex-end;padding:18px 20px;color:#fff;opacity:0;transition:opacity .3s ease;z-index:3}
    .gal-item:hover .gal-overlay{opacity:1}
    .gal-title{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:#fff;margin:0 0 3px;line-height:1.25}
    .gal-caption{font-size:.82rem;color:rgba(255,255,255,.82);margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4}
    .gal-zoom-icon{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.2);
      backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem}

    .gal-empty{background:#fff;border-radius:18px;padding:48px 20px;text-align:center;max-width:540px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,.05)}
    .gal-empty i{font-size:48px;color:var(--accent-orange);margin-bottom:12px}

    /* Interactive Lightbox Modal */
    .gal-modal{display:none;position:fixed;inset:0;background:rgba(18,12,24,.95);z-index:9999999;
      backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;user-select:none}
    .gal-modal.open{display:flex;animation:pop .25s ease}
    .gal-modal-inner{position:relative;max-width:1100px;width:100%;max-height:92vh;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .gal-modal-img-wrap{position:relative;max-width:100%;max-height:72vh;display:flex;align-items:center;justify-content:center}
    .gal-modal-img{max-width:100%;max-height:72vh;border-radius:12px;object-fit:contain;box-shadow:0 25px 60px rgba(0,0,0,.6)}
    
    .gal-modal-nav{position:absolute;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;
      background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:1.1rem;
      cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s ease;z-index:10}
    .gal-modal-nav:hover{background:var(--accent-orange);border-color:var(--accent-orange);transform:translateY(-50%) scale(1.08)}
    .gal-modal-prev{left:-24px}
    .gal-modal-next{right:-24px}
    
    .gal-modal-bar{width:100%;max-width:780px;margin-top:14px;background:rgba(255,255,255,.1);
      border:1px solid rgba(255,255,255,.15);border-radius:14px;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;color:#fff;backdrop-filter:blur(12px)}
    .gal-modal-bar-left{flex:1;text-align:left;padding-right:16px}
    .gal-modal-bar-title{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:#fff;margin:0 0 2px}
    .gal-modal-bar-desc{font-size:.85rem;color:rgba(255,255,255,.75);margin:0}
    .gal-modal-count{font-size:.85rem;font-weight:700;color:var(--accent-orange);background:rgba(232,89,12,.18);padding:4px 12px;border-radius:999px;white-space:nowrap}
    .gal-modal-close{position:absolute;top:-46px;right:0;background:rgba(255,255,255,.14);border:0;color:#fff;
      width:38px;height:38px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;transition:background .2s}
    .gal-modal-close:hover{background:var(--accent-orange)}

    @media(max-width:768px){
      .gal-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
      .gal-modal-prev{left:6px}
      .gal-modal-next{right:6px}
      .gal-modal-close{top:6px;right:6px}
      .gal-modal-nav{width:40px;height:40px;font-size:.95rem}
    }

    /* ============================================================
       UPGRADED HEADER NAVBAR STYLES
       ============================================================ */
    .navbar {
        background: linear-gradient(135deg, #1b1325 0%, #29213a 50%, #3e263d 100%) !important;
        position: sticky; top: 0; z-index: 1000;
        box-shadow: 0 4px 20px rgba(0,0,0,0.22);
        border-bottom: 1px solid rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        padding: 0 !important;
    }
    .navbar-inner {
        display: flex; align-items: center; justify-content: space-between;
        height: 72px; gap: 16px;
    }
    .navbar-brand {
        display: inline-flex; align-items: center; gap: 12px;
        font-family: 'Playfair Display', serif; font-size: 1.35rem; font-weight: 700;
        color: #fff !important; text-decoration: none; padding-left: 0 !important; flex-shrink: 0;
        transition: transform .2s ease, color .2s ease;
    }
    .navbar-brand:hover { color: var(--accent-orange, #e8590c) !important; transform: translateY(-1px); }
    .navbar-brand .brand-icon {
        width: 42px; height: 42px; border-radius: 12px; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--accent, #f4b942), var(--primary, #d96c3f));
        box-shadow: 0 4px 12px rgba(232,89,12,0.35); flex-shrink: 0;
    }
    .navbar-brand .brand-icon img { width: 100%; height: 100%; object-fit: cover; }

    .navbar-collapse {
        display: flex; align-items: center; justify-content: space-between;
        flex: 1; margin-left: 20px;
    }
    .navbar-nav {
        display: flex; align-items: center; gap: 6px; list-style: none; margin: 0; padding: 0;
    }
    .navbar-nav li { margin: 0; }
    .navbar-nav .nav-link {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 15px; border-radius: 999px;
        color: rgba(255, 255, 255, 0.85) !important; font-size: 0.92rem; font-weight: 600;
        text-decoration: none; transition: all 0.25s ease;
        position: relative; box-shadow: none !important;
    }
    .navbar-nav .nav-link i {
        font-size: 0.88rem; color: rgba(255, 255, 255, 0.65);
        transition: color 0.25s ease, transform 0.25s ease;
    }
    .navbar-nav .nav-link:hover {
        color: #fff !important; background: rgba(255, 255, 255, 0.12) !important;
        transform: translateY(-1px);
    }
    .navbar-nav .nav-link:hover i {
        color: var(--accent-orange, #e8590c) !important; transform: scale(1.1);
    }
    .navbar-nav .nav-link.active {
        color: #fff !important; background: linear-gradient(135deg, rgba(232,89,12,0.25), rgba(240,138,60,0.18)) !important;
        border: 1px solid rgba(232,89,12,0.45);
        box-shadow: 0 4px 14px rgba(232,89,12,0.25) !important;
    }
    .navbar-nav .nav-link.active i {
        color: var(--accent-orange, #e8590c) !important;
    }

    .navbar-actions {
        display: flex; align-items: center; gap: 10px; flex-shrink: 0;
    }
    .nav-btn-admin {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 16px; border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        border: 1.5px solid rgba(255, 255, 255, 0.18);
        color: rgba(255, 255, 255, 0.9) !important; font-size: 0.85rem; font-weight: 600;
        text-decoration: none; transition: all 0.25s ease;
    }
    .nav-btn-admin:hover {
        color: #fff !important; background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.4); transform: translateY(-1px);
    }
    .nav-btn-admin i { color: #f4b942; }

    .nav-btn-cta {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 18px; border-radius: 999px;
        background: linear-gradient(135deg, #e8590c 0%, #f08a3c 100%);
        color: #fff !important; font-size: 0.86rem; font-weight: 700;
        text-decoration: none; box-shadow: 0 4px 16px rgba(232,89,12,0.4);
        transition: all 0.25s ease;
    }
    .nav-btn-cta:hover {
        background: linear-gradient(135deg, #cf4c05 0%, #e8590c 100%);
        transform: translateY(-2px); box-shadow: 0 6px 20px rgba(232,89,12,0.55);
        color: #fff !important;
    }
    .nav-btn-cta i { font-size: 1.05rem; }

    /* Mobile Toggler */
    .navbar-toggler {
        display: none; flex-direction: column; justify-content: center; align-items: center;
        width: 42px; height: 42px; border-radius: 10px;
        background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);
        cursor: pointer; padding: 0; gap: 5px; transition: background 0.2s ease;
    }
    .navbar-toggler:hover { background: rgba(255, 255, 255, 0.16); }
    .toggler-bar {
        width: 20px; height: 2px; background: #fff; border-radius: 2px;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .navbar-toggler.is-open .toggler-bar:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }
    .navbar-toggler.is-open .toggler-bar:nth-child(2) {
        opacity: 0; transform: scaleX(0);
    }
    .navbar-toggler.is-open .toggler-bar:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    @media(max-width: 992px) {
        .navbar-toggler { display: flex; }
        .navbar-collapse {
            position: absolute; top: 100%; left: 0; right: 0;
            background: linear-gradient(180deg, rgba(27,19,37,0.98) 0%, rgba(41,33,58,0.98) 100%);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
            flex-direction: column; align-items: stretch;
            padding: 0 24px; margin: 0;
            max-height: 0; opacity: 0; overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease, padding 0.3s ease;
            pointer-events: none;
        }
        .navbar-collapse.is-open {
            max-height: 520px; opacity: 1; padding: 18px 24px 24px; pointer-events: auto;
        }
        .navbar-nav {
            flex-direction: column; align-items: stretch; gap: 6px; width: 100%;
        }
        .navbar-nav .nav-link {
            padding: 12px 18px; border-radius: 12px; font-size: 1rem; width: 100%; box-sizing: border-box;
        }
        .navbar-actions {
            flex-direction: column; gap: 10px; width: 100%; margin-top: 14px;
            padding-top: 14px; border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .nav-btn-admin, .nav-btn-cta {
            width: 100%; justify-content: center; padding: 12px 20px; box-sizing: border-box;
        }
    }

    /* ---------- FAQ ---------- */
    .faq{max-width:800px;margin:0 auto;padding:60px 16px}
    .faq h2{text-align:center;font-size:clamp(1.6rem,3vw,2.2rem);margin-bottom:26px;color:var(--ink)}
    .faq details{margin-bottom:10px;background:#fff;border:1px solid rgba(45,31,61,.1);padding:16px 18px;border-radius:12px}
    .faq summary{cursor:pointer;font-weight:600;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:12px;color:var(--ink)}
    .faq summary::-webkit-details-marker{display:none}
    .faq summary i{font-size:12px;transition:transform .2s}
    .faq details[open] summary i{transform:rotate(180deg)}
    .faq p{margin:12px 0 0;line-height:1.65;opacity:.85}
    </style>
</head>

<body>

<div class="page-loader" id="pageLoader" role="status" aria-label="Loading page">
    <span class="page-loader-spinner" aria-hidden="true"></span>
</div>

<!-- NAVBAR -->
 <div class="topbar">
    <div class="container topbar-inner">
        <a href="tel:+917810807552" class="topbar-link">
            <i class="fas fa-phone-alt"></i>
            <span>+91 78108 07552</span>
        </a>
        <a href="mailto:info@sktravelplanners.in" class="topbar-link">
            <i class="fas fa-envelope"></i>
            <span>info@sktravelplanners.in</span>
        </a>
    </div>
</div>
<nav class="navbar" id="mainNavbar">
    <div class="container navbar-inner">
        <a href="index.php" class="navbar-brand">
            <span class="brand-icon"><img src="logo.jpg" alt="Logo"></span>
            <span class="brand-title"><?= e(APP_NAME) ?></span>
        </a>

        <!-- Mobile Menu Toggle Button -->
        <button type="button" class="navbar-toggler" id="navbarToggler" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navbarMenu">
            <span class="toggler-bar"></span>
            <span class="toggler-bar"></span>
            <span class="toggler-bar"></span>
        </button>

        <div class="navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav">
                <li>
                    <a href="index.php" class="nav-link active">
                        <i class="fas fa-home"></i> <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="#itineraries" class="nav-link">
                        <i class="fas fa-compass"></i> <span>Destinations</span>
                    </a>
                </li>
                <li>
                    <a href="#gallery" class="nav-link">
                        <i class="fas fa-images"></i> <span>Gallery</span>
                    </a>
                </li>
                <li>
                    <a href="#reviews" class="nav-link">
                        <i class="fas fa-star"></i> <span>Reviews</span>
                    </a>
                </li>
                <li>
                    <a href="#faq" class="nav-link">
                        <i class="fas fa-circle-question"></i> <span>FAQ</span>
                    </a>
                </li>
            </ul>

            <div class="navbar-actions">
                <a href="admin/index.php" class="nav-btn-admin" title="Admin Portal">
                    <i class="fas fa-shield-halved"></i> <span>Admin</span>
                </a>
                <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappMsg ?>" target="_blank" rel="noopener" class="nav-btn-cta">
                    <i class="fab fa-whatsapp"></i> <span>Plan My Trip</span>
                </a>
            </div>
        </div>
    </div>
</nav>
<!-- =========================================================
HERO======================================================== -->
<section class="hero hero-carousel" aria-roledescription="carousel" aria-label="Incredible India destinations">

    <div class="hc-track">
        <?php foreach ($heroSlides as $i => $s): ?>
            <figure
                class="hc-slide<?= $i === 0 ? ' is-active' : '' ?>"
                style="background:<?= e($s['tint']) ?>"
                data-title="<?= e($s['title']) ?>"
                data-state="<?= e($s['state']) ?>"
                role="group"
                aria-roledescription="slide"
                aria-label="<?= $i + 1 ?> of <?= count($heroSlides) ?>"
            >
                <?php if ($i === 0): ?>
                    <!-- First slide loads right away (it's the LCP image) -->
                    <img
                        src="<?= e($heroSrc($s, 1600)) ?>"
                        <?php if ($heroSrcset($s)): ?>srcset="<?= e($heroSrcset($s)) ?>" sizes="100vw"<?php endif; ?>
                        alt="<?= e($s['title']) ?>, <?= e($s['state']) ?>"
                        width="1600" height="900"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                        onload="this.classList.add('is-loaded')"
                    >
                <?php else: ?>
                    <!-- Other slides are lazy: the browser won't fetch them until the script asks -->
                    <img
                        data-src="<?= e($heroSrc($s, 1600)) ?>"
                        <?php if ($heroSrcset($s)): ?>data-srcset="<?= e($heroSrcset($s)) ?>" sizes="100vw"<?php endif; ?>
                        alt="<?= e($s['title']) ?>, <?= e($s['state']) ?>"
                        width="1600" height="900"
                        decoding="async"
                    >
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>

    <div class="hc-overlay" aria-hidden="true"></div>

    <div class="container hc-inner">
        <div class="hero-content">

            <h1>
                Plan Your Dream
                <span>Journey</span>
                With Confidence
            </h1>

            <p>
                Curated travel itineraries crafted by experts.
                From serene backwaters to majestic peaks —
                your perfect trip awaits.
            </p>

            <div class="hero-actions">
                <a href="#itineraries" class="btn btn-accent btn-lg">
                    <i class="fas fa-compass"></i>
                    Explore Itineraries
                </a>

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

    <div class="hc-place" aria-live="polite"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span></span></div>

    <div class="hc-controls">
        <button type="button" class="hc-btn hc-prev" aria-label="Previous slide"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
        <button type="button" class="hc-btn hc-toggle" aria-label="Pause slideshow"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg></button>
        <button type="button" class="hc-btn hc-next" aria-label="Next slide"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button>
    </div>

    <div class="hc-dots" aria-hidden="true"></div>
    <div class="hc-progress" aria-hidden="true"><span></span></div>

</section>

<!-- =========================================================
     FILTER (single instance, directly below the hero)
========================================================= -->
<?php if (!empty($itineraries)): ?>
<section class="sf-section" id="find-package" aria-label="Find a package">
    <div class="container">
        <div class="sf-inner">

            <span class="sf-eyebrow"><i class="fas fa-compass" aria-hidden="true"></i> Plan your escape</span>
            <h2 class="sf-heading">Where do you want to go?</h2>
            <p class="sf-sub">Search by destination or pick a popular one to see matching packages instantly.</p>

            <div class="sf-card">
                <div class="sf-head">
                    <div class="sf-title"><i class="fas fa-sliders" aria-hidden="true"></i> Find your perfect package</div>
                    <div class="sf-count" aria-live="polite">
                        Showing <strong id="resultCount"><?= min($pageSize, count($itineraries)) ?></strong>
                        of <strong id="totalCount"><?= count($itineraries) ?></strong> packages
                    </div>
                </div>

                <div class="sf-row">
                    <div class="sf-search">
                        <input type="text" id="destinationSearch" placeholder="Search by destination, e.g. Darjeeling"
                               aria-label="Search destination" autocomplete="off">
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                    </div>
                    <button type="button" class="sf-reset filter-reset" id="resetFilters">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i> Reset
                    </button>
                </div>

                <?php if ($primaryDestinations): ?>
                <div class="sf-chips" id="destChips" role="group" aria-label="Popular destinations">
                    <button type="button" class="sf-chip is-active" data-dest="">All</button>
                    <?php foreach ($primaryDestinations as $d): ?>
                        <button type="button" class="sf-chip" data-dest="<?= e($d['label']) ?>">
                            <?= e($d['label']) ?><em><?= (int)$d['count'] ?></em>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="active-filters" id="activeFilters" aria-live="polite"></div>

                <!-- Kept for compatibility with app.js (price filtering has been removed) -->
                <select id="priceFilter" hidden tabindex="-1" aria-hidden="true"><option value="all">All Prices</option></select>
            </div>

        </div>
    </div>
</section>
<?php endif; ?>

<!-- BEST SELLERS -->
<?php if (!empty($bestSellers)): ?>
<section class="section bs-section" id="best-sellers">
    <div class="container">
        <div class="bs-heading">
            <span class="bs-eyebrow"><i class="fas fa-award" aria-hidden="true"></i> Traveller Favourites</span>
            <h2>Our Best-Selling <span>Tour Packages</span></h2>
            <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
            <p>Handpicked journeys that travellers book again and again, with expert-planned itineraries, trusted stays and dependable local support.</p>

            <?php if ($bsReviewTotal): ?>
            <ul class="bs-trust">
                <li><i class="fas fa-star" aria-hidden="true"></i> <b><?= number_format($bsOverallAvg, 1) ?>/5</b> average rating</li>
                <li><i class="fas fa-users" aria-hidden="true"></i> <b><?= (int)$bsReviewTotal ?>+</b> traveller reviews</li>
                <li><i class="fas fa-headset" aria-hidden="true"></i> <b>24/7</b> trip support</li>
            </ul>
            <?php endif; ?>

            <a href="#itineraries" class="bs-viewall">View all packages <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>

        <div class="car" id="bsCarousel" aria-roledescription="carousel" aria-label="Best selling packages">
            <button type="button" class="car-arrow car-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
            <div class="car-track" id="bsTrack">
                <?php foreach ($bestSellers as $n => $b): $rt = $ratings[(int)$b['id']] ?? null; ?>
                    <article class="bs-card">
                        <div class="bs-img">
                            <img src="<?= e(imageUrl($b['image'])) ?>" alt="<?= e($b['title']) ?>"
                                 loading="<?= $n < 2 ? 'eager' : 'lazy' ?>" decoding="async">
                            <span class="bs-badge"><i class="fas fa-fire"></i> Best Seller</span>
                            <span class="bs-days"><?= (int)$b['duration_days'] ?> Days</span>
                        </div>
                        <div class="bs-body">
                            <h3><?= e($b['title']) ?></h3>
                            <div class="bs-dest"><i class="fas fa-map-marker-alt"></i> <?= e($b['destination']) ?></div>
                            <?php if ($rt): ?>
                                <div class="bs-rate"><i class="fas fa-star"></i> <b><?= number_format($rt['avg'], 1) ?></b> (<?= $rt['count'] ?>)</div>
                            <?php endif; ?>
                            <div class="bs-foot">
                                <span class="bs-price"><?= e(formatPrice($b['price'])) ?></span>
                                <a class="btn btn-primary btn-sm" href="detail.php?id=<?= (int)$b['id'] ?>">View <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <button type="button" class="car-arrow car-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="car-dots" id="bsDots" role="group" aria-label="Choose slide"></div>
    </div>
</section>
<?php endif; ?>

<!-- ITINERARIES -->
<section class="section" id="itineraries">
    <div class="container">

        <div class="bs-heading">
            <span class="bs-eyebrow"><i class="fas fa-map-location-dot" aria-hidden="true"></i> Explore &amp; Compare</span>
            <h2>Handcrafted <span>Travel Packages</span></h2>
            <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
            <p>Every journey is planned by local experts, with clear day-wise schedules, carefully chosen stays and authentic local experiences. Use the search above to find the trip that fits you.</p>
            <ul class="bs-trust">
                <li><i class="fas fa-calendar-check" aria-hidden="true"></i> <b>Day-wise</b> plans</li>
                <li><i class="fas fa-hotel" aria-hidden="true"></i> <b>Handpicked</b> stays</li>
                <li><i class="fas fa-user-tie" aria-hidden="true"></i> <b>Local</b> experts</li>
                <li><i class="fas fa-pen-ruler" aria-hidden="true"></i> <b>Fully</b> customisable</li>
            </ul>
        </div>

        <?php if (empty($itineraries)): ?>

            <div class="empty-state">
                <i class="fas fa-suitcase-rolling"></i>
                <p>No itineraries available yet. Check back soon!</p>
            </div>

        <?php else: ?>

            <div class="card-grid skeleton-grid" id="skeletonGrid" aria-hidden="true">
                <?php for ($s = 0; $s < min($pageSize, count($itineraries)); $s++): ?>
                    <div class="sk-card">
                        <div class="skeleton sk-img"></div>
                        <div class="sk-body">
                            <div class="skeleton sk-line lg"></div>
                            <div class="skeleton sk-line sm"></div>
                            <div class="skeleton sk-line md"></div>
                            <div class="skeleton sk-line"></div>
                            <div class="sk-row"><div class="skeleton sk-line price"></div><div class="skeleton sk-btn"></div></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="card-grid is-loading" id="itineraryGrid">
                <?php foreach ($itineraries as $index => $itin):
                    $highlights = json_decode((string)$itin['highlights'], true) ?? [];
                    $days       = json_decode((string)$itin['day_plan'], true) ?? [];
                    $price      = (float)$itin['price'];
                    $eager      = $index < $pageSize;
                    $rt         = $ratings[(int)$itin['id']] ?? null;
                ?>
                    <div class="itin-card itinerary-item"
                         data-price="<?= $price ?>"
                         data-destination="<?= e(strtolower($itin['destination'])) ?>"
                         data-title="<?= e(strtolower($itin['title'])) ?>">

                        <div class="itin-card-img">
                            <span class="img-shimmer skeleton" aria-hidden="true"></span>
                            <img src="<?= e(imageUrl($itin['image'])) ?>" alt="<?= e($itin['title']) ?>"
                                 loading="<?= $eager ? 'eager' : 'lazy' ?>" decoding="async"
                                 <?= $index === 0 ? 'fetchpriority="high"' : '' ?>>
                            <span class="itin-card-badge"><?= (int)$itin['duration_days'] ?> Days</span>
                            <span class="rating-pill<?= $rt ? '' : ' is-new' ?>"
                                  aria-label="<?= $rt ? 'Rated ' . number_format($rt['avg'], 1) . ' out of 5 by ' . $rt['count'] . ' travellers' : 'No ratings yet' ?>">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <?php if ($rt): ?><b><?= number_format($rt['avg'], 1) ?></b><em>(<?= $rt['count'] ?>)</em>
                                <?php else: ?><b>New</b><?php endif; ?>
                            </span>
                        </div>

                        <div class="itin-card-body">
                            <h3><?= e($itin['title']) ?></h3>
                            <div class="dest"><i class="fas fa-map-marker-alt"></i> <?= e($itin['destination']) ?></div>
                            <p class="desc"><?= e($itin['description']) ?></p>

                            <div class="itin-card-meta">
                                <span><i class="fas fa-calendar-alt"></i> <?= (int)$itin['duration_days'] ?> Days</span>
                                <span><i class="fas fa-star"></i> <?= count($highlights) ?> Highlights</span>
                                <span><i class="fas fa-route"></i> <?= count($days) ?> Stops</span>
                            </div>

                            <div class="rate-box" data-id="<?= (int)$itin['id'] ?>">
                                <div class="rate-stars" role="radiogroup" aria-label="Rate <?= e($itin['title']) ?>">
                                    <?php for ($n = 1; $n <= 5; $n++): ?>
                                        <button type="button" class="rate-star" role="radio" aria-checked="false"
                                                data-value="<?= $n ?>" aria-label="<?= $n ?> star<?= $n > 1 ? 's' : '' ?>">
                                            <i class="fas fa-star" aria-hidden="true"></i>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                                <span class="rate-count<?= $rt ? '' : ' is-empty' ?>" aria-live="polite">
                                    <?php if ($rt): ?><b><?= number_format($rt['avg'], 1) ?></b> · <?= $rt['count'] ?> rating<?= $rt['count'] === 1 ? '' : 's' ?>
                                    <?php else: ?>No ratings yet<?php endif; ?>
                                </span>
                                <span class="rate-hint">Tap a star to rate</span>
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

                <div id="noFilterResults" class="filter-no-results" style="display:none;">
                    <i class="fas fa-map-signs"></i>
                    <h3>No packages found</h3>
                    <p>Try a different destination.</p>
                    <button type="button" class="btn btn-primary" id="resetFiltersEmpty">Reset filter</button>
                </div>
            </div>

            <div class="load-more-wrap" id="loadMoreWrap" hidden>
                <button type="button" class="btn btn-primary btn-lg load-more-btn" id="loadMoreBtn">
                    <span class="btn-spinner" aria-hidden="true"></span>
                    <span class="btn-label">Load more packages</span>
                </button>
                <p class="load-more-hint" id="loadMoreHint"></p>
            </div>

        <?php endif; ?>
    </div>
</section>

<!-- TRAVEL GALLERY -->
<section class="section gallery-section" id="gallery" aria-label="Travel photo gallery">
    <div class="container">

        <div class="bs-heading">
            <span class="bs-eyebrow"><i class="fas fa-camera-retro" aria-hidden="true"></i> Visual Journey</span>
            <h2>Captured Moments &amp; <span>Travel Memories</span></h2>
            <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
            <p>Immerse yourself in authentic travel moments, breathtaking landscapes, and scenic highlights captured across our handcrafted journeys.</p>
        </div>

        <?php if (!empty($galleryDestinations)): ?>
            <div class="gal-filter-chips" id="galFilterChips" role="group" aria-label="Filter gallery by destination">
                <button type="button" class="gal-chip is-active" data-dest="all">
                    <i class="fas fa-layer-group"></i> All Photos <em><?= count($galleryImages) ?></em>
                </button>
                <?php foreach ($galleryDestinations as $gd): ?>
                    <button type="button" class="gal-chip" data-dest="<?= e(mb_strtolower($gd['label'])) ?>">
                        <?= e($gd['label']) ?> <em><?= (int)$gd['count'] ?></em>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($galleryImages)): ?>
            <div class="gal-empty">
                <i class="fas fa-images"></i>
                <h3 style="font-family:'Playfair Display',serif;margin-bottom:6px">Gallery Coming Soon</h3>
                <p style="color:#64748b;font-size:14.5px;">We are currently curating stunning travel pictures for you. Check back shortly!</p>
            </div>
        <?php else: ?>
            <div class="gal-grid" id="galGrid">
                <?php foreach ($galleryImages as $gIdx => $gPhoto):
                    $gUrl = galleryImageUrl($gPhoto['image']);
                    $gDest = trim((string)($gPhoto['destination'] ?? ''));
                    $gDestKey = mb_strtolower($gDest);
                ?>
                    <article class="gal-item"
                             data-dest="<?= e($gDestKey) ?>"
                             data-src="<?= e($gUrl) ?>"
                             data-title="<?= e($gPhoto['title']) ?>"
                             data-desc="<?= e($gPhoto['description'] ?? '') ?>"
                             data-location="<?= e($gDest) ?>"
                             tabindex="0"
                             role="button"
                             aria-label="View <?= e($gPhoto['title']) ?>">
                        <?php if ($gDest !== ''): ?>
                            <span class="gal-badge"><i class="fas fa-map-marker-alt"></i> <?= e($gDest) ?></span>
                        <?php endif; ?>
                        <img src="<?= e($gUrl) ?>" alt="<?= e($gPhoto['title']) ?>"
                             loading="<?= $gIdx < 8 ? 'eager' : 'lazy' ?>" decoding="async">
                        <div class="gal-overlay">
                            <h3 class="gal-title"><?= e($gPhoto['title']) ?></h3>
                            <?php if (!empty($gPhoto['description'])): ?>
                                <p class="gal-caption"><?= e($gPhoto['description']) ?></p>
                            <?php endif; ?>
                            <span class="gal-zoom-icon" aria-hidden="true"><i class="fas fa-expand-alt"></i></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div id="galNoResults" style="display:none; text-align:center; padding:30px 16px; color:#64748b;">
                <i class="fas fa-camera" style="font-size:36px; color:var(--accent-orange); margin-bottom:8px;"></i>
                <p>No photos found for this destination.</p>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- GALLERY LIGHTBOX MODAL -->
<div id="galModal" class="gal-modal" role="dialog" aria-modal="true" aria-label="Enlarged photo view">
    <div class="gal-modal-inner">
        <button type="button" class="gal-modal-close" id="galModalClose" aria-label="Close photo view"><i class="fas fa-times"></i></button>

        <div class="gal-modal-img-wrap">
            <button type="button" class="gal-modal-nav gal-modal-prev" id="galModalPrev" aria-label="Previous photo"><i class="fas fa-chevron-left"></i></button>
            <img id="galModalImg" class="gal-modal-img" src="" alt="Travel photo">
            <button type="button" class="gal-modal-nav gal-modal-next" id="galModalNext" aria-label="Next photo"><i class="fas fa-chevron-right"></i></button>
        </div>

        <div class="gal-modal-bar">
            <div class="gal-modal-bar-left">
                <h4 id="galModalTitle" class="gal-modal-bar-title"></h4>
                <p id="galModalDesc" class="gal-modal-bar-desc"></p>
            </div>
            <span id="galModalCount" class="gal-modal-count"></span>
        </div>
    </div>
</div>

<!-- REVIEWS -->
<section class="section reviews-section" id="reviews">
    <div class="container">

        <div class="bs-heading">
            <span class="bs-eyebrow"><i class="fas fa-heart" aria-hidden="true"></i> Real Traveller Experiences</span>
            <h2>What Users Say</h2>
            <p>Discover why travellers trust SK Travel Planners for their journeys and customised holiday packages.</p>
            <button type="button" class="btn btn-primary" id="openReview" style="margin-top:20px">
                <i class="fas fa-pen"></i> Write a Review
            </button>
        </div>

        <?php if (!empty($bestReviews)): ?>
            <div class="car" id="rvCarousel" aria-roledescription="carousel" aria-label="Customer reviews">
                <button type="button" class="car-arrow car-prev" aria-label="Previous review"><i class="fas fa-chevron-left"></i></button>
                <div class="car-track" id="rvTrack">
                    <?php foreach ($bestReviews as $rev):
                        $initials = '';
                        foreach (array_slice(explode(' ', trim((string)$rev['user_name'])), 0, 2) as $w) {
                            $initials .= mb_substr($w, 0, 1);
                        }
                        $photos = $bestReviewImages[$rev['id']] ?? [];
                    ?>
                        <article class="rv-card">
                            <div class="rv-quote"><i class="fas fa-quote-right"></i></div>
                            <div class="rv-stars" aria-label="Rated <?= (int)$rev['rating'] ?> out of 5">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="<?= $s <= (int)$rev['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                <?php endfor; ?>
                                <span class="rv-pill"><?= (int)$rev['rating'] ?>.0 / 5</span>
                            </div>
                            <?php if (!empty($rev['review_title'])): ?><h4><?= e($rev['review_title']) ?></h4><?php endif; ?>
                            <p class="rv-text">"<?= nl2br(e($rev['review_text'])) ?>"</p>

                            <?php if ($photos): ?>
                                <div class="rv-photos">
                                    <?php foreach ($photos as $p):
                                        $pUrl = reviewImageUrl($p) ?: ('review_folder/' . rawurlencode($p)); ?>
                                        <button type="button" data-lightbox="<?= e($pUrl) ?>" title="Enlarge photo">
                                            <img src="<?= e($pUrl) ?>" alt="Photo by <?= e($rev['user_name']) ?>" loading="lazy">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="rv-foot">
                                <div class="rv-avatar"><?= e($initials ?: 'U') ?></div>
                                <div>
                                    <div class="rv-name"><?= e($rev['user_name']) ?></div>
                                    <div class="rv-sub">
                                        <?php if (!empty($rev['user_location'])): ?>
                                            <span><i class="fas fa-map-marker-alt"></i> <?= e($rev['user_location']) ?></span>
                                        <?php endif; ?>
                                        <span class="rv-ok"><i class="fas fa-check-circle"></i> Verified Traveller</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="car-arrow car-next" aria-label="Next review"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="car-dots" id="rvDots" role="group" aria-label="Choose review"></div>
        <?php else: ?>
            <div class="rv-empty">
                <i class="fas fa-comments" style="font-size:48px;color:var(--accent-orange);margin-bottom:14px"></i>
                <h3 style="font-family:'Playfair Display',serif;margin-bottom:8px">Be the first to review</h3>
                <p style="margin-bottom:18px">Travelled with SK Travel Planners? Share your experience with our community.</p>
                <button type="button" class="btn btn-primary" data-open-review><i class="fas fa-pen"></i> Leave a Review</button>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- REVIEW MODAL -->
<div id="reviewModal" class="ov" role="dialog" aria-modal="true" aria-label="Write a review">
    <div class="md">
        <div class="md-head">
            <div>
                <h3>Share Your Experience</h3>
                <p>Your review helps fellow travellers plan their trips.</p>
            </div>
            <button type="button" class="md-x" id="closeReview" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>

        <div class="md-body">
            <div id="reviewSuccessBox" style="display:none;text-align:center;padding:26px 8px">
                <div style="width:68px;height:68px;border-radius:50%;background:#d1fae5;color:#059669;display:inline-grid;place-items:center;font-size:32px;margin-bottom:16px"><i class="fas fa-check-circle"></i></div>
                <h4 style="font-family:'Playfair Display',serif;font-size:22px;margin-bottom:8px">Thank you!</h4>
                <p id="reviewSuccessText" style="line-height:1.6;margin-bottom:22px">Your review was submitted and will appear once approved by our team.</p>
                <button type="button" class="btn btn-primary" id="doneReview">Done</button>
            </div>

            <form id="liveReviewForm" novalidate>
                <div id="reviewAlertBox" class="alert" role="alert"></div>
                <input type="text" name="website_url" style="display:none!important" tabindex="-1" autocomplete="off">
                <input type="hidden" name="ajax" value="1">

                <div class="stars-pick">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px">How was your experience?</label>
                    <div id="modalStarPicker">
                        <?php for ($n = 1; $n <= 5; $n++): ?>
                            <span class="m-star" data-val="<?= $n ?>" title="<?= $n ?> star<?= $n > 1 ? 's' : '' ?>"><i class="fas fa-star"></i></span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="modalRatingInput" value="5">
                    <div id="modalRatingText">★★★★★ 5.0 - Exceptional experience!</div>
                </div>

                <div class="fld">
                    <label for="rvName">Your full name <span style="color:#ef4444">*</span></label>
                    <input type="text" id="rvName" name="user_name" required placeholder="e.g. Rahul Sharma">
                </div>

                <div class="two fld">
                    <div>
                        <label for="rvEmail">Email <small>(private)</small></label>
                        <input type="email" id="rvEmail" name="user_email" placeholder="rahul@example.com">
                    </div>
                    <div>
                        <label for="rvLoc">Destination / tour <small>(optional)</small></label>
                        <input type="text" id="rvLoc" name="user_location" placeholder="e.g. Darjeeling">
                    </div>
                </div>

                <div class="fld">
                    <label for="rvTitle">Headline <small>(optional)</small></label>
                    <input type="text" id="rvTitle" name="review_title" placeholder="e.g. Best vacation of our lives!">
                </div>

                <div class="fld">
                    <label for="rvText">Your review <span style="color:#ef4444">*</span></label>
                    <textarea id="rvText" name="review_text" rows="4" required minlength="10" placeholder="Tell other travellers about the hotels, guides, transport and support..."></textarea>
                    <small>Minimum 10 characters</small>
                </div>

                <div class="fld">
                    <label><i class="fas fa-camera" style="color:var(--accent-orange)"></i> Photos <small>(optional, up to 5)</small></label>
                    <div class="drop" id="reviewDropzone" tabindex="0" role="button">
                        <input type="file" name="review_images[]" id="publicReviewImagesInput" multiple
                               accept="image/png,image/jpeg,image/webp,image/gif" style="display:none">
                        <i class="fas fa-images" style="font-size:24px;color:var(--accent-orange)"></i><br>
                        <b>Click to upload photos</b><br>
                        <small>JPG, PNG, WebP or GIF · max 5 MB each</small>
                    </div>
                    <div class="thumbs" id="reviewSelectedThumbnails"></div>
                </div>

                <div class="actions">
                    <button type="button" class="btn-ghost" id="cancelReview">Cancel</button>
                    <button type="submit" id="reviewSubmitBtn" class="btn btn-primary">
                        <span id="reviewBtnIcon"><i class="fas fa-paper-plane"></i></span>
                        <span id="reviewBtnText">Submit Review</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Photo lightbox -->
<div id="publicReviewLightbox" class="ov" role="dialog" aria-label="Review photo">
    <img id="publicReviewLightboxImg" src="" alt="Review photo">
</div>

<!-- FAQ -->
<section id="faq" class="faq">
    <h2>Frequently Asked Questions</h2>
    <?php foreach ($faqs as $f): ?>
        <details>
            <summary><?= e($f[0]) ?> <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
            <p><?= e($f[1]) ?></p>
        </details>
    <?php endforeach; ?>
</section>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h4><img class="footer-logo" src="logo.jpg" alt="Logo"> <?= e(APP_NAME) ?></h4>
                <p style="max-width:320px;">Your trusted partner for unforgettable travel experiences. Expert-crafted itineraries for destinations across the globe.</p>
            </div>
            <div>
                <h4>Quick Links</h4>
                <ul style="list-style:none;padding-left:0;">
                    <li style="margin-bottom:8px;"><a href="index.php">Home</a></li>
                    <li style="margin-bottom:8px;"><a href="#itineraries">Itineraries</a></li>
                    <li style="margin-bottom:8px;"><a href="#gallery">Travel Gallery</a></li>
                    <li style="margin-bottom:8px;"><a href="#reviews">Reviews</a></li>
                    <li style="margin-bottom:8px;"><a href="#faq">FAQ</a></li>
                    <li style="margin-bottom:8px;"><a href="admin/index.php">Admin Panel</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> <a href="mailto:info@sktravelplanners.in">info@sktravelplanners.in</a></p>
                <p><i class="fab fa-whatsapp"></i> <a href="https://wa.me/<?= $whatsappNumber ?>" target="_blank" rel="noopener noreferrer">+91 78108 07552</a></p>
                <a href="https://wa.me/<?= $whatsappNumber ?>" target="_blank" rel="noopener" class="footer-whatsapp">
                    <i class="fab fa-whatsapp"></i> WhatsApp Us
                </a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
            <a href="privicy-policy.html" target="_blank">Terms &amp; Conditions</a>
            &nbsp; &nbsp; <span><a href="https://e-websolutions.netlify.app/" target="_blank" rel="noopener">Maintained by e-WebSolutions</a></span>
        </div>
    </div>
</footer>

<!-- FLOATING WHATSAPP -->
<a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappMsg ?>" target="_blank" rel="noopener"
   class="floating-whatsapp" aria-label="Chat on WhatsApp"><i class="fab fa-whatsapp"></i></a>

<!-- CHATBOT -->
<div class="chatbot">
    <div class="chatbot-nudge" id="chatbotNudge" hidden>
        <button type="button" class="chatbot-nudge-close" id="chatbotNudgeClose" aria-label="Dismiss"><i class="fas fa-times"></i></button>
        <span>👋 Not sure where to go? Tell me your budget and I'll suggest a trip.</span>
    </div>

    <button type="button" class="chatbot-toggle" id="chatbotToggle" aria-label="Open travel assistant"
            aria-expanded="false" aria-controls="chatbotWindow">
        <i class="fas fa-comments"></i>
        <span class="chatbot-notification" id="chatbotBadge">1</span>
    </button>

    <div class="chatbot-window" id="chatbotWindow" role="dialog" aria-label="Travel assistant" aria-hidden="true">
        <div class="chatbot-header">
            <div class="chatbot-header-info">
                <div class="chatbot-avatar"><i class="fas fa-headset"></i></div>
                <div><strong>Travel Assistant</strong><small><span class="online-dot"></span> Online</small></div>
            </div>
            <div class="chatbot-header-actions">
                <button type="button" id="chatbotClear" class="chatbot-close" aria-label="Restart chat" title="Restart chat"><i class="fas fa-rotate-right"></i></button>
                <button type="button" id="chatbotClose" class="chatbot-close" aria-label="Close chatbot"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <div class="chatbot-messages" id="chatbotMessages" aria-live="polite">
            <div class="bot-message" id="chatbotWelcome">
                <div class="message-avatar"><i class="fas fa-robot"></i></div>
                <div class="message-content">
                    👋 Hello! Welcome to <?= e(APP_NAME) ?>.<br><br>
                    Tell me your <strong>budget</strong>, <strong>destination</strong> or <strong>trip length</strong>, for example
                    <em>“5 day trip under 20k”</em>, and I'll find matching packages.
                    <div class="quick-replies">
                        <button type="button" data-question="Show me affordable packages">💰 Affordable packages</button>
                        <button type="button" data-question="Which destinations do you cover?">📍 Destinations</button>
                        <button type="button" data-question="How can I customize my trip?">✨ Customize my trip</button>
                        <button type="button" data-question="I want to talk to a travel expert">👨‍💼 Talk to an expert</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="chatbot-input-area">
            <input type="text" id="chatbotInput" placeholder="Ask about your trip..." autocomplete="off" maxlength="300">
            <button type="button" id="chatbotSend" aria-label="Send message"><i class="fas fa-paper-plane"></i></button>
        </div>
        <div class="chatbot-footer"><span>Powered by <?= e(APP_NAME) ?></span></div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
    window.APP = <?= json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="app.js" defer></script>
<script src="carousel-extras.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };

    /* ---------- Destination filter: chips drive the search box that app.js listens to ---------- */
    var search = $('destinationSearch');
    var chips  = document.querySelectorAll('#destChips .sf-chip');

    function markChip(value) {
        var v = (value || '').trim().toLowerCase();
        chips.forEach(function (c) { c.classList.toggle('is-active', c.dataset.dest.toLowerCase() === v); });
    }
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (!search) return;
            search.value = chip.dataset.dest;
            search.dispatchEvent(new Event('input', { bubbles: true }));
            markChip(chip.dataset.dest);
            var grid = $('itineraryGrid');
            if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    if (search) search.addEventListener('input', function () { markChip(search.value); });
    ['resetFilters', 'resetFiltersEmpty'].forEach(function (id) {
        var b = $(id); if (b) b.addEventListener('click', function () { markChip(''); });
    });

    /* ---------- Reusable scroll-snap carousel (best sellers + reviews) ---------- */
    function carousel(rootId, trackId, dotsId, cardSel, auto) {
        var root = $(rootId), track = $(trackId), dots = $(dotsId);
        if (!root || !track) return;
        var cards = track.querySelectorAll(cardSel), timer;
        if (!cards.length) return;

        var step  = function () { return cards[0].getBoundingClientRect().width + 20; };
        var per   = function () { return Math.max(1, Math.round(track.clientWidth / cards[0].getBoundingClientRect().width)); };
        var pages = function () { return Math.max(1, cards.length - per() + 1); };

        function sync() {
            var idx = Math.round(track.scrollLeft / step());
            dots.querySelectorAll('button').forEach(function (d, i) { d.classList.toggle('on', i === idx); });
        }
        function go(i) { track.scrollTo({ left: i * step(), behavior: 'smooth' }); }
        function next() {
            var end = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
            end ? go(0) : track.scrollBy({ left: step(), behavior: 'smooth' });
        }
        function prev() { track.scrollLeft <= 4 ? go(pages() - 1) : track.scrollBy({ left: -step(), behavior: 'smooth' }); }
        function start() { if (auto && !matchMedia('(prefers-reduced-motion: reduce)').matches) { stop(); timer = setInterval(next, auto); } }
        function stop()  { clearInterval(timer); }
        function build() {
            if (!dots) return;
            dots.innerHTML = '';
            if (pages() < 2) return;
            for (var i = 0; i < pages(); i++) {
                var b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Slide ' + (i + 1));
                (function (n) { b.addEventListener('click', function () { go(n); start(); }); })(i);
                dots.appendChild(b);
            }
            sync();
        }

        root.querySelector('.car-next').addEventListener('click', function () { next(); start(); });
        root.querySelector('.car-prev').addEventListener('click', function () { prev(); start(); });
        track.addEventListener('scroll', function () { requestAnimationFrame(sync); }, { passive: true });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('touchstart', stop, { passive: true });
        root.addEventListener('touchend', start, { passive: true });
        addEventListener('resize', build);
        document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
        build(); start();
    }
    carousel('bsCarousel', 'bsTrack', 'bsDots', '.bs-card', 4500);
    carousel('rvCarousel', 'rvTrack', 'rvDots', '.rv-card', 5500);

    /* ---------- Mobile Navbar Menu Toggle ---------- */
    var navToggler = $('navbarToggler');
    var navMenu = $('navbarMenu');
    if (navToggler && navMenu) {
        navToggler.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = navMenu.classList.toggle('is-open');
            navToggler.classList.toggle('is-open', isOpen);
            navToggler.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        navMenu.querySelectorAll('.nav-link, .nav-btn-admin, .nav-btn-cta').forEach(function(link) {
            link.addEventListener('click', function() {
                navMenu.classList.remove('is-open');
                navToggler.classList.remove('is-open');
                navToggler.setAttribute('aria-expanded', 'false');
            });
        });

        document.addEventListener('click', function(e) {
            if (!navMenu.contains(e.target) && !navToggler.contains(e.target)) {
                navMenu.classList.remove('is-open');
                navToggler.classList.remove('is-open');
                navToggler.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ---------- Lightbox ---------- */
    var lb = $('publicReviewLightbox'), lbImg = $('publicReviewLightboxImg');
    function openLb(url) { lbImg.src = url; lb.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeLb()   { lb.classList.remove('open'); lbImg.src = ''; document.body.style.overflow = ''; }
    document.querySelectorAll('[data-lightbox]').forEach(function (b) {
        b.addEventListener('click', function () { openLb(b.dataset.lightbox); });
    });
    lb.addEventListener('click', closeLb);
    lbImg.addEventListener('click', function (e) { e.stopPropagation(); });

    /* ---------- Travel Gallery Filtering & Lightbox ---------- */
    var galItems = Array.from(document.querySelectorAll('#galGrid .gal-item'));
    var galChips = document.querySelectorAll('#galFilterChips .gal-chip');
    var galNoRes = $('galNoResults');
    var galModal = $('galModal'), galModalImg = $('galModalImg'),
        galModalTitle = $('galModalTitle'), galModalDesc = $('galModalDesc'),
        galModalCount = $('galModalCount');
    var currentVisibleGalItems = galItems.slice();
    var currentGalIndex = 0;

    if (galChips.length > 0) {
        galChips.forEach(function(chip) {
            chip.addEventListener('click', function() {
                galChips.forEach(function(c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                var targetDest = chip.dataset.dest;

                currentVisibleGalItems = [];
                galItems.forEach(function(item) {
                    var itemDest = item.dataset.dest || '';
                    if (targetDest === 'all' || itemDest === targetDest) {
                        item.style.display = 'block';
                        currentVisibleGalItems.push(item);
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (galNoRes) {
                    galNoRes.style.display = currentVisibleGalItems.length === 0 ? 'block' : 'none';
                }
            });
        });
    }

    function showGalPhoto(idx) {
        if (currentVisibleGalItems.length === 0) return;
        if (idx < 0) idx = currentVisibleGalItems.length - 1;
        if (idx >= currentVisibleGalItems.length) idx = 0;
        currentGalIndex = idx;

        var el = currentVisibleGalItems[currentGalIndex];
        if (!el) return;
        galModalImg.src = el.dataset.src;
        galModalTitle.textContent = el.dataset.title;
        var desc = el.dataset.desc;
        var loc = el.dataset.location;
        var descText = loc ? ('📍 ' + loc + (desc ? ' · ' + desc : '')) : (desc || '');
        galModalDesc.textContent = descText;
        galModalCount.textContent = (currentGalIndex + 1) + ' / ' + currentVisibleGalItems.length;
    }

    function openGalModal(itemEl) {
        var idx = currentVisibleGalItems.indexOf(itemEl);
        if (idx === -1) idx = 0;
        showGalPhoto(idx);
        galModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeGalModal() {
        if (!galModal) return;
        galModal.classList.remove('open');
        galModalImg.src = '';
        document.body.style.overflow = '';
    }

    galItems.forEach(function(item) {
        item.addEventListener('click', function() { openGalModal(item); });
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openGalModal(item);
            }
        });
    });

    if ($('galModalClose')) $('galModalClose').addEventListener('click', closeGalModal);
    if ($('galModalPrev')) $('galModalPrev').addEventListener('click', function(e) { e.stopPropagation(); showGalPhoto(currentGalIndex - 1); });
    if ($('galModalNext')) $('galModalNext').addEventListener('click', function(e) { e.stopPropagation(); showGalPhoto(currentGalIndex + 1); });
    if (galModal) {
        galModal.addEventListener('click', function(e) {
            if (e.target === galModal || e.target.classList.contains('gal-modal-inner') || e.target.classList.contains('gal-modal-img-wrap')) {
                closeGalModal();
            }
        });

        // Touch Swipe
        var touchStartX = 0, touchEndX = 0;
        galModal.addEventListener('touchstart', function(e) { touchStartX = e.changedTouches[0].screenX; }, {passive: true});
        galModal.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            if (touchEndX < touchStartX - 50) showGalPhoto(currentGalIndex + 1);
            if (touchEndX > touchStartX + 50) showGalPhoto(currentGalIndex - 1);
        }, {passive: true});
    }

    /* ---------- Review modal ---------- */
    var modal = $('reviewModal'), form = $('liveReviewForm'), alertBox = $('reviewAlertBox');
    var files = [], input = $('publicReviewImagesInput'), thumbs = $('reviewSelectedThumbnails');

    function openModal()  { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeModal() {
        modal.classList.remove('open'); document.body.style.overflow = '';
        files = []; syncFiles(); renderThumbs();
    }
    $('openReview').addEventListener('click', openModal);
    document.querySelectorAll('[data-open-review]').forEach(function (b) { b.addEventListener('click', openModal); });
    ['closeReview', 'cancelReview', 'doneReview'].forEach(function (id) { $(id).addEventListener('click', closeModal); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (galModal && galModal.classList.contains('open')) {
            if (e.key === 'Escape') closeGalModal();
            else if (e.key === 'ArrowLeft') showGalPhoto(currentGalIndex - 1);
            else if (e.key === 'ArrowRight') showGalPhoto(currentGalIndex + 1);
            return;
        }
        if (e.key !== 'Escape') return;
        if (lb.classList.contains('open')) closeLb(); else if (modal.classList.contains('open')) closeModal();
    });

    // Star picker
    var labels = { 1: '★☆☆☆☆ 1.0 - Poor', 2: '★★☆☆☆ 2.0 - Fair', 3: '★★★☆☆ 3.0 - Good',
                   4: '★★★★☆ 4.0 - Very good!', 5: '★★★★★ 5.0 - Exceptional experience!' };
    var rating = 5, stars = document.querySelectorAll('#modalStarPicker .m-star');
    function paint(v) {
        stars.forEach(function (s) {
            var on = +s.dataset.val <= v;
            s.querySelector('i').className = (on ? 'fas' : 'far') + ' fa-star';
            s.style.color = on ? '#fbbf24' : '#cbd5e1';
        });
        $('modalRatingText').textContent = labels[v];
    }
    stars.forEach(function (s) {
        s.addEventListener('mouseenter', function () { paint(+s.dataset.val); });
        s.addEventListener('click', function () { rating = +s.dataset.val; $('modalRatingInput').value = rating; paint(rating); });
    });
    $('modalStarPicker').addEventListener('mouseleave', function () { paint(rating); });

    // Photo picker
    function showError(msg) { alertBox.textContent = msg; alertBox.style.display = 'block'; }
    function syncFiles() {
        try { var dt = new DataTransfer(); files.forEach(function (f) { dt.items.add(f); }); input.files = dt.files; } catch (e) {}
    }
    function renderThumbs() {
        thumbs.innerHTML = '';
        files.forEach(function (file, idx) {
            var r = new FileReader();
            r.onload = function (ev) {
                var chip = document.createElement('div');
                chip.className = 'thumb';
                chip.innerHTML = '<img alt="Preview" src="' + ev.target.result + '">' +
                                 '<button type="button" title="Remove photo"><i class="fas fa-times"></i></button>';
                chip.querySelector('button').addEventListener('click', function () {
                    files.splice(idx, 1); syncFiles(); renderThumbs();
                });
                thumbs.appendChild(chip);
            };
            r.readAsDataURL(file);
        });
    }
    var drop = $('reviewDropzone');
    drop.addEventListener('click', function () { input.click(); });
    drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', function () {
        alertBox.style.display = 'none';
        Array.from(input.files || []).forEach(function (f) {
            if (!f.type.startsWith('image/')) return showError('Only image files (JPG, PNG, WebP, GIF) are allowed.');
            if (f.size > 5 * 1024 * 1024)     return showError(f.name + ' exceeds the 5 MB limit.');
            if (files.length >= 5)            return showError('You can upload up to 5 photos.');
            if (!files.some(function (x) { return x.name === f.name && x.size === f.size; })) files.push(f);
        });
        syncFiles(); renderThumbs();
    });

    // Submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        alertBox.style.display = 'none';
        if (form.user_name.value.trim().length < 2)   return showError('Please enter your name (at least 2 characters).');
        if (form.review_text.value.trim().length < 10) return showError('Please write at least 10 characters in your review.');

        var btn = $('reviewSubmitBtn'), txt = $('reviewBtnText'), ico = $('reviewBtnIcon');
        function busy(on) {
            btn.disabled = on;
            txt.textContent = on ? 'Submitting...' : 'Submit Review';
            ico.innerHTML = on ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-paper-plane"></i>';
        }
        busy(true);

        fetch('submit-review.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                busy(false);
                if (d.ok) {
                    form.style.display = 'none';
                    $('reviewSuccessText').textContent = d.message || 'Your review was submitted and will appear once approved by our team.';
                    $('reviewSuccessBox').style.display = 'block';
                } else {
                    showError(d.error || 'Failed to submit review. Please try again.');
                }
            })
            .catch(function () { busy(false); showError('Network error. Please check your connection and try again.'); });
    });
});
</script>

</body>
</html>