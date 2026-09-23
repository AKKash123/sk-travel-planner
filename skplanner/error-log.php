<?php
// ============================================
// Admin Error Log Viewer
// ============================================
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['admin_id'])) { header('Location: admin/index.php'); exit; }

 $logDir = __DIR__ . '/logs';
 $files = ['error.log','404.log','access.log'];
 $current = isset($_GET['f']) && in_array($_GET['f'], $files) ? $_GET['f'] : 'error.log';
 $logPath = $logDir . '/' . $current;
 $lines = [];
 $totalLines = 0;

if (file_exists($logPath)) {
    $fp = fopen($logPath, 'r');
    $all = [];
    while (($line = fgets($fp)) !== false) { $all[] = $line; }
    fclose($fp);
    $totalLines = count($all);
    $lines = array_slice(array_reverse($all), 0, 200);
}

if (isset($_GET['clear']) && isset($_GET['token']) && hash_equals($_SESSION['log_token']??'', $_GET['token'])) {
    file_put_contents($logPath, '');
    header('Location: error-log.php?f=' . urlencode($current));
    exit;
}
 $_SESSION['log_token'] = bin2hex(random_bytes(32));

function e($s) { return htmlspecialchars($s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Error Logs — SK Travel Planner Admin</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Source Sans 3',sans-serif;background:#F7F3ED;color:#2D2D2D;min-height:100vh;}
.top{background:#1B2838;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.top h1{color:white;font-size:18px;font-family:'Playfair Display',serif;}
.top a{color:rgba(255,255,255,.7);font-size:14px;text-decoration:none;}
.content{max-width:960px;margin:24px auto;padding:0 20px;}
.tabs{display:flex;gap:6px;margin-bottom:20px;}
.tab{padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:14px;transition:all .2s;}
.tab-a{background:#0D7377;color:white;}
.tab-i{background:white;color:#2D2D2D;border:1px solid #D1CBC0;}
.tab-i:hover{border-color:#0D7377;color:#0D7377;}
.stats{display:flex;gap:12px;margin-bottom:16px;font-size:13px;color:#6B7280;}
.stats span{font-weight:700;color:#1B2838;}
.log-box{background:white;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
.log-box pre{font-family:'Courier New',monospace;font-size:12px;line-height:1.6;overflow-x:auto;max-height:70vh;overflow-y:auto;padding:16px;background:#1B2838;color:#c8d6e5;border-radius:8px;white-space:pre-wrap;word-break:break-all;}
.warn{background:rgba(220,53,69,.05);border:1px solid rgba(220,53,69,.15);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#721c24;}
.btn{padding:8px 16px;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;}
</style>
</head>
<body>
<div class="top"><h1><i class="fas fa-file-alt"></i> Error Logs</h1><a href="admin/dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
<div class="content">
<div class="tabs">
<?php foreach($files as $f): ?>
<a class="tab <?= $f===$current?'tab-a':'tab-i' ?>" href="?f=<?= e($f) ?>"><?= e($f) ?></a>
<?php endforeach; ?>
</div>
<div class="stats">File: <span><?= e($current) ?></span> &bull; Total: <span><?= $totalLines ?></span> lines &bull; Showing latest 200</div>
<a href="?f=<?= e($current) ?>&clear=1&token=<?= e($_SESSION['log_token']) ?>" class="btn" style="background:#DC3545;color:white;float:right;margin-bottom:10px;" onclick="return confirm('Clear this log file?');"><i class="fas fa-trash"></i> Clear Log</a>
<div style="clear:both;"></div>
<?php if(empty($lines)): ?>
<div class="log-box"><p style="padding:40px;text-align:center;color:#6B7280;"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:#2E8B57;"></i>Log file is empty. No errors recorded.</p></div>
<?php else: ?>
<div class="log-box"><pre><?= e(implode('', $lines)) ?></pre></div>
<?php endif; ?>
</div>
</body></html>