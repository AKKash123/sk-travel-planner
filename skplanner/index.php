<?php
// require_once 'config.php';

// // Fetch all active itineraries
// $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE status = 1 ORDER BY created_at DESC");
// $stmt->execute();
// $itineraries = $stmt->fetchAll();
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$stmt = $pdo->prepare(
    "SELECT *
     FROM itineraries
     WHERE status = 1
     ORDER BY created_at DESC"
);

$stmt->execute();

$itineraries = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------------------------------------------------------
// Ratings (table: itinerary_reviews — see reviews.sql)
// Wrapped in try/catch so the page still works before the table exists
// ---------------------------------------------------------
$ratings = [];
try {
    $rs = $pdo->query(
        "SELECT itinerary_id, ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM itinerary_reviews
         GROUP BY itinerary_id"
    );
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ratings[(int)$r['itinerary_id']] = [
            'avg'   => (float)$r['avg_rating'],
            'count' => (int)$r['total'],
        ];
    }
} catch (Throwable $e) {
    error_log('Ratings unavailable: ' . $e->getMessage());
}


// ---------------------------------------------------------
// Primary destinations (auto-built from the packages)
// "Darjeeling, Sikkim" -> primary = "Darjeeling"
// ---------------------------------------------------------
$destCounts = [];
foreach ($itineraries as $it) {
    $primary = trim(explode(',', (string)$it['destination'])[0]);
    if ($primary === '') continue;
    $key = mb_strtolower($primary);
    if (!isset($destCounts[$key])) {
        $destCounts[$key] = [
            'label' => $primary,
            'count' => 0,
            'image' => $it['image'],   // NEW: used by the destination cards
        ];
    }
    $destCounts[$key]['count']++;
}
uasort($destCounts, static fn($a, $b) => $b['count'] <=> $a['count']);
$primaryDestinations = array_slice($destCounts, 0, 8, true);


// ---------------------------------------------------------
// Best sellers
// ---------------------------------------------------------
$sales = []; // itinerary_id => number of bookings
/* OPTIONAL: use real sales if you have such a table
try {
    $bs = $pdo->query("SELECT itinerary_id, COUNT(*) AS n FROM bookings GROUP BY itinerary_id");
    foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sales[(int)$r['itinerary_id']] = (int)$r['n'];
    }
} catch (Throwable $e) { error_log('Sales unavailable: ' . $e->getMessage()); }
*/
$bestSellers = $itineraries;
usort($bestSellers, static function ($a, $b) use ($ratings, $sales) {
    $score = static function ($i) use ($ratings, $sales) {
        $id = (int)$i['id'];
        return ($sales[$id] ?? 0) * 100
             + ($ratings[$id]['count'] ?? 0) * ($ratings[$id]['avg'] ?? 0);
    };
    return $score($b) <=> $score($a) ?: strcmp((string)$b['created_at'], (string)$a['created_at']);
});
$bestSellers = array_slice($bestSellers, 0, 8);
// ---------------------------------------------------------
// Price slider bounds come from the real package prices
// ---------------------------------------------------------
$allPrices = array_map(static fn($i) => (float)$i['price'], $itineraries);
$priceMin  = $allPrices ? (int)(floor(min($allPrices) / 1000) * 1000) : 0;
$priceMax  = $allPrices ? (int)(ceil(max($allPrices) / 1000) * 1000) : 50000;
if ($priceMax <= $priceMin) {
    $priceMax = $priceMin + 1000;
}

// ---------------------------------------------------------
// Hero carousel — Incredible India (Unsplash photo IDs).
// Swap any 'photo' for another Unsplash ID, or set 'url' to your
// own image (e.g. 'assets/hero/goa.jpg'). A broken image is skipped
// automatically. 'tint' is the colour shown while the photo loads.
// ---------------------------------------------------------
$heroSlides = [
    // Darjeeling / Sikkim / Dooars (existing)
    ['slug' => 'darjeeling-pine-forest', 'title' => 'Pine Forests of Darjeeling', 'state' => 'Darjeeling',   'tint' => '#2f4a3a', 'wiki' => 'Pine trees Darjeeling.jpg'],
    ['slug' => 'yumthang-valley',        'title' => 'Yumthang Valley of Flowers', 'state' => 'North Sikkim', 'tint' => '#4a6a4a', 'wiki' => 'Yumthang Valley at North Sikkim, India 01.jpg'],
    ['slug' => 'ghum-toy-train',         'title' => 'Toy Train at Ghum Station',  'state' => 'Ghum',         'tint' => '#3b4a5a', 'wiki' => 'A train of Darjeeling Himalayan Railway at Ghoom Station.jpg'],
    ['slug' => 'kolakham-view',          'title' => 'Kolakham Viewpoint',         'state' => 'Kalimpong',    'tint' => '#3f6b55', 'wiki' => 'Kolakham view.jpg'],
    ['slug' => 'north-sikkim-zero-point','title' => 'Zero Point, North Sikkim',   'state' => 'North Sikkim', 'tint' => '#5a6f85', 'wiki' => 'Zero Point, Sikkim.jpg'],
    ['slug' => 'kurseong-tea-estates',   'title' => 'Kurseong Tea Estates',       'state' => 'Kurseong',     'tint' => '#3f6b45', 'wiki' => 'Tea estate in kurseong.jpg'],
    ['slug' => 'dow-hill',               'title' => 'Dow Hill Forest',            'state' => 'Kurseong',     'tint' => '#2f4a35', 'wiki' => 'Dow hill Kurseong.jpg'],
    ['slug' => 'kanchenjunga-view',      'title' => 'Kanchenjunga Panorama',      'state' => 'Darjeeling',   'tint' => '#5a6a80', 'wiki' => 'Darjeeling-panoramic.jpg'],
    ['slug' => 'mirik-pine-road',        'title' => 'Pine Road to Mirik',         'state' => 'Mirik',        'tint' => '#2f4f3a', 'wiki' => 'A Route through Pine Forest.jpg'],
    ['slug' => 'sumendu-lake',           'title' => 'Sumendu Lake',               'state' => 'Mirik',        'tint' => '#2d6f86', 'wiki' => 'Sumendu Lake, Mirik.jpg'],
    ['slug' => 'dooars-tea-garden',      'title' => 'Tea Gardens of the Dooars',  'state' => 'Dooars',       'tint' => '#3a5a30', 'wiki' => 'Tea garden in dooars.jpg'],

    // Arunachal Pradesh
    ['slug' => 'tawang-monastery',       'title' => 'Tawang Monastery',           'state' => 'Arunachal Pradesh', 'tint' => '#6a4a2f', 'wiki' => 'Tawang Monastery (Tibetan Buddhist).jpg'],
    ['slug' => 'ziro-valley',            'title' => 'Ziro Valley Paddy Fields',   'state' => 'Arunachal Pradesh', 'tint' => '#4a7a3a', 'wiki' => 'Ziro Valley.jpg'],

    // Assam
   ['slug' => 'sela-pass',              'title' => 'Sela Pass',                  'state' => 'Arunachal Pradesh', 'tint' => '#4a6a85', 'wiki' => 'Sela Pass, Arunachal Pradesh.jpg'],
['slug' => 'kaziranga-rhino',        'title' => 'One-Horned Rhino, Kaziranga','state' => 'Assam',             'tint' => '#5a6a3a', 'wiki' => 'One-Horned Rhino at the Kaziranga National Park, Assam.jpg'],

    ['slug' => 'majuli-island',          'title' => 'Majuli River Island',        'state' => 'Assam',             'tint' => '#3a6a70', 'wiki' => 'Majuli Island.jpg'],
    ['slug' => 'assam-tea-garden',       'title' => 'Tea Gardens of Assam',       'state' => 'Assam',             'tint' => '#3f6b30', 'wiki' => 'Tea Garden at Indo-Bhutan Border at Darranga, Assam.jpg'],

    // Goa
    ['slug' => 'palolem-beach',          'title' => 'Palolem Beach',              'state' => 'Goa',               'tint' => '#2d7a8a', 'wiki' => 'Palolem Beach.jpg'],
    ['slug' => 'basilica-bom-jesus',     'title' => 'Basilica of Bom Jesus',      'state' => 'Goa',               'tint' => '#8a5a3a', 'wiki' => 'Basilica of Bom Jesus.jpg'],
    ['slug' => 'dudhsagar-falls',        'title' => 'Dudhsagar Falls',            'state' => 'Goa',               'tint' => '#3a5a4a', 'wiki' => 'Dudhsagar Falls.jpg'],
];

