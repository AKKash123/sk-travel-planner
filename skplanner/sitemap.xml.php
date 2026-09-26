<?php
// ============================================
// SK Travel Planner — Dynamic XML Sitemap
// Generates sitemap URLs from the database
// ============================================

declare(strict_types=1);

// XML response must be sent before any output.
header('Content-Type: application/xml; charset=UTF-8');

// Load application configuration.
require_once __DIR__ . '/config.php';

// --------------------------------------------
// Base URL
// --------------------------------------------
$baseUrl = rtrim(APP_URL, '/');

// --------------------------------------------
// Sitemap items
// --------------------------------------------
$items = [];

// Static pages
$items[] = [
    'loc'        => $baseUrl . '/index.php',
    'changefreq' => 'weekly',
    'priority'   => '1.0',
];

$items[] = [
    'loc'        => $baseUrl . '/detail.php',
    'changefreq' => 'weekly',
    'priority'   => '0.8',
];

// --------------------------------------------
// Dynamic itinerary pages
// --------------------------------------------
try {
    $stmt = $pdo->query(
        "SELECT id, updated_at
         FROM itineraries
         WHERE status = 1
         ORDER BY updated_at DESC"
    );

    $itineraries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itineraries as $itinerary) {
        $id = (int) ($itinerary['id'] ?? 0);

        if ($id <= 0) {
            continue;
        }

        $lastmod = null;

        if (!empty($itinerary['updated_at'])) {
            $timestamp = strtotime($itinerary['updated_at']);

            if ($timestamp !== false) {
                $lastmod = date('Y-m-d', $timestamp);
            }
        }

        $item = [
            'loc'        => $baseUrl . '/detail.php?id=' . $id,
            'changefreq' => 'weekly',
            'priority'   => '0.9',
        ];

        if ($lastmod !== null) {
            $item['lastmod'] = $lastmod;
        }

        $items[] = $item;
    }
} catch (Throwable $e) {
    // Do not expose database errors in the XML sitemap.
    // Static pages will still be returned.
    error_log(
        'SK Travel Planner - Sitemap database error: ' .
        $e->getMessage()
    );
}

// --------------------------------------------
// XML helper
// --------------------------------------------
function xmlEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

// --------------------------------------------
// XML output
// --------------------------------------------
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>

<urlset
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
                        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<?php foreach ($items as $item): ?>

    <url>
        <loc><?= xmlEscape($item['loc']) ?></loc>

<?php if (!empty($item['lastmod'])): ?>
        <lastmod><?= xmlEscape($item['lastmod']) ?></lastmod>
<?php endif; ?>

        <changefreq><?= xmlEscape($item['changefreq'] ?? 'monthly') ?></changefreq>
        <priority><?= xmlEscape($item['priority'] ?? '0.5') ?></priority>
    </url>

<?php endforeach; ?>

</urlset>