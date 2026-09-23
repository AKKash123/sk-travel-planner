<?php
// ============================================
// SK Travel Planner — 404 Error Page
// ============================================
require_once __DIR__ . '/config.php';
 $logRef = $_SERVER['REQUEST_URI'] ?? 'Unknown';
 $logAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
 $logIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Log the 404 error
if (file_exists(__DIR__ . '/logs')) {
    $entry = date('Y-m-d H:i:s') . " | 404 | IP:{$logIp} | URL:{$logRef} | Agent:{$logAgent}\n";
    @file_put_contents(__DIR__ . '/logs/404.log', $entry, FILE_APPEND);
}

// Suggest similar pages based on URL keywords
 $suggestions = [];
 $path = strtolower($logRef);
if (strpos($path, 'admin') !== false) $suggestions[] = ['Admin Panel', 'admin/index.php'];
if (strpos($path, 'detail') !== false) $suggestions[] = ['Home', 'index.php'];
if (strpos($path, 'pdf') !== false || strpos($path, 'download') !== false) $suggestions[] = ['Itineraries', 'index.php#itineraries'];
if (strpos($path, 'bali') !== false || strpos($path, 'travel') !== false) $suggestions[] = ['Browse Trips', 'index.php'];
 $suggestions[] = ['Homepage', 'index.php'];
 $suggestions = array_unique($suggestions, SORT_REGULAR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Page Not Found — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Source Sans 3',sans-serif;background:#F7F3ED;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;color:#2D2D2D;}
h1,h2,h3{font-family:'Playfair Display',serif;color:#1B2838;}
a{color:#0D7377;text-decoration:none;}a:hover{color:#E8912D;}
.wrap{max-width:560px;width:100%;text-align:center;}
.err-code{font-family:'Playfair Display',serif;font-size:140px;font-weight:900;line-height:1;
background:linear-gradient(135deg,#0D7377,#E8912D);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;
animation:fadeIn .6s ease both;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-20px);}to{opacity:1;transform:translateY(0);}}
h2{font-size:26px;margin-bottom:10px;animation:fadeIn .6s .1s ease both;opacity:0;}
p.desc{font-size:16px;color:#6B7280;margin-bottom:28px;line-height:1.6;animation:fadeIn .6s .2s ease both;opacity:0;}
.url-box{background:white;border-radius:12px;padding:14px 20px;font-family:'Courier New',monospace;font-size:14px;color:#DC3545;word-break:break-all;margin-bottom:28px;border:1px solid #EDE7DB;animation:fadeIn .6s .3s ease both;opacity:0;}
.sug-title{font-size:15px;font-weight:700;color:#1B2838;margin-bottom:12px;animation:fadeIn .6s .4s ease both;opacity:0;}
.sug-list{list-style:none;animation:fadeIn .6s .5s ease both;opacity:0;}
.sug-list li{margin-bottom:8px;}
.sug-list a{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:white;border-radius:8px;border:2px solid #D1CBC0;transition:all .2s;font-weight:600;font-size:14px;}
.sug-list a:hover{border-color:#0D7377;background:rgba(13,115,119,.04);transform:translateY(-2px);}
.search{margin-top:24px;animation:fadeIn*fadeIn .6s .6s ease both;opacity:0;}
.search input{padding:12px 16px;border:2px solid #D1CBC0;border-radius:8px 0 0 8px;font-size:14px;width:260px;outline:none;font-family:'Source Sans 3',sans-serif;}
.search input:focus{border-color:#0D7377;}
.search button{padding:12px 20px;background:#0D7377;color:white;border:none;border-radius:0 8px 8px 0;font-size:14px;cursor:pointer;font-family:'Source Sans 3',sans-serif;font-weight:600;}
.search button:hover{background:#095558;}
.foot{margin-top:32px;font-size:13px;color:#6B7280;animation:fadeIn .6s .7s ease both;opacity:0;}
</style>
</head>
<body>
<div class="wrap">
<div class="err-code">404</div>
<h2>Page Not Found</h2>
<p class="desc">The page you're looking for doesn't exist, has been moved, or is temporarily unavailable.</p>
<div class="url-box"><i class="fas fa-link" style="margin-right:8px;color:#D1CBC0;"></i><?= e(urldecode($logRef)) ?></div>

<?php if(!empty($suggestions)): ?>
<div class="sug-title">Were you looking for...</div>
<ul class="sug-list">
<?php foreach($suggestions as $s): ?>
<li><a href="<?= e($s[1]) ?>"><i class="fas fa-arrow-right"></i> <?= e($s[0]) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="search">
<form action="index.php" method="GET">
<input type="text" name="search" placeholder="Search itineraries..." autocomplete="off">
<button type="submit"><i class="fas fa-search"></i></button>
</form>
</div>

<div class="foot">
<a href="index.php"><i class="fas fa-home"></i> Home</a> &bull;
<a href="admin/index.php"><i class="fas fa-lock"></i> Admin</a>
</div>
</div>
</body>
</html>