foreach ($heroSlides as &$hs) {
    $hs['url'] = null;                       // local override
    foreach (['jpg', 'jpeg', 'webp', 'png'] as $ext) {
        if (is_file(__DIR__ . "/assets/hero/{$hs['slug']}.{$ext}")) {
            $hs['url'] = "assets/hero/{$hs['slug']}.{$ext}";
            break;
        }
    }
}
unset($hs);

$heroSrc = static function (array $s, int $w): string {
    if (!empty($s['url'])) {
        return $s['url'];
    }
    // Wikimedia Commons resizes on the fly with ?width=
    return 'https://commons.wikimedia.org/wiki/Special:FilePath/'
         . rawurlencode(str_replace(' ', '_', $s['wiki']))
         . '?width=' . $w;
};
$heroSrcset = static function (array $s) use ($heroSrc): string {
    if (!empty($s['url'])) {
        return '';
    }
    return implode(', ', array_map(static fn($w) => $heroSrc($s, $w) . ' ' . $w . 'w', [640, 1024, 1600]));
};


// WhatsApp number - country code + number, without + or spaces
$whatsappNumber = '917810807552';

$whatsappDefaultMessage = rawurlencode(
    "Hello! I found your travel packages on " . APP_NAME . ". I would like to know more about your itineraries."
);

// Number of cards shown first, and added on every "Load more" click
$pageSize = 3;

// Lightweight package data for the chatbot (real packages, real prices)
$chatPackages = array_map(function ($i) {
    return [
        'id'          => (int)$i['id'],
        'title'       => $i['title'],
        'destination' => $i['destination'],
        'price'       => (float)$i['price'],
        'priceLabel'  => formatPrice($i['price']),
        'days'        => (int)$i['duration_days'],
        'url'         => 'detail.php?id=' . (int)$i['id'],
    ];
}, $itineraries);

