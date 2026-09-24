<?php
while (ob_get_level()) { ob_end_clean(); }
ob_start();
require_once 'config.php';
require_once __DIR__ . '/fpdf/fpdf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: '.APP_URL.'/index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id=? AND status=1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { header('Location: '.APP_URL.'/index.php'); exit; }

$hl = json_decode($row['highlights'], true);
$inc = json_decode($row['inclusions'], true);
$exc = json_decode($row['exclusions'], true);
$dp = json_decode($row['day_plan'], true);
if (!is_array($hl)) $hl = [];
if (!is_array($inc)) $inc = [];
if (!is_array($exc)) $exc = [];
if (!is_array($dp)) $dp = [];

function S($s) {
    if ($s === null) return '';
    $s = (string)$s;
    $r = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    if ($r !== false) $s = $r;
    $o = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $c = ord($s[$i]);
        if ($c===10||$c===13||$c===9||($c>=32&&$c<=255)) $o .= $s[$i];
    }
    return $o;
}

function fmtP($v) { return 'Rs. '.number_format((float)$v,0,',',','); }

class ItinPDF extends FPDF {
    function Header() {
        if ($this->page == 1) {
            $this->SetFillColor(27,40,56);
            $this->Rect(0,0,210,26,'F');
            $this->SetFillColor(232,145,45);
            $this->Rect(0,26,210,2,'F');

            $logoFile = __DIR__ . '/images/logo.jpg';
            if (file_exists($logoFile)) {
                $this->SetFillColor(255,255,255);
                $this->Rect(170, 3, 28, 20, 'F');
                $this->Image($logoFile, 171, 4, 26, 18);
            }

            $this->SetFont('Helvetica','B',12);
            $this->SetTextColor(255,255,255);
            $this->SetXY(12,7);
            $this->Cell(0,6,'SK Travel Planner',0,0,'L');
            $this->SetFont('Helvetica','',9);
            $this->SetTextColor(232,145,45);
            $this->SetXY(12,15);
            $this->Cell(0,5,'www.sktravelplanner.com  |  info@sktravelplanner.com',0,0,'L');
            $this->SetY(32);
        } else {
            $this->SetFillColor(27,40,56);
            $this->Rect(0,0,210,10,'F');
            $this->SetFont('Helvetica','B',8);
            $this->SetTextColor(255,255,255);
            $this->SetXY(12,3);
            $this->Cell(95,5,'SK Travel Planner',0,0,'L');
            $this->SetFont('Helvetica','',8);
            $this->SetTextColor(232,145,45);
            $this->Cell(95,5,'Continued',0,0,'R');
            $this->SetY(14);
        }
    }

    function Footer() {
        $this->SetY(-14);
        $this->SetDrawColor(200,200,200);
        $this->SetLineWidth(0.2);
        $this->Line(10,$this->GetY(),200,$this->GetY());
        $this->Ln(2);
        $this->SetFont('Helvetica','I',8);
        $this->SetTextColor(150,150,150);
        $this->Cell(0,7,'Page '.$this->PageNo().' of {nb}',0,0,'C');
    }
}

$pdf = new ItinPDF();
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true,18);
$pdf->AddPage();

$pdf->SetFont('Helvetica','B',20);
$pdf->SetTextColor(27,40,56);
$pdf->MultiCell(0,10,S($row['title']));
$pdf->SetFont('Helvetica','',12);
$pdf->SetTextColor(232,145,45);
$pdf->Cell(0,7,S($row['destination']),0,1);
$pdf->Ln(3);

