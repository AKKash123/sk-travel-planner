<?php
 $logUri = $_SERVER['REQUEST_URI'] ?? '';
 $logIp  = $_SERVER['REMOTE_ADDR'] ?? '';
if (is_dir(__DIR__ . '/logs')) {
    @file_put_contents(__DIR__ . '/logs/error.log', date('Y-m-d H:i:s')." | 500 | IP:{$logIp} | URL:{$logUri}\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>500 Error — SK Travel Planner</title>
<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Source Sans 3',sans-serif;background:#F7F3ED;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:40px;}h1{font-family:'Playfair Display',serif;font-size:120px;color:#E8912D;margin-bottom:8px;}h2{font-size:24px;color:#1B2838;margin-bottom:10px;}p{color:#6B7280;font-size:16px;margin-bottom:24px;}a{color:#0D7377;font-weight:600;}</style>
</head><body><div><h1>500</h1><h2>Server Error</h2><p>Something went wrong. Our team has been notified. Please try again later.</p><a href="index.php"><i class="fas fa-home"></i> Go Home</a></div></body></html>