$appConfig = [
    'name'     => APP_NAME,
    'whatsapp' => $whatsappNumber,
    'pageSize' => $pageSize,
    'packages' => $chatPackages,
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <!-- Primary SEO -->
    <meta name="description" content="Plan your dream trip with SK Travel Planners – best travel agency for tour packages, custom itineraries, holiday packages, honeymoon packages, adventure tours & North Bengal trips. Expert-curated day-wise itineraries, handpicked stays and local experiences. Chat on WhatsApp to book now.">
    <meta name="keywords" content="SKTravel, SK Travel Planners, tour packages, custom itineraries, holiday packages, trip planning, North Bengal tours, best travel agency, vacation packages, honeymoon packages, family tour packages, adventure tours, budget travel packages, luxury travel packages, group tour packages, weekend getaways, holiday destinations, travel deals, tour operators, travel consultants, vacation planning, Darjeeling tour package, Sikkim tour package, Kalimpong tour, Gangtok holiday, Siliguri travel agency, North East India tours, Bhutan tour package, Nepal travel package, customized travel packages, affordable tour packages, travel booking online, all inclusive tour packages, travel offers, tour and travel agency near me, best travel planners, holiday planners, trip advisors, travel services, hotel booking, cab rental service, hill station packages, beach holidays, wildlife tours, cultural tours, heritage tours, pilgrimage tours, corporate tour packages, student tour packages, solo travel packages, last minute travel deals, airport transfers, travel agency in Siliguri, North Bengal travel agents, West Bengal tour operators">
    <meta name="author" content="SK Travel Planners">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://sktravelplanner.com/">
    

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn previews) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SK Travel Planners">
   <meta property="og:title" content="SK Travel Planners | Best Tour & Travel Agency – Holiday Packages & Custom Itineraries">
    <meta property="og:description" content="Book affordable tour packages, custom itineraries & holiday deals with SK Travel Planners. Expert-curated trips to North Bengal, Sikkim, Darjeeling, Bhutan & more. WhatsApp us now!">
    <meta property="og:url" content="https://sktravel-planners.com/">
    <meta property="og:image" content="https://picsum.photos/seed/sktravel-og/1200/630.jpg">
    <meta property="og:site_name" content="SK Travel Planners">
    <meta property="og:image:alt" content="SK Travel Planners: curated travel itineraries">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="SK Travel Planners | Best Tour & Travel Agency – Holiday Packages & Custom Itineraries">
    <meta name="twitter:description" content="Book affordable tour packages, custom itineraries & holiday deals with SK Travel Planners. North Bengal, Sikkim, Darjeeling, Bhutan & more.">
    <meta name="twitter:image" content="https://picsum.photos/seed/sktravel-og/1200/630.jpg">
    
    <!-- Mobile / branding -->
    <meta name="theme-color" content="#2d1f3d">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <!-- Geo Tags for Local SEO -->
    <meta name="geo.region" content="IN-WB">
    <meta name="geo.placename" content="Alipurduar">
    <meta name="geo.position" content="26.489;89.527">
    <meta name="ICBM" content="26.489, 89.527">
     <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "TravelAgency",
        "name": "SK Travel Planners",
        "alternateName": "SKTravel",
        "url": "https://sktravelplanners.com",
        "logo": "https://picsum.photos/seed/sklogo/200/200.jpg",
        "description": "Best tour and travel agency offering custom itineraries, holiday packages, honeymoon packages, adventure tours and vacation planning for North Bengal, Sikkim, Darjeeling, Bhutan and more.",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Alipurduar",
            "addressRegion": "West Bengal",
            "addressCountry": "IN"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude":26.489,
            "longitude":89.527
        },
        "telephone": "+91-7810807552",
        "priceRange": "₹₹",
        "areaServed": ["North Bengal", "Sikkim", "Darjeeling", "Kalimpong", "Gangtok", "Bhutan", "Nepal", "North East India"],
        "sameAs": ["https://wa.me/917810807552"],
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Tour Packages",
            "itemListElement": [
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Darjeeling Tour Package"}},
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Sikkim Holiday Package"}},
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "North Bengal Adventure Tour"}},
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Bhutan Group Tour Package"}},
                {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Honeymoon Vacation Package"}}
            ]
        }
    }
    </script>

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://sktravelplanners.com/"},
            {"@type": "ListItem", "position": 2, "name": "Itineraries", "item": "https://sktravelplanners.com/#Itineraries"},
        ]
    }
    </script>

    <!-- FAQ Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What tour packages does SK Travel Planners offer?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "SK Travel Planners offers a wide range of tour packages including Darjeeling tour packages, Sikkim holiday packages, North Bengal adventure tours, Bhutan group tours, Nepal vacation packages, honeymoon packages, family tour packages, and custom itineraries for all budgets."
                }
            },
            {
                "@type": "Question",
                "name": "How to book a trip with SK Travel Planners?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "You can book your trip easily by chatting with us on WhatsApp or Our chatbot. Our travel consultants will help you plan your custom itinerary, choose handpicked stays, and finalize your tour package within minutes."
                }
            },
            {
                "@type": "Question",
                "name": "Is SK Travel Planners the best travel agency in North Bengal?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, SK Travel Planners is rated as one of the best travel agencies in North Bengal and Siliguri, offering expert-curated day-wise itineraries, affordable pricing, local experiences, and 24/7 support for all tour packages."
                }
            }
        ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
    <!-- Lets CSS know JavaScript is running (skeletons / fade-ins only apply then) -->
    <script>document.documentElement.classList.add('js');</script>

    <title><?= e(APP_NAME) ?> — Discover Your Next Adventure</title>

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
            href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap"
            rel="stylesheet"
    >
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="extra.css">
    <link rel="stylesheet" href="carousel-extras.css">
    <?php if (!empty($heroSlides)): ?>
    <link rel="preload" as="image" href="<?= e($heroSrc($heroSlides[0], 1600)) ?>"
          <?php if ($heroSrcset($heroSlides[0])): ?>imagesrcset="<?= e($heroSrcset($heroSlides[0])) ?>" imagesizes="100vw"<?php endif; ?>
          fetchpriority="high">
    <?php endif; ?>

                <!-- Favicons -->
        <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
        <link rel="icon" href="assets/icons/favicon.ico">

        <!-- Apple Touch Icon -->
        <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon.png">
    <!-- Font Awesome loads without blocking first paint -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        media="print"
        onload="this.media='all'"
    >
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    </noscript>
    
</head>

<body>

<div class="page-loader" id="pageLoader" role="status" aria-label="Loading page">
    <span class="page-loader-spinner" aria-hidden="true"></span>
</div>

<!-- =========================================================
     NAVBAR
========================================================= -->
<nav class="navbar">
    <div class="container navbar-inner">

        <a href="index.php" class="navbar-brand">
            <span class="brand-icon"> <img src="logo.jpg" alt="Logo"></span>
            <?= e(APP_NAME) ?>
        </a>

        <ul class="navbar-nav">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="#itineraries">Itineraries</a></li>
            <li>
                <a href="admin/index.php">
                    <i class="fas fa-lock"></i> Admin
                </a>
            </li>
        </ul>

    </div>
</nav>

<!-- =========================================================
     HERO
========================================================= -->
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

                <a
                    href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappDefaultMessage ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-whatsapp btn-lg"
                >
                    <i class="fab fa-whatsapp"></i>
                    Plan on WhatsApp
                </a>
            </div>

        </div>
    </div>

    <div class="hc-place" aria-live="polite"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span></span></div>

    <div class="hc-controls">
        <button type="button" class="hc-btn hc-prev" aria-label="Previous slide"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
        <div class="hc-dots" role="group" aria-label="Choose slide"></div>
        <button type="button" class="hc-btn hc-toggle" aria-label="Pause slideshow"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg></button>
        <button type="button" class="hc-btn hc-next" aria-label="Next slide"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button>
    </div>

    <div class="hc-progress" aria-hidden="true"><span></span></div>

</section>

<?php if (!empty($bestSellers)): ?>
<section class="section bs-section" id="best-sellers">
    <div class="container">
        <?php