$by = $pdf->GetY();
$pdf->SetFillColor(245,240,232);
$pdf->Rect(10,$by,190,12,'F');
$pdf->SetFillColor(232,145,45);
$pdf->Rect(10,$by,3,12,'F');
$pdf->SetFont('Helvetica','B',11);
$pdf->SetTextColor(13,115,119);
$pdf->SetXY(16,$by+2);
$pdf->Cell(90,5,'Price: '.fmtP($row['price']).' per person',0,0,'L');
$pdf->Cell(90,5,'Duration: '.(int)$row['duration_days'].' Days',0,0,'R');
$pd = ((int)$row['duration_days']>0) ? round((float)$row['price']/(int)$row['duration_days']) : 0;
$pdf->SetFont('Helvetica','',8);
$pdf->SetTextColor(100,100,100);
$pdf->SetXY(16,$by+7);
$pdf->Cell(90,4,'Excluding flights',0,0,'L');
$pdf->Cell(90,4,'Approx. '.fmtP($pd).'/day',0,0,'R');
$pdf->SetY($by+16);

if (!empty($row['image'])) {
    $imgFile = UPLOAD_DIR . $row['image'];
    if (file_exists($imgFile)) {
        $ext = strtolower(pathinfo($imgFile, PATHINFO_EXTENSION));
        if ($ext==='jpg'||$ext==='jpeg'||$ext==='png') {
            $is = @getimagesize($imgFile);
            if ($is!==false) {
                $ow=$is[0]; $oh=$is[1];
                $dw=180; $dh=($oh/$ow)*$dw;
                if ($dh>70) { $dh=70; $dw=($ow/$oh)*$dh; }
                $ix=(210-$dw)/2;
                $pdf->Image($imgFile,$ix,$pdf->GetY(),$dw,$dh,$ext);
                $pdf->SetY($pdf->GetY()+$dh+4);
            }
        }
    }
}

// SECTION TITLE helper
function secTitle($p,$t) {
    $p->Ln(2);
    $p->SetFont('Helvetica','B',13);
    $p->SetTextColor(13,115,119);
    $p->Cell(0,9,$t,0,1,'L');
    $y=$p->GetY();
    $p->SetDrawColor(232,145,45);
    $p->SetLineWidth(0.6);
    $p->Line(10,$y,50,$y);
    $p->Ln(3);
}

// BULLET ITEM helper
function bulletItem($p,$text,$r,$g,$b) {
    $st = S($text);
    if ($st==='') return;
    if ($p->GetY()>270) $p->AddPage();
    $y=$p->GetY();
    $x=10;
    $p->SetFillColor($r,$g,$b);
    $p->Rect($x+1,$y+1.5,2.2,2.2,'F');
    $p->SetX($x+6);
    $p->SetFont('Helvetica','',10);
    $p->SetTextColor(55,55,55);
    $w=210-10-$x-6;
    $p->MultiCell($w,5.5,$st);
    $p->Ln(0.5);
}

// DAY HEADER helper
function dayHead($p,$n,$t) {
    if ($p->GetY()>240) $p->AddPage();
    $y=$p->GetY();
    $p->SetFillColor(232,145,45);
    $p->Rect(10,$y,16,8,'F');
    $p->SetFont('Helvetica','B',8);
    $p->SetTextColor(255,255,255);
    $p->SetXY(10,$y+1.5);
    $p->Cell(16,5,'Day '.$n,0,0,'C');
    $p->SetFillColor(13,115,119);
    $p->Rect(26,$y,174,8,'F');
    $p->SetFont('Helvetica','B',10);
    $p->SetXY(28,$y+1.5);
    $p->Cell(170,5,$t,0,0,'L');
    $p->SetY($y+10);
}

// DAY TAGS helper
function dayTags($p,$meals,$hotel) {
    if ($meals===''&&$hotel==='') return;
    $y=$p->GetY();
    $x=12;
    if ($meals!=='') {
        $p->SetFont('Helvetica','B',7.5);
        $ml='Meals: '.$meals;
        $mw=$p->GetStringWidth($ml)+8;
        $p->SetFillColor(232,145,45);
        $p->Rect($x,$y,$mw,5.5,'F');
        $p->SetTextColor(255,255,255);
        $p->SetXY($x+4,$y+0.8);
        $p->Cell($mw-8,4,$ml,0,0,'L');
        $x+=$mw+3;
    }
    if ($hotel!=='') {
        $p->SetFont('Helvetica','B',7.5);
        $sl='Stay: '.$hotel;
        $sw=$p->GetStringWidth($sl)+8;
        $p->SetFillColor(13,115,119);
        $p->Rect($x,$y,$sw,5.5,'F');
        $p->SetTextColor(255,255,255);
        $p->SetXY($x+4,$y+0.8);
        $p->Cell($sw-8,4,$sl,0,0,'L');
    }
    $p->SetY($y+7.5);
}

