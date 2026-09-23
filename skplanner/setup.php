<?php
// Run once to set up logging then delete the setup file 
 $dir = __DIR__ . '/logs';
if (!is_dir($dir)) mkdir($dir, 0755, true);

 $files = ['error.log', '404.log', 'access.log'];
foreach ($files as $f) {
    $path = $dir . '/' . $f;
    if (!file_exists($path)) touch($path);
}

// Protect logs with .htaccess
 $hta = $dir . '/.htaccess';
file_put_contents($hta, "Deny from all\n");

echo '<h2>Logs setup complete</h2><ul>';
foreach ($files as $f) echo '<li>' . $f . ' created</li>';
echo '<li>.htaccess protection added</li></ul><p>Delete this file.</p>';
?>