// Trust line numbers from real reviews (hidden when there are none)
        $bsReviewTotal = 0; $bsWeighted = 0.0;
        foreach ($ratings as $r) {
            $bsReviewTotal += $r['count'];
            $bsWeighted    += $r['avg'] * $r['count'];
        }
        $bsOverallAvg = $bsReviewTotal ? $bsWeighted / $bsReviewTotal : 0;
        ?>
        <div class="bs-heading">
            <div class="bs-heading-text">
                <span class="bs-eyebrow"><i class="fas fa-award" aria-hidden="true"></i> Traveller Favourites</span>
                <h2>Our Best-Selling <span>Tour Packages</span></h2>
                <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
                <p>Handpicked journeys that travellers book again and again, with expert-planned
                itineraries, trusted stays and dependable local support.</p>

                <?php if ($bsReviewTotal): ?>
                <ul class="bs-trust">
                    <li><i class="fas fa-star" aria-hidden="true"></i>
                        <b><?= number_format($bsOverallAvg, 1) ?>/5</b> average rating</li>
                    <li><i class="fas fa-users" aria-hidden="true"></i>
                        <b><?= (int)$bsReviewTotal ?>+</b> traveller reviews</li>
                    <li><i class="fas fa-headset" aria-hidden="true"></i>
                        <b>24/7</b> trip support</li>
                </ul>
                <?php endif; ?>
            </div>

            <a href="#itineraries" class="bs-viewall">
                View all packages <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="bs-carousel" id="bsCarousel" aria-roledescription="carousel" aria-label="Best selling packages">
            <button type="button" class="bs-arrow bs-prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>

            <div class="bs-track" id="bsTrack">
                <?php foreach ($bestSellers as $n => $b):
                    $rt = $ratings[(int)$b['id']] ?? null; ?>
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

            <button type="button" class="bs-arrow bs-next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="bs-dots" id="bsDots" role="group" aria-label="Choose slide"></div>
    </div>
</section>
<?php endif; ?>
<!-- =========================================================
     ITINERARIES
========================================================= -->
<section class="section" id="itineraries">

    <div class="container">

        <div class="bs-heading">
            <div class="bs-heading-text">
                <h2>Primary<span>Travel Destinations</span></h2>
                <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
                <?php if (!empty($primaryDestinations)): ?>
                    <div class="dc-wrap">
                        <button type="button" class="dc-arrow dc-prev" aria-label="Previous destinations" hidden>
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <div class="dest-chips dc-track" id="destChips" role="group" aria-label="Popular destinations">

                            <button type="button" class="dest-chip dest-card is-active is-all" data-dest="">
                                <span class="dc-img dc-all"><i class="fas fa-earth-asia"></i></span>
                                <span class="dc-info">
                                    <strong>All Destinations</strong>
                                    <small><?= count($itineraries) ?> packages</small>
                                </span>
                            </button>

                            <?php foreach ($primaryDestinations as $d): ?>
                            <button type="button" class="dest-chip dest-card" data-dest="<?= e($d['label']) ?>">
                                <span class="dc-img">
                                    <img src="<?= e(imageUrl($d['image'])) ?>" alt="" loading="lazy" decoding="async">
                                </span>
                                <span class="dc-info">
                                    <strong><?= e($d['label']) ?></strong>
                                    <small><?= (int)$d['count'] ?> package<?= $d['count'] > 1 ? 's' : '' ?></small>
                                </span>
                            </button>
                            <?php endforeach; ?>

                        </div>

                        <button type="button" class="dc-arrow dc-next" aria-label="Next destinations">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <?php endif; ?>
            </div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-wrapper">


            <div class="filter-title">
                <i class="fas fa-sliders"></i>
                <span>Find Your Perfect Package</span>
            </div>
            <div class="filter-controls">

                <div class="price-group">
                    <label for="priceMin">
                        <span><i class="fas fa-indian-rupee-sign"></i> Price Range</span>
                        <output class="ps-value" id="priceValue" for="priceMin priceMax">Any price</output>
                    </label>

                    <div class="price-slider" id="priceSlider">
                        <div class="ps-track"><div class="ps-fill"></div></div>
                        <input type="range" id="priceMin" aria-label="Minimum price"
                               min="<?= $priceMin ?>" max="<?= $priceMax ?>" step="500" value="<?= $priceMin ?>">
                        <input type="range" id="priceMax" aria-label="Maximum price"
                               min="<?= $priceMin ?>" max="<?= $priceMax ?>" step="500" value="<?= $priceMax ?>">
                    </div>

                    <div class="ps-scale" aria-hidden="true">
                        <span id="psMinLabel"></span>
                        <span id="psMaxLabel"></span>
                    </div>

                    <!-- Hidden: the existing filter code in app.js reads this -->
                    <select id="priceFilter" hidden tabindex="-1" aria-hidden="true">
                        <option value="all">All Prices</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="destinationSearch">
                        <i class="fas fa-location-dot"></i>
                        Destination
                    </label>

                    <input
                        type="text"
                        id="destinationSearch"
                        placeholder="Search destination..."
                        autocomplete="off"
                    >
                </div>

                <button type="button" class="filter-reset" id="resetFilters">
                    <i class="fas fa-rotate-left"></i>
                    Reset
                </button>

            </div>

            <div class="active-filters" id="activeFilters" aria-live="polite"></div>

            <div class="filter-result" aria-live="polite">
                Showing
                <strong id="resultCount"><?= min($pageSize, count($itineraries)) ?></strong>
                of
                <strong id="totalCount"><?= count($itineraries) ?></strong>
                package(s)
            </div>

        </div>

        <div class="bs-heading">
            <div class="bs-heading-text">
                <span class="bs-eyebrow is-purple"><i class="fas fa-map-location-dot" aria-hidden="true"></i> Explore &amp; Compare</span>
                <h2>Handicrafted<span>Travel Packages</span></h2>
                <span class="bs-divider" aria-hidden="true"><i></i><i class="fas fa-compass"></i><i></i></span>
                <p>Every journey is planned by local experts, with clear day-wise schedules,
                carefully chosen stays and authentic local experiences. Filter by destination
                and budget to find the trip that fits you.</p>

                <ul class="bs-trust">
                    <li><i class="fas fa-calendar-check" aria-hidden="true"></i> <b>Day-wise</b> plans</li>
                    <li><i class="fas fa-hotel" aria-hidden="true"></i> <b>Handpicked</b> stays</li>
                    <li><i class="fas fa-user-tie" aria-hidden="true"></i> <b>Local</b> experts</li>
                    <li><i class="fas fa-pen-ruler" aria-hidden="true"></i> <b>Fully</b> customisable</li>
                </ul>
            </div>
        </div>
        <?php if (empty($itineraries)): ?>

            <div class="empty-state">
                <i class="fas fa-suitcase-rolling"></i>
                <p>No itineraries available yet. Check back soon!</p>
            </div>

        <?php else: ?>

            <!-- SKELETON PLACEHOLDERS (only visible while JS prepares the grid) -->
            <div class="card-grid skeleton-grid" id="skeletonGrid" aria-hidden="true">
                <?php for ($s = 0; $s < min($pageSize, count($itineraries)); $s++): ?>
                    <div class="sk-card">
                        <div class="skeleton sk-img"></div>
                        <div class="sk-body">
                            <div class="skeleton sk-line lg"></div>
                            <div class="skeleton sk-line sm"></div>
                            <div class="skeleton sk-line md"></div>
                            <div class="skeleton sk-line"></div>
                            <div class="sk-row">
                                <div class="skeleton sk-line price"></div>
                                <div class="skeleton sk-btn"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="card-grid is-loading" id="itineraryGrid">

                <?php foreach ($itineraries as $index => $itin): ?>

                    <?php
                        $highlights = json_decode($itin['highlights'], true) ?? [];
                        $days       = json_decode($itin['day_plan'], true) ?? [];
                        $price      = (float)$itin['price'];

                        // First batch loads immediately, the rest are lazy-loaded
                        $eager = $index < $pageSize;
                    ?>

                    <div
                        class="itin-card itinerary-item"
                        data-price="<?= $price ?>"
                        data-destination="<?= e(strtolower($itin['destination'])) ?>"
                        data-title="<?= e(strtolower($itin['title'])) ?>"
                    >

                        <!-- IMAGE -->
                        <div class="itin-card-img">

                            <span class="img-shimmer skeleton" aria-hidden="true"></span>

                            <img
                                src="<?= e(imageUrl($itin['image'])) ?>"
                                alt="<?= e($itin['title']) ?>"
                                loading="<?= $eager ? 'eager' : 'lazy' ?>"
                                decoding="async"
                                <?= $index === 0 ? 'fetchpriority="high"' : '' ?>
                            >

                            <span class="itin-card-badge">
                                <?= (int)$itin['duration_days'] ?> Days
                            </span>

                            <?php $rt = $ratings[(int)$itin['id']] ?? null; ?>
                            <span
                                class="rating-pill<?= $rt ? '' : ' is-new' ?>"
                                aria-label="<?= $rt ? 'Rated ' . number_format($rt['avg'], 1) . ' out of 5 by ' . $rt['count'] . ' travellers' : 'No ratings yet' ?>"
                            >
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <?php if ($rt): ?>
                                    <b><?= number_format($rt['avg'], 1) ?></b><em>(<?= $rt['count'] ?>)</em>
                                <?php else: ?>
                                    <b>New</b>
                                <?php endif; ?>
                            </span>

                        </div>

                        <!-- BODY -->
                        <div class="itin-card-body">

                            <h3><?= e($itin['title']) ?></h3>

                            <div class="dest">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= e($itin['destination']) ?>
                            </div>

                            <p class="desc"><?= e($itin['description']) ?></p>

                            <div class="itin-card-meta">
                                <span>
                                    <i class="fas fa-calendar-alt"></i>
                                    <?= (int)$itin['duration_days'] ?> Days
                                </span>
                                <span>
                                    <i class="fas fa-star"></i>
                                    <?= count($highlights) ?> Highlights
                                </span>
                                <span>
                                    <i class="fas fa-route"></i>
                                    <?= count($days) ?> Stops
                                </span>
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
                                    <?php if ($rt): ?>
                                        <b><?= number_format($rt['avg'], 1) ?></b> · <?= $rt['count'] ?> rating<?= $rt['count'] === 1 ? '' : 's' ?>
                                    <?php else: ?>
                                        No ratings yet
                                    <?php endif; ?>
                                </span>
                                <span class="rate-hint">Tap a star to rate</span>
                            </div>

                            <div class="itin-card-footer">
                                <span class="itin-price">
                                    <?= e(formatPrice($itin['price'])) ?>
                                </span>

                                <a
                                    href="detail.php?id=<?= (int)$itin['id'] ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    View Details
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

                <!-- NO FILTER RESULTS -->
                <div id="noFilterResults" class="filter-no-results" style="display:none;">
                    <i class="fas fa-map-signs"></i>
                    <h3>No packages found</h3>
                    <p>Try another price range or destination.</p>
                    <button type="button" class="btn btn-primary" id="resetFiltersEmpty">
                        Reset Filters
                    </button>
                </div>

            </div>

            <!-- LOAD MORE -->
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
<br>
<!-- =========================================================
     WHATSAPP CTA SECTION