// === BUILD CONTENT ===

secTitle($pdf,'Overview');
$pdf->SetFont('Helvetica','',10);
$pdf->SetTextColor(55,55,55);
$pdf->MultiCell(0,5.5,S($row['description']));
$pdf->Ln(4);

if (count($hl)>0) {
    secTitle($pdf,'Highlights');
    foreach ($hl as $h) { bulletItem($pdf,$h,13,115,119); }
    $pdf->Ln(3);
}

if (count($dp)>0) {
    secTitle($pdf,'Day-wise Itinerary');
    foreach ($dp as $i=>$d) {
        $dn=isset($d['day'])?(int)$d['day']:($i+1);
        $dt=S(isset($d['title'])?$d['title']:'Day '.$dn);
        $dd=S(isset($d['desc'])?$d['desc']:'');
        $dm=S(isset($d['meals'])?$d['meals']:'');
        $dh=S(isset($d['hotel'])?$d['hotel']:'');
        dayHead($pdf,$dn,$dt);
        if ($dd!=='') {
            $pdf->SetFont('Helvetica','',10);
            $pdf->SetTextColor(55,55,55);
            $pdf->MultiCell(0,5.5,$dd);
            $pdf->Ln(1);
        }
        dayTags($pdf,$dm,$dh);
        $pdf->Ln(2);
    }
}

if (count($inc)>0) {
    secTitle($pdf,'Inclusions');
    foreach ($inc as $v) { bulletItem($pdf,$v,46,139,87); }
    $pdf->Ln(3);
}

if (count($exc)>0) {
    secTitle($pdf,'Exclusions');
    foreach ($exc as $v) { bulletItem($pdf,$v,220,53,69); }
    $pdf->Ln(3);
}

$pdf->Ln(3);
$pdf->SetDrawColor(200,200,200);
$pdf->SetLineWidth(0.2);
$pdf->Line(10,$pdf->GetY(),200,$pdf->GetY());
$pdf->Ln(4);
$pdf->SetFont('Helvetica','B',9);
$pdf->SetTextColor(13,115,119);
$pdf->Cell(0,5,'Important Notice',0,1);
$pdf->SetFont('Helvetica','',8);
$pdf->SetTextColor(120,120,120);
$pdf->MultiCell(0,4.5,S('This itinerary is provided by SK Travel Planner for informational purposes. Prices and availability are subject to change. Contact info@sktravelplanner.com or +91 98765 43210 for booking.'));
$pdf->Ln(4);
$pdf->SetFont('Helvetica','B',9);
$pdf->SetTextColor(27,40,56);
$pdf->Cell(0,5,'SK Travel Planner',0,1);
$pdf->SetFont('Helvetica','',8);
$pdf->SetTextColor(150,150,150);
$pdf->Cell(0,4,'Email: info@sktravelplanner.com  |  Phone: +91 98765 43210',0,1);
$pdf->Cell(0,4,'Generated on '.date('F j, Y \a\t g:i A'),0,1);

$fn = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['title']);
$fn = trim($fn, '_');
if ($fn==='') $fn = 'itinerary';
$fn .= '.pdf';
$pdf->SetTitle(S($row['title']));
$pdf->SetAuthor('SK Travel Planner');
$pdf->SetSubject(S($row['destination'].' Itinerary'));
$pdf->SetCreator('SK Travel Planner');
while (ob_get_level()) { ob_end_clean(); }
$pdf->Output('D',$fn);
exit;
?>