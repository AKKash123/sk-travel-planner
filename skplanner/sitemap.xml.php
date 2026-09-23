<?php
// ============================================
// SK Travel Planner — Dynamic Sitemap
// Generates XML sitemap from database
// ============================================
require_once 'config.php';

 $baseUrl = APP_URL;
 $items   = [];

// Static pages
 $items[] = ['loc' => $baseUrl . '/index.php', 'changefreq' => 'weekly', 'priority' => '1.0'];
 $items[] = ['loc' => $baseUrl . '/detail.php', 'changefreq' => 'weekly', 'priority' => '0.8'];

// Dynamic itinerary pages
try {
    $stmt = $pdo->query("SELECT id, title, updated_at FROM itineraries WHERE status = 1 ORDER BY updated_at DESC");
    $itins = $stmt->fetchAll();
    foreach ($itins as $itin) {
        $items[] = [
            'loc'        => $baseUrl . '/detail.php?id=' . (int)$itin['id'],
            'lastmod'    => date('Y-m-d', strtotime($itin['updated_at'])),
            'changefreq' => 'weekly',
            'priority'   => '0.9'
        ];
    }
} catch (PDOException $e) {
    // Fallback — just serve static pages
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

<?php foreach ($items as $item): ?>
<url>
    <loc><?= htmlspecialchars($item['loc'], ENT_XML1, 'UTF-8') ?></loc>
    <?php if (!empty($item['lastmod'])): ?>
    <lastmod><?= e($item['lastmod']) ?></lastmod>
    <?php endif; ?>
    <changefreq><?= e($item['changefreq'] ?? 'monthly') ?></changefreq>
    <priority><?= e($item['priority'] ?? '0.5') ?></priority>
</url>
<?php endforeach; ?>

</urlset>