========================================================= -->
<section class="whatsapp-section">

    <div class="container">

        <div class="whatsapp-card">

            <div class="whatsapp-icon-large">
                <i class="fab fa-whatsapp"></i>
            </div>

            <div class="whatsapp-content">
                <span class="whatsapp-label">NEED HELP PLANNING?</span>
                <h2>Let's Plan Your Perfect Trip</h2>
                <p>
                    Tell us your destination, travel dates,
                    number of travellers and budget.
                    Our team can help you build the right itinerary.
                </p>
            </div>

            <a
                href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappDefaultMessage ?>"
                target="_blank"
                rel="noopener"
                class="btn btn-whatsapp btn-lg"
            >
                <i class="fab fa-whatsapp"></i>
                WhatsApp
            </a>

        </div>

    </div>

</section>

<!-- NEW FAQ SECTION -->
        <div id="faq" class="footer-faq" style="margin-top: 40px; margin-bottom: 40px;">
            <h4 style="text-align:center; margin-bottom: 25px; font-size: 24px;">Frequently Asked Questions</h4>
            <div style="max-width: 800px; margin: 0 auto;">
                
                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        How do I book a tour with SK Travel Planners?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">Booking your dream trip is easy! You can start by browsing our itineraries and clicking "Inquire". Alternatively, reach out via email or phone. Process: 1. Consult (share dates/preferences), 2. Customize (we design & quote), 3. Confirm (secure with a deposit), 4. Prepare (we handle the logistics)!</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex;; justify-content: space-between; align-items: center;">
                        Are the tour prices per person or per group?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">All listed tour prices are <strong>per person</strong>, typically based on double/twin occupancy. Solo travelers will have a single supplement fee. However, if you are a private group booking a customized tour together, we can provide a flat group package rate upon request.</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        What's included in the tour price?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">Standard tours include: Accommodation, private air-conditioned transport with a chauffeur, local expert guides, daily breakfast, and all listed monument entrance fees. <em>Note: International flights, visa fees, and personal expenses/tips are generally not included.</em></p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        Can I customize a tour to my preferences?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;"><strong>Absolutely!</strong> Customization is our specialty. You can adjust the duration, upgrade hotels, add specific activities (like cooking classes or yoga retreats), or change the route. Just tell us what you envision, and we’ll build it.</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        What is the cancellation policy?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">Our standard policy (from date of departure): 60+ days prior: Deposit refunded (minus admin fee). 30-59 days: 50% non-refundable. 0-29 days: 100% non-refundable. *Peak season/luxury train bookings may have stricter policies. We strongly recommend travel insurance.*</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        Do you provide visa assistance?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">We provide <strong>comprehensive visa assistance</strong>. While we don't process visas directly, we supply all necessary supporting documents (hotel vouchers, itinerary) and guide you step-by-step through the Indian e-Visa online process.</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        Are India tours safe for solo female travelers?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">Yes! At SK Travel Planners, we prioritize your safety by providing vetted private transport, safe centrally-located hotels, professionally trained guides, and 24/7 on-ground support. You can also request female guides in certain cities for added comfort.</p>
                </details>

                <details style="margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 5px;">
                    <summary style="cursor: pointer; font-weight: bold; list-style: none; display: flex; justify-content: space-between; align-items: center;">
                        What is the best time to visit India?
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </summary>
                    <p style="margin-top: 15px; line-height: 1.6;">Generally, <strong>October to March</strong> is best for most of the country (Golden Triangle, Rajasthan, South India). April to June is ideal for the Himalayas. July to September is the monsoon season—great for lush landscapes and Ayurvedic treatments in Kerala.</p>
                </details>

            </div>
        </div>
        <!-- END FAQ SECTION -->
<!-- =========================================================
     FOOTER
========================================================= -->
<!--<footer class="footer">-->

<!--    <div class="container">-->

<!--        <div class="footer-grid">-->

<!--            <div>-->
<!--                <h4>-->
<!--                <img class="footer-logo" src="logo.jpg" alt="Logo">-->
<!--                    <?= e(APP_NAME) ?>-->
<!--                </h4>-->
<!--                <p style="max-width:320px;">-->
<!--                    Your trusted partner for unforgettable-->
<!--                    travel experiences. Expert-crafted itineraries-->
<!--                    for destinations across the globe.-->
<!--                </p>-->
<!--            </div>-->

<!--            <div>-->
<!--                <h4>Quick Links</h4>-->
<!--                <ul style="list-style:none;">-->
<!--                    <li style="margin-bottom:8px;"><a href="index.php">Home</a></li>-->
<!--                    <li style="margin-bottom:8px;"><a href="#itineraries">Itineraries</a></li>-->
<!--                    <li style="margin-bottom:8px;"><a href="admin/index.php">Admin Panel</a></li>-->
<!--                </ul>-->
<!--            </div>-->

<!--            <div>-->
<!--                <h4>Contact</h4>-->
<!--                <p><i class="fas fa-envelope"></i> <a href="mailto:info@sktravelplanners.in">sktravelplanners@gmail.com</a></p>-->
<!--                <p><i class="fab fa-whatsapp"></i> <a href="https://wa.me/<?= $whatsappNumber ?>" target="_blank" rel="noopener noreferrer">+91 78108 07552</a></p>-->

<!--                <a-->
<!--                    href="https://wa.me/<?= $whatsappNumber ?>"-->
<!--                    target="_blank"-->
<!--                    rel="noopener"-->
<!--                    class="footer-whatsapp"-->
<!--                >-->
<!--                    <i class="fab fa-whatsapp"></i>-->
<!--                    WhatsApp Us-->
<!--                </a>-->
<!--            </div>-->

<!--        </div>-->

<!--        <div class="footer-bottom">-->
<!--            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.-->
<!--            <a href="privicy-policy.html" target="_blank">Terms & Conditions</a>-->
<!--           &nbsp; &nbsp; <span> <a href = "https://e-websolutions.netlify.app/" target="_blank">Maintained by e-WebSolutions</a></span>-->
<!--        </div>-->

<!--    </div>-->

<!--</footer>-->
<footer class="footer">

    <div class="container">

        <div class="footer-grid">

            <div>
                <h4>
                <img class="footer-logo" src="logo.jpg" alt="Logo">
                    <?= e(APP_NAME) ?>
                </h4>
                <p style="max-width:320px;">
                    Your trusted partner for unforgettable
                    travel experiences. Expert-crafted itineraries
                    for destinations across the globe.
                </p>
            </div>

            <div>
                <h4>Quick Links</h4>
                <ul style="list-style:none; padding-left:0;">
                    <li style="margin-bottom:8px;"><a href="index.php">Home</a></li>
                    <li style="margin-bottom:8px;"><a href="#itineraries">Itineraries</a></li>
                    <li style="margin-bottom:8px;"><a href="#faq">FAQ</a></li> <!-- NEW FAQ LINK -->
                    <li style="margin-bottom:8px;"><a href="admin/index.php">Admin Panel</a></li>
                </ul>
            </div>

            <div>
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> <a href="mailto:info@sktravelplanners.in">info@sktravelplanners.in</a></p>
                <p><i class="fab fa-whatsapp"></i> <a href="https://wa.me/<?= $whatsappNumber ?>" target="_blank" rel="noopener noreferrer">+91 78108 07552</a></p>

                <a
                    href="https://wa.me/<?= $whatsappNumber ?>"
                    target="_blank"
                    rel="noopener"
                    class="footer-whatsapp"
                >
                    <i class="fab fa-whatsapp"></i>
                    WhatsApp Us
                </a>
            </div>

        </div>

        

        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
            <a href="privicy-policy.html" target="_blank">Terms & Conditions</a>
           &nbsp; &nbsp; <span> <a href = "https://e-websolutions.netlify.app/" target="_blank">Maintained by e-WebSolutions</a></span>
        </div>

    </div>

</footer>
<!-- =========================================================
     FLOATING WHATSAPP BUTTON
========================================================= -->
<a
    href="https://wa.me/<?= $whatsappNumber ?>?text=<?= $whatsappDefaultMessage ?>"
    target="_blank"
    rel="noopener"
    class="floating-whatsapp"
    aria-label="Chat on WhatsApp"
>
    <i class="fab fa-whatsapp"></i>
</a>

<!-- =========================================================
     CHATBOT
========================================================= -->
<div class="chatbot">

    <!-- Gentle one-time nudge -->
    <div class="chatbot-nudge" id="chatbotNudge" hidden>
        <button type="button" class="chatbot-nudge-close" id="chatbotNudgeClose" aria-label="Dismiss">
            <i class="fas fa-times"></i>
        </button>
        <span>👋 Not sure where to go? Tell me your budget and I'll suggest a trip.</span>
    </div>

    <!-- CHAT BUTTON -->
    <button
        type="button"
        class="chatbot-toggle"
        id="chatbotToggle"
        aria-label="Open travel assistant"
        aria-expanded="false"
        aria-controls="chatbotWindow"
    >
        <i class="fas fa-comments"></i>
        <span class="chatbot-notification" id="chatbotBadge">1</span>
    </button>

    <!-- CHAT WINDOW -->
    <div
        class="chatbot-window"
        id="chatbotWindow"
        role="dialog"
        aria-label="Travel assistant"
        aria-hidden="true"
    >

        <div class="chatbot-header">

            <div class="chatbot-header-info">
                <div class="chatbot-avatar">
                    <i class="fas fa-headset"></i>
                </div>
                <div>
                    <strong>Travel Assistant</strong>
                    <small><span class="online-dot"></span> Online</small>
                </div>
            </div>

            <div class="chatbot-header-actions">
                <button type="button" id="chatbotClear" class="chatbot-close" aria-label="Restart chat" title="Restart chat">
                    <i class="fas fa-rotate-right"></i>
                </button>
                <button type="button" id="chatbotClose" class="chatbot-close" aria-label="Close chatbot">
                    <i class="fas fa-times"></i>
                </button>
            </div>

        </div>

        <div class="chatbot-messages" id="chatbotMessages" aria-live="polite">

            <div class="bot-message" id="chatbotWelcome">
                <div class="message-avatar"><i class="fas fa-robot"></i></div>

                <div class="message-content">
                    👋 Hello! Welcome to <?= e(APP_NAME) ?>.

                    <br><br>

                    Tell me your <strong>budget</strong>, <strong>destination</strong>
                    or <strong>trip length</strong> — for example
                    <em>“5 day trip under 20k”</em> — and I'll find matching packages.

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
            <input
                type="text"
                id="chatbotInput"
                placeholder="Ask about your trip..."
                autocomplete="off"
                maxlength="300"
            >
            <button type="button" id="chatbotSend" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>

        <div class="chatbot-footer">
            <span>Powered by <?= e(APP_NAME) ?></span>
            
        </div>

    </div>

</div>

<!-- =========================================================
     JAVASCRIPT
========================================================= -->
<script>
    window.APP = <?= json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="app.js" defer></script>
<style>
/* ---------- destination chips ---------- */
.dest-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.dest-chip{border:1px solid rgba(0,0,0,.15);background:#fff;color:inherit;border-radius:999px;
  padding:7px 14px;font:inherit;font-size:.9rem;cursor:pointer;transition:.2s}
.dest-chip em{font-style:normal;font-size:.75rem;opacity:.6;margin-left:4px}
.dest-chip:hover{border-color:#2d1f3d}
.dest-chip.is-active{background:#2d1f3d;color:#fff;border-color:#2d1f3d}

/* ---------- best-seller carousel ---------- */
.bs-carousel{position:relative}
.bs-track{display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;
  padding:6px 2px 14px;scrollbar-width:none}
.bs-track::-webkit-scrollbar{display:none}
.bs-card{flex:0 0 calc((100% - 40px)/3);scroll-snap-align:start;background:#fff;border-radius:14px;
  overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.09);display:flex;flex-direction:column}
.bs-img{position:relative;aspect-ratio:16/10;background:#ddd}
.bs-img img{width:100%;height:100%;object-fit:cover;display:block}
.bs-badge{position:absolute;top:12px;left:12px;background:#e8590c;color:#fff;font-size:.75rem;
  font-weight:600;padding:5px 10px;border-radius:999px}
.bs-days{position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,.65);color:#fff;
  font-size:.75rem;padding:4px 10px;border-radius:999px}
.bs-body{padding:16px;display:flex;flex-direction:column;gap:6px;flex:1}
.bs-body h3{font-size:1.1rem;margin:0}
.bs-dest,.bs-rate{font-size:.88rem;opacity:.8}
.bs-rate i{color:#f5a623}
.bs-foot{margin-top:auto;display:flex;justify-content:space-between;align-items:center;padding-top:10px}
.bs-price{font-weight:700;font-size:1.1rem}
.bs-arrow{position:absolute;top:40%;transform:translateY(-50%);z-index:2;width:42px;height:42px;
  border-radius:50%;border:0;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer}
.bs-prev{left:-14px}.bs-next{right:-14px}
.bs-dots{display:flex;justify-content:center;gap:8px;margin-top:8px}
.bs-dots button{width:9px;height:9px;border-radius:50%;border:0;background:#c9c9c9;padding:0;cursor:pointer}
.bs-dots button.on{background:#2d1f3d;width:24px;border-radius:6px}
@media(max-width:900px){.bs-card{flex-basis:calc((100% - 20px)/2)}}
@media(max-width:600px){.bs-card{flex-basis:88%}.bs-arrow{display:none}}
/* ---------- best-seller heading ---------- */
.bs-heading{position:relative;text-align:center;max-width:760px;margin:0 auto 38px}
.bs-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 16px;border-radius:999px;
  background:rgba(232,89,12,.1);color:#e8590c;font-size:.78rem;font-weight:700;
  letter-spacing:.14em;text-transform:uppercase}
.bs-heading h2{margin:14px 0 10px;font-size:clamp(1.8rem,3.6vw,2.6rem);line-height:1.15;
  color:#2d1f3d;letter-spacing:-.01em}
.bs-heading h2 span{color:#e8590c}
.bs-divider{display:flex;align-items:center;justify-content:center;gap:12px;margin:0 auto 14px;color:#e8590c}
.bs-divider i:not(.fas){display:block;width:56px;height:2px;
  background:linear-gradient(90deg,transparent,#e8590c)}
.bs-divider i:last-child{background:linear-gradient(270deg,transparent,#e8590c)}
.bs-heading p{margin:0 auto;max-width:600px;font-size:1.05rem;line-height:1.65;opacity:.75}

.bs-trust{list-style:none;display:flex;flex-wrap:wrap;justify-content:center;gap:10px 26px;
  margin:22px 0 0;padding:14px 0 0;border-top:1px solid rgba(0,0,0,.08);font-size:.9rem}
.bs-trust li{display:flex;align-items:center;gap:8px}
.bs-trust i{color:#e8590c}
.bs-trust b{color:#2d1f3d}

.bs-viewall{position:absolute;right:0;bottom:0;display:inline-flex;align-items:center;gap:8px;
  font-weight:600;font-size:.92rem;color:#2d1f3d;text-decoration:none;
  border-bottom:2px solid #e8590c;padding-bottom:2px;transition:gap .2s}
.bs-viewall:hover{gap:12px}
/* keep the link from overlapping the centred text on smaller screens */
@media(max-width:1100px){
  .bs-viewall{position:static;display:flex;justify-content:center;width:max-content;margin:20px auto 0}
}
/* ---------- destination card carousel ---------- */
.dc-wrap{position:relative;margin:4px 0 22px}
.dest-chips.dc-track{display:flex;flex-wrap:nowrap;gap:14px;overflow-x:auto;margin:0;
  padding:6px 2px 10px;scroll-snap-type:x proximity;scroll-behavior:smooth;scrollbar-width:none}
.dc-track::-webkit-scrollbar{display:none}

.dest-chip.dest-card{flex:0 0 190px;scroll-snap-align:start;display:flex;flex-direction:column;
  padding:0;border-radius:14px;overflow:hidden;text-align:left;background:#fff;
  border:2px solid transparent;box-shadow:0 4px 14px rgba(0,0,0,.1);
  transition:transform .2s,box-shadow .2s,border-color .2s}
.dest-card:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(0,0,0,.16)}
.dest-card.is-active{background:#fff;color:inherit;border-color:#e8590c}

.dc-img{display:block;height:100px;background:#ddd;overflow:hidden}
.dc-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.dest-card:hover .dc-img img{transform:scale(1.08)}
.dc-all{display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;
  background:linear-gradient(135deg,#2d1f3d,#e8590c)}

.dc-info{display:flex;flex-direction:column;gap:2px;padding:10px 12px}
.dc-info strong{font-size:.95rem;color:#2d1f3d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dc-info small{font-size:.78rem;opacity:.65}
.dest-card.is-active .dc-info strong{color:#e8590c}

.dc-arrow{position:absolute;top:42%;transform:translateY(-50%);z-index:2;width:38px;height:38px;
  border-radius:50%;border:0;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer}
.dc-prev{left:-12px}.dc-next{right:-12px}
.dc-arrow[hidden]{display:none}

@media(max-width:600px){
  .dest-chip.dest-card{flex-basis:150px}
  .dc-img{height:84px}
  .dc-arrow{display:none}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Destination chips: drive the existing filter ---------- */
    var chips  = document.querySelectorAll('#destChips .dest-chip');
    var search = document.getElementById('destinationSearch');

    function markChip(value) {
        var v = (value || '').trim().toLowerCase();
        chips.forEach(function (c) {
            c.classList.toggle('is-active', c.dataset.dest.toLowerCase() === v);
        });
    }
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (!search) return;
            search.value = chip.dataset.dest;
            search.dispatchEvent(new Event('input', { bubbles: true }));  // triggers app.js filter
            markChip(chip.dataset.dest);
            document.getElementById('itineraryGrid')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    if (search) search.addEventListener('input', function () { markChip(search.value); });
    ['resetFilters', 'resetFiltersEmpty'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('click', function () { markChip(''); });
    });
    
    /* ---------- Destination card carousel ---------- */
var dcTrack = document.getElementById('destChips');
if (dcTrack) {
    var dcPrev = document.querySelector('.dc-prev');
    var dcNext = document.querySelector('.dc-next');
    var dcStep = function () { return Math.max(200, dcTrack.clientWidth * 0.8); };

    function dcArrows() {
        var max = dcTrack.scrollWidth - dcTrack.clientWidth - 4;
        dcPrev.hidden = dcTrack.scrollLeft <= 4;
        dcNext.hidden = dcTrack.scrollLeft >= max;
    }
    dcPrev.addEventListener('click', function () { dcTrack.scrollBy({ left: -dcStep(), behavior: 'smooth' }); });
    dcNext.addEventListener('click', function () { dcTrack.scrollBy({ left:  dcStep(), behavior: 'smooth' }); });
    dcTrack.addEventListener('scroll', function () { requestAnimationFrame(dcArrows); }, { passive: true });
    window.addEventListener('resize', dcArrows);
    dcArrows();

    // keep the selected card visible
    dcTrack.addEventListener('click', function (e) {
        var card = e.target.closest('.dest-card');
        if (card) card.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    });
}
    /* ---------- Best-seller carousel ---------- */
    var track = document.getElementById('bsTrack');
    if (!track) return;
    var cards = track.querySelectorAll('.bs-card');
    var dots  = document.getElementById('bsDots');
    var root  = document.getElementById('bsCarousel');
    var timer;

    function perView() { return Math.max(1, Math.round(track.clientWidth / cards[0].getBoundingClientRect().width)); }
    function step()    { return cards[0].getBoundingClientRect().width + 20; }
    function pages()   { return Math.max(1, cards.length - perView() + 1); }

    function buildDots() {
        dots.innerHTML = '';
        for (var i = 0; i < pages(); i++) {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('aria-label', 'Slide ' + (i + 1));
            (function (idx) { b.addEventListener('click', function () { go(idx); restart(); }); })(i);
            dots.appendChild(b);
        }
        sync();
    }
    function sync() {
        var idx = Math.round(track.scrollLeft / step());
        dots.querySelectorAll('button').forEach(function (d, i) { d.classList.toggle('on', i === idx); });
    }
    function go(i)  { track.scrollTo({ left: i * step(), behavior: 'smooth' }); }
    function next() {
        var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
        atEnd ? go(0) : track.scrollBy({ left: step(), behavior: 'smooth' });
    }
    function prev() {
        track.scrollLeft <= 4 ? go(pages() - 1) : track.scrollBy({ left: -step(), behavior: 'smooth' });
    }
    function start()   { timer = setInterval(next, 4500); }
    function stop()    { clearInterval(timer); }
    function restart() { stop(); start(); }

    root.querySelector('.bs-next').addEventListener('click', function () { next(); restart(); });
    root.querySelector('.bs-prev').addEventListener('click', function () { prev(); restart(); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('touchstart', stop, { passive: true });
    root.addEventListener('touchend', start, { passive: true });
    window.addEventListener('resize', buildDots);

    buildDots();
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) start();
});
</script>
<script src="carousel-extras.js" defer></script>

</body>
</html>