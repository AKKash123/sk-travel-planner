<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/fpdf/fpdf.php';

if (!class_exists('FPDF')) {
    die('ERROR: FPDF class not found');
}

try {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Test Works!', 0, 1);
    $pdf->Output('I', 'test.pdf');
} catch (Exception $e) {
    die('ERROR: ' . $e->getMessage());
}