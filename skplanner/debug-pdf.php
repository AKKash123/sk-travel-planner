<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Step 1: Config
echo "<h3>Step 1: Loading config...</h3>";
require_once __DIR__ . '/config.php';
echo "APP_URL: " . (defined('APP_URL') ? APP_URL : '<b style="color:red">NOT DEFINED</b>') . "<br>";
echo "UPLOAD_DIR: " . (defined('UPLOAD_DIR') ? UPLOAD_DIR : '<b style="color:red">NOT DEFINED</b>') . "<br>";
echo "PDO: " . (isset($pdo) ? 'OK' : '<b style="color:red">NOT SET</b>') . "<br>";

// Step 2: ID check
echo "<h3>Step 2: ID parameter...</h3>";
 $id = (int)($_GET['id'] ?? 0);
echo "ID = " . $id . "<br>";
if ($id <= 0) { die('<b style="color:red">No valid ID</b>'); }

// Step 3: Database query
echo "<h3>Step 3: Database query...</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id=? AND status=1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
} catch (Exception $e) {
    die('<b style="color:red">DB Error: ' . htmlspecialchars($e->getMessage()) . '</b>');
}
if (!$row) { die('<b style="color:red">No itinerary found with id=' . $id . '</b>'); }
echo "Row found! Columns: " . implode(', ', array_keys($row)) . "<br>";
echo "Title: " . htmlspecialchars($row['title'] ?? 'NULL') . "<br>";
echo "Destination: " . htmlspecialchars($row['destination'] ?? 'NULL') . "<br>";
echo "Price: " . htmlspecialchars($row['price'] ?? 'NULL') . "<br>";
echo "Duration: " . htmlspecialchars($row['duration_days'] ?? 'NULL') . "<br>";
echo "Image: " . htmlspecialchars($row['image'] ?? 'NULL') . "<br>";

// Step 4: JSON fields
echo "<h3>Step 4: JSON fields...</h3>";
 $hl = json_decode($row['highlights'], true);
 $inc = json_decode($row['inclusions'], true);
 $exc = json_decode($row['exclusions'], true);
 $dp = json_decode($row['day_plan'], true);
echo "highlights: " . (is_array($hl) ? count($hl).' items' : '<b style="color:red">INVALID JSON</b>') . "<br>";
echo "inclusions: " . (is_array($inc) ? count($inc).' items' : '<b style="color:red">INVALID JSON</b>') . "<br>";
echo "exclusions: " . (is_array($exc) ? count($exc).' items' : '<b style="color:red">INVALID JSON</b>') . "<br>";
echo "day_plan: " . (is_array($dp) ? count($dp).' items' : '<b style="color:red">INVALID JSON</b>') . "<br>";

// Step 5: FPDF
echo "<h3>Step 5: FPDF load...</h3>";
require_once __DIR__ . '/fpdf/fpdf.php';
echo "FPDF class: " . (class_exists('FPDF') ? 'OK' : '<b style="color:red">NOT FOUND</b>') . "<br>";

// Step 6: Try generating PDF
echo "<h3>Step 6: Generate PDF...</h3>";
try {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Test: ' . ($row['title'] ?? 'No title'), 0, 1);
    $result = $pdf->Output('S');
    echo "<b style='color:green'>PDF generation OK! Size: " . strlen($result) . " bytes</b><br>";
} catch (Exception $e) {
    echo "<b style='color:red'>PDF Error: " . htmlspecialchars($e->getMessage()) . "</b><br>";
}

// Step 7: Check image
echo "<h3>Step 7: Image check...</h3>";
if (!empty($row['image'])) {
    $imgFile = UPLOAD_DIR . $row['image'];
    echo "Image path: " . htmlspecialchars($imgFile) . "<br>";
    echo "Exists: " . (file_exists($imgFile) ? 'YES' : '<b style="color:red">NO</b>') . "<br>";
} else {
    echo "No image in database<br>";
}

echo "<h3>All checks complete!</h3>";
?>