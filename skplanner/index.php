<?php
require_once 'config.php';

// Fetch all active itineraries
$stmt = $pdo->prepare("SELECT * FROM itineraries WHERE status = 1 ORDER BY created_at DESC");
$stmt->execute();
$itineraries = $stmt->fetchAll();

// WhatsApp number - country code + number, without + or spaces
$whatsappNumber = '919876543210';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Lets CSS know JavaScript is running (skeletons / fade-ins only apply then) -->
    <script>document.documentElement.classList.add('js');</script>

    <title><?= e(APP_NAME) ?> — Discover Your Next Adventure</title>

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="extras.css">

            <!-- Title icon / Favicon -->
        <link rel="icon" type="image/png" sizes="32x32" href="logo.jpg">
        <link rel="icon" type="image/png" sizes="16x16" href="logo.jpg">
        <link rel="icon" type="image/x-icon" href="logo.jpg">

        <!-- Apple Touch Icon -->
        <link rel="apple-touch-icon" sizes="180x180" href="logo.jpg">
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
<section class="hero">
 
    <div class="hero-shapes">
        <div class="hero-shape" style="width:300px;height:300px;top:10%;right:10%;animation-delay:-3s;"></div>
        <div class="hero-shape" style="width:200px;height:200px;bottom:20%;left:5%;animation-delay:-7s;"></div>
        <div class="hero-shape" style="width:150px;height:150px;top:60%;right:30%;animation-delay:-12s;"></div>
    </div>
 
    <!-- Pure-CSS landscape: sun, mountains, sea, boat, hills, tourists -->
    <div class="hero-scene" aria-hidden="true">
 
        <span class="sun"></span>
 
        <span class="mountain far m5"></span>
        <span class="mountain far m1"></span>
        <span class="mountain far m2"></span>
        <span class="mountain near m3"></span>
        <span class="mountain near m4"></span>
 
        <div class="sea">
            <span class="wave w1"></span>
            <span class="wave w2"></span>
        </div>
 
        <span class="boat"><i></i></span>
 
        <div class="hill back">
            <span class="pine" style="--x:30%;--b:86%"></span>
            <span class="pine" style="--x:38%;--b:92%"></span>
            <span class="pine" style="--x:62%;--b:92%"></span>
            <span class="pine" style="--x:70%;--b:86%"></span>
            <span class="pine" style="--x:80%;--b:74%"></span>
        </div>
 
        <div class="hill front">
            <span class="tourist t1"><i class="arm"></i><i class="leg l"></i><i class="leg r"></i></span>
            <span class="tourist t3"><i class="arm"></i><i class="leg l"></i><i class="leg r"></i></span>
            <span class="tourist t2"><i class="arm"></i><i class="leg l"></i><i class="leg r"></i></span>
        </div>
 
        <div class="hill side"></div>
 
    </div>
 
    <div class="container hero-content">
 
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
 
</section>

<!-- =========================================================
     ITINERARIES
========================================================= -->
<section class="section" id="itineraries">

    <div class="container">

        <div class="section-heading">
            <h2>Handcrafted Itineraries</h2>
            <p>
                Each itinerary is meticulously planned with
                day-wise schedules, handpicked stays,
                and local experiences.
            </p>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-wrapper">

            <div class="filter-title">
                <i class="fas fa-sliders"></i>
                <span>Find Your Perfect Package</span>
            </div>

            <div class="filter-controls">

                <div class="filter-group">
                    <label for="priceFilter">
                        <i class="fas fa-indian-rupee-sign"></i>
                        Price Range
                    </label>

                    <select id="priceFilter">
                        <option value="all">All Prices</option>
                        <option value="0-10000">Under ₹10,000</option>
                        <option value="10000-20000">₹10,000 – ₹20,000</option>
                        <option value="20000-30000">₹20,000 – ₹30,000</option>
                        <option value="30000-999999999">₹30,000+</option>
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
                Chat on WhatsApp
            </a>

        </div>

    </div>

</section>

<!-- =========================================================
     FOOTER
========================================================= -->
<footer class="footer">

    <div class="container">

        <div class="footer-grid">

            <div>
                <h4>
                    <i class="fas fa-plane"></i>
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
    aria-label="Chat with us on WhatsApp"
>
    <i class="fab fa-whatsapp"></i>
    <span class="floating-whatsapp-text">Chat with us</span>
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

</body>
</html>