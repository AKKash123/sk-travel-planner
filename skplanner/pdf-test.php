<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>PDF Diagnostic</h2><pre>';

// Test 1
echo '1. Config load... ';
require_once 'config.php';
echo "OK\n";

// Test 2
echo '2. FPDF file... ';
 $p = __DIR__ . '/fpdf/fpdf.php';
if (file_exists($p)) {
    echo "OK (" . filesize($p) . " bytes)\n";
} else {
    echo "MISSING - download from fpdf.org\n";
    exit;
}

// Test 3
echo '3. Require FPDF... ';
require_once $p;
echo "OK\n";

// Test 4
echo '4. New FPDF()... ';
 $pdf = new FPDF();
echo "OK\n";

// Test 5
echo '5. AddPage... ';
 $pdf->AddPage();
echo "OK\n";

// Test 6
echo '6. SetFont... ';
 $pdf->SetFont('Helvetica', 'B', 16);
echo "OK\n";

// Test 7
echo '7. Cell... ';
 $pdf->Cell(0, 10, 'Hello World', 0, 1);
echo "OK\n";

// Test 8
echo '8. Rect... ';
 $pdf->SetFillColor(27, 40, 56);
 $pdf->Rect(10, 30, 190, 10, 'F');
echo "OK\n";

// Test 9
echo '9. Output I... ';
ob_start();
 $pdf->Output('I', 'test.pdf');
echo "OK\n";

echo "\nAll tests passed. If you see a PDF, FPDF works perfectly.";
?>