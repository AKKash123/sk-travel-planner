<?php
// ============================================
// FPDF Version 1.82 — Corrected Build
// Pure PHP PDF generation — no external deps
// Place at: fpdf/fpdf.php
// ============================================

if(!class_exists('FPDF'))
{

class FPDF
{
    const FPDF_VERSION = '1.82';

    // Core state
    protected $page;
    protected $n;
    protected $offsets;
    protected $buffer;
    protected $pages;
    protected $state;
    protected $compress;
    protected $k;
    protected $DefOrientation;
    protected $CurOrientation;
    protected $StdPageSizes;
    protected $DefPageSize;
    protected $CurPageSize;
    protected $PageInfo;
    protected $CurRotation;
    protected $wPt;
    protected $hPt;
    protected $w;
    protected $h;
    protected $lMargin;
    protected $tMargin;
    protected $rMargin;
    protected $bMargin;
    protected $cMargin;
    protected $CurUnit;
    protected $x;
    protected $y;
    protected $lasth;
    protected $LineWidth;
    protected $path;
    protected $nPages;
    protected $CoreFonts;
    protected $fonts;
    protected $FontFiles;
    protected $diffs;
    protected $FontFamily;
    protected $FontStyle;
    protected $underline;
    protected $CurrentFont;
    protected $FontSizePt;
    protected $FontSize;
    protected $DrawColor;
    protected $FillColor;
    protected $TextColor;
    protected $ColorFlag;
    protected $WithAlpha;
    protected $ws;
    protected $images;
    protected $PageLinks;
    protected $links;
    protected $AutoPageBreak;
    protected $PageBreakTrigger;
    protected $InHeader;
    protected $InFooter;
    protected $AliasNbPages;
    protected $PDFVersion;

    // Document metadata
    protected $title       = '';
    protected $author      = '';
    protected $subject     = '';
    protected $keywords    = '';
    protected $creator     = '';

    // Display mode
    protected $ZoomMode    = 'default';
    protected $LayoutMode  = 'default';

    // ============================================
    //  CONSTRUCTOR
    // ============================================
    public function __construct($orientation='P', $unit='mm', $size='A4')
    {
        $this->state       = 0;
        $this->page        = 0;
        $this->n           = 0;
        $this->buffer      = '';
        $this->pages       = array();
        $this->PageInfo    = array();
        $this->offsets     = array();
        $this->lMargin     = 10;
        $this->tMargin     = 10;
        $this->rMargin     = 10;
        $this->bMargin     = 20;
        $this->cMargin     = 1;
        $this->CurUnit     = $unit;
        $this->StdPageSizes = array(
            'a3'    => array(841.89, 1190.55),
            'a4'    => array(595.28, 841.89),
            'a5'    => array(420.94, 595.28),
            'letter'=> array(612, 792),
            'legal' => array(612, 1008)
        );

        $size = strtolower($size);
        if(isset($this->StdPageSizes[$size]))
            $size = $this->StdPageSizes[$size];
        else
            $size = $this->_getpagesize($size);

        $this->DefPageSize  = $size;
        $this->CurPageSize  = $size;

        // Scale factor
        if($unit == 'pt')
            $this->k = 1;
        elseif($unit == 'mm')
            $this->k = 72 / 25.4;
        elseif($unit == 'cm')
            $this->k = 72 / 2.54;
        elseif($unit == 'in')
            $this->k = 72;
        else
            $this->Error('Incorrect unit: ' . $unit);

        // Orientation
        $orientation = strtolower($orientation);
        if($orientation == 'p' || $orientation == 'portrait')
        {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        }
        elseif($orientation == 'l' || $orientation == 'landscape')
        {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        }
        else
            $this->Error('Incorrect orientation: ' . $orientation);

        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        // Margins
        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);

        // Line width (0.2 mm)
        $this->LineWidth = .567 / $this->k;

        // Auto page break
        $this->SetAutoPageBreak(true, 2 * $margin);

        // Colors
        $this->DrawColor  = '0 G';
        $this->FillColor  = '0 g';
        $this->TextColor  = '0 g';
        $this->ColorFlag  = false;
        $this->WithAlpha  = false;

        // Font defaults
        $this->FontFamily = 'Helvetica';
        $this->FontStyle  = '';
        $this->underline  = false;
        $this->FontSizePt = 12;

        // Core fonts list
        $this->CoreFonts = array(
            'Courier','CourierB','CourierI','CourierBI',
            'Helvetica','HelveticaB','HelveticaI','HelveticaBI',
            'Times','TimesB','TimesI','TimesBI',
            'Symbol','ZapfDingbats'
        );

        $this->fonts      = array();
        $this->FontFiles  = array();
        $this->diffs      = array();
        $this->images     = array();
        $this->PageLinks  = array();
        $this->links      = array();
        $this->InHeader   = false;
        $this->InFooter   = false;
        $this->lasth      = 0;
        $this->nPages     = 0;
        $this->AliasNbPages = '{nb}';
        $this->compress   = function_exists('gzcompress');
        $this->ws         = 0;
        $this->PDFVersion = '1.7';
    }

    // ============================================
    //  PUBLIC — SETUP METHODS
    // ============================================
    public function SetMargins($left, $top, $right=null)
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if($right === null) $right = $left;
        $this->rMargin = $right;
    }

    public function SetLeftMargin($margin)   { $this->lMargin = $margin; }
    public function SetTopMargin($margin)    { $this->tMargin = $margin; }
    public function SetRightMargin($margin)  { $this->rMargin = $margin; }

    public function SetAutoPageBreak($auto, $margin=0)
    {
        $this->AutoPageBreak    = $auto;
        $this->bMargin          = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetDisplayMode($zoom, $layout='default')
    {
        if($zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string($zoom))
            $this->ZoomMode = $zoom;
        else
            $this->Error('Incorrect zoom display mode: ' . $zoom);

        if($layout == 'single' || $layout == 'continuous' || $layout == 'two' || $layout == 'default')
            $this->LayoutMode = $layout;
        else
            $this->Error('Incorrect layout display mode: ' . $layout);
    }

    public function SetCompression($compress)
    {
        $this->compress = $compress ? function_exists('gzcompress') : false;
    }

    public function SetTitle($title, $isUTF8=false)    { $this->title    = $isUTF8 ? $title : utf8_encode($title); }
    public function SetAuthor($author, $isUTF8=false)   { $this->author   = $isUTF8 ? $author : utf8_encode($author); }
    public function SetSubject($subject, $isUTF8=false) { $this->subject  = $isUTF8 ? $subject : utf8_encode($subject); }
    public function SetKeywords($kw, $isUTF8=false)     { $this->keywords = $isUTF8 ? $kw : utf8_encode($kw); }
    public function SetCreator($creator, $isUTF8=false) { $this->creator  = $isUTF8 ? $creator : utf8_encode($creator); }

    public function AliasNbPages($alias='{nb}') { $this->AliasNbPages = $alias; }

    public function Error($msg) { throw new Exception('FPDF error: ' . $msg); }

    // ============================================
    //  PUBLIC — DOCUMENT METHODS
    // ============================================
    public function Close()
    {
        if($this->state == 3) return;
        if($this->page == 0) $this->AddPage();

        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;

        $this->_endpage();
        $this->_enddoc();
    }

    public function AddPage($orientation='', $size='', $rotation=0)
    {
        if($this->state == 3)
            $this->Error('The document is closed');

        // Save current style
        $family   = $this->FontFamily;
        $style    = $this->FontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw       = $this->LineWidth;
        $dc       = $this->DrawColor;
        $fc       = $this->FillColor;
        $tc       = $this->TextColor;
        $cf       = $this->ColorFlag;

        if($this->page > 0)
        {
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }

        $this->_beginpage($orientation, $size, $rotation);

        // Line cap = square
        $this->_out('2 J');
        // Line width
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));

        // Restore font
        if($family) $this->SetFont($family, $style, $fontsize);

        // Restore colors
        $this->DrawColor = $dc;
        if($dc != '0 G') $this->_out($dc);
        $this->FillColor = $fc;
        if($fc != '0 g') $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;

        // Header
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;

        // Restore after header
        if($family) $this->SetFont($family, $style, $fontsize);
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        $this->DrawColor = $dc;
        if($dc != '0 G') $this->_out($dc);
        $this->FillColor = $fc;
        if($fc != '0 g') $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    public function Header() { /* Override in subclass */ }
    public function Footer() { /* Override in subclass */ }

    public function PageNo() { return $this->page; }

    // ============================================
    //  PUBLIC — COLOR METHODS
    // ============================================
    public function SetDrawColor($r, $g=null, $b=null)
    {
        if($g === null)
            $this->DrawColor = sprintf('%.3F G', $r / 255);
        else
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255);

        if($this->page > 0 && !$this->InHeader && !$this->InFooter)
            $this->_out($this->DrawColor);
    }

    public function SetFillColor($r, $g=null, $b=null)
    {
        if($g === null)
            $this->FillColor = sprintf('%.3F g', $r / 255);
        else
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);

        $this->ColorFlag = ($this->FillColor != $this->TextColor);

        if($this->page > 0 && !$this->InHeader && !$this->InFooter)
            $this->_out($this->FillColor);
    }

    public function SetTextColor($r, $g=null, $b=null)
    {
        if($g === null)
            $this->TextColor = sprintf('%.3F g', $r / 255);
        else
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);

        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    // ============================================
    //  PUBLIC — TEXT MEASUREMENT
    // ============================================
    public function GetStringWidth($s)
    {
        $s  = (string)$s;
        $cw = &$this->CurrentFont['cw'];
        $w  = 0;
        $l  = strlen($s);
        for($i = 0; $i < $l; $i++)
            $w += $cw[$s[$i]];
        return $w * $this->FontSize / 1000;
    }

    // ============================================
    //  PUBLIC — DRAWING
    // ============================================
    public function SetLineWidth($width)
    {
        $this->LineWidth = $width;
        if($this->page > 0 && !$this->InHeader && !$this->InFooter)
            $this->_out(sprintf('%.2F w', $width * $this->k));
    }

    public function Line($x1, $y1, $x2, $y2)
    {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',
            $x1 * $this->k, ($this->h - $y1) * $this->k,
            $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function Rect($x, $y, $w, $h, $style='')
    {
        if($style == 'F')
            $op = 'f';
        elseif($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';

        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',
            $x * $this->k, ($this->h - $y) * $this->k,
            $w * $this->k, -$h * $this->k, $op));
    }

    // ============================================
    //  PUBLIC — FONT
    // ============================================
    public function SetFont($family, $style='', $size=0)
    {
        if($family == '')
            $family = $this->FontFamily;
        else
            $family = strtolower($family);

        $style = strtoupper($style);
        if(strpos($style, 'U') !== false)
        {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        }
        else
            $this->underline = false;

        if($style == 'IB') $style = 'BI';

        // Already selected?
        if($this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size)
            return;

        // Normalize
        if($family == 'arial')
            $family = 'helvetica';
        elseif($family == 'symbol' || $family == 'zapfdingbats')
            $style = '';

        if(!in_array($family, $this->CoreFonts) && !isset($this->fonts[$family . $style]))
            $this->Error('Undefined font: ' . $family . ' ' . $style);

        if($size == 0) $size = $this->FontSizePt;

        $this->FontFamily = $family;
        $this->FontStyle  = $style;
        $this->FontSizePt = $size;
        $this->FontSize   = $size / $this->k;

        $fontkey = $family . $style;
        if(!isset($this->fonts[$fontkey]))
        {
            $cw = $this->_getfontwidths($family . $style);
            $this->fonts[$fontkey] = array(
                'i'    => count($this->fonts) + 1,
                'type' => 'core',
                'name' => $this->_getfontname($family, $style),
                'up'   => -100,
                'ut'   => 50,
                'cw'   => $cw
            );
        }

        $this->CurrentFont = &$this->fonts[$fontkey];

        if($this->page > 0 && !$this->InHeader && !$this->InFooter)
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetFontSize($size)
    {
        if($this->FontSizePt == $size) return;
        $this->FontSizePt = $size;
        $this->FontSize   = $size / $this->k;

        if($this->page > 0 && !$this->InHeader && !$this->InFooter)
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    // ============================================
    //  PUBLIC — CELL / TEXT OUTPUT
    // ============================================
    public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='', $ignore_min=false)
    {
        $txt = (string)$txt;
        if(!$this->CurrentFont)
            $this->Error('No font has been set');

        $cMargin = $this->cMargin;
        if(!$ignore_min && ($h == 0 || $h < $this->FontSize))
            $h = $this->FontSize + 2 * $cMargin;

        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;

        // Auto page break
        if($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak)
        {
            $x  = $this->x;
            $ws = $this->ws;
            if($ws > 0) { $this->ws = 0; $this->_out('0 Tw'); }
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            $this->x = $x;
            if($ws > 0) { $this->ws = $ws; $this->_out(sprintf('%.3F Tw', $ws * $this->k)); }
        }

        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;

        $s = '';

        // Fill / border rect
        if($fill || $border == 1)
        {
            $op = ($fill) ? (($border == 1) ? 'B' : 'f') : 'S';
            $s = sprintf('%.2F %.2F %.2F %.2F re %s',
                $this->x * $this->k, ($this->h - $this->y) * $this->k,
                $w * $this->k, -$h * $this->k, $op);
        }

        // Named border sides
        if(is_string($border) && $border != '1' && $border != '0')
        {
            $bx = $this->x;
            $by = $this->y;
            if(strpos($border,'L')!==false)
                $s .= sprintf(' %.2F %.2F m %.2F %.2F l S', $bx*$this->k, ($this->h-$by)*$this->k, $bx*$this->k, ($this->h-$by-$h)*$this->k);
            if(strpos($border,'T')!==false)
                $s .= sprintf(' %.2F %.2F m %.2F %.2F l S', $bx*$this->k, ($this->h-$by)*$this->k, ($bx+$w)*$this->k, ($this->h-$by)*$this->k);
            if(strpos($border,'R')!==false)
                $s .= sprintf(' %.2F %.2F m %.2F %.2F l S', ($bx+$w)*$this->k, ($this->h-$by)*$this->k, ($bx+$w)*$this->k, ($this->h-$by-$h)*$this->k);
            if(strpos($border,'B')!==false)
                $s .= sprintf(' %.2F %.2F m %.2F %.2F l S', $bx*$this->k, ($this->h-$by-$h)*$this->k, ($bx+$w)*$this->k, ($this->h-$by-$h)*$this->k);
        }

        // Text
        if($txt !== '')
        {
            if(!$align || $align == 'L')
                $dx = $cMargin;
            elseif($align == 'R')
                $dx = $w - $this->GetStringWidth($txt) - $cMargin;
            elseif($align == 'C')
                $dx = ($w - $this->GetStringWidth($txt)) / 2;
            else
                $dx = $cMargin;

            if($this->ColorFlag)
                $s .= 'q ' . $this->TextColor . ' ';

            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',
                ($this->x + $dx) * $this->k,
                ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $this->k,
                $this->_escape($txt));

            if($this->underline)
                $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);

            if($this->ColorFlag)
                $s .= ' Q';

            if($link)
                $this->Link($this->x + $dx, $this->y + .5*$h - .5*$this->FontSize, $this->GetStringWidth($txt), $this->FontSize, $link);
        }

        if($s) $this->_out($s);

        $this->lasth = $h;
        if($ln > 0)
        {
            $this->y += $h;
            if($ln == 1) $this->x = $this->lMargin;
        }
        else
            $this->x += $w;
    }

    public function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
    {
        $txt = (string)$txt;
        if(!$this->CurrentFont)
            $this->Error('No font has been set');

        $cw = &$this->CurrentFont['cw'];
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s    = str_replace("\r", '', $txt);
        $nb   = strlen($s);
        if($nb > 0 && $s[$nb-1] == "\n") $nb--;

        $b  = 0;
        $b2 = '';
        if($border)
        {
            if($border == 1)
            {
                $border = 'LTRB';
                $b  = 'LRT';
                $b2 = 'LR';
            }
            else
            {
                if(strpos($border,'L')!==false) $b2 .= 'L';
                if(strpos($border,'R')!==false) $b2 .= 'R';
                $b = (strpos($border,'T')!==false) ? $b2 . 'T' : $b2;
            }
        }

        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $ns  = 0;
        $nl  = 1;

        while($i < $nb)
        {
            $c = $s[$i];
            if($c == "\n")
            {
                if($this->ws > 0) { $this->ws = 0; $this->_out('0 Tw'); }
                $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1; $j = $i; $l = 0; $ns = 0; $nl++;
                if($border && $nl == 2) $b = $b2;
                continue;
            }
            if($c == ' ') { $sep = $i; $ns++; }
            $l += $cw[$c];
            if($l > $wmax)
            {
                if($sep == -1)
                {
                    if($i == $j) $i++;
                    if($this->ws > 0) { $this->ws = 0; $this->_out('0 Tw'); }
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                }
                else
                {
                    if($align == 'J')
                    {
                        $this->ws = ($ns > 1) ? ($wmax - $l) / 1000 * $this->FontSize / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
                    }
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $ns = 0; $nl++;
                if($border && $nl == 2) $b = $b2;
            }
            else
                $i++;
        }

        if($this->ws > 0) { $this->ws = 0; $this->_out('0 Tw'); }
        $last_b = ($border && strpos($border, 'B') !== false) ? $b . 'B' : $b;
        $this->Cell($w, $h, substr($s, $j, $i - $j), $last_b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function Write($h, $txt, $link='')
    {
        $txt = (string)$txt;
        if(!$this->CurrentFont)
            $this->Error('No font has been set');

        $cw   = &$this->CurrentFont['cw'];
        $w    = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s    = str_replace("\r", '', $txt);
        $nb   = strlen($s);
        $sep  = -1;
        $i    = 0;
        $j    = 0;
        $l    = 0;
        $nl   = 1;

        while($i < $nb)
        {
            $c = $s[$i];
            if($c == "\n")
            {
                $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
                $i++; $sep = -1; $j = $i; $l = 0;
                if($nl == 1)
                {
                    $this->x = $this->lMargin;
                    $w    = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
                }
                $nl++;
                continue;
            }
            if($c == ' ') $sep = $i;
            $l += $cw[$c];
            if($l > $wmax)
            {
                if($sep == -1)
                {
                    if($this->x > $this->lMargin)
                    {
                        $this->x = $this->lMargin;
                        $this->y += $h;
                        $w    = $this->w - $this->rMargin - $this->x;
                        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
                        $i++; $nl++;
                        continue;
                    }
                    if($i == $j) $i++;
                    $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', false, $link);
                }
                else
                {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', false, $link);
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0;
                if($nl == 1)
                {
                    $this->x = $this->lMargin;
                    $w    = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
                }
                $nl++;
            }
            else
                $i++;
        }
        if($i != $j)
            $this->Cell($l/1000*$this->FontSize, $h, substr($s, $j), 0, 0, '', false, $link);
    }

    public function Ln($h=null)
    {
        if($h === null) $h = $this->lasth;
        $this->x = $this->lMargin;
        $this->y += $h;
    }

    // ============================================
    //  PUBLIC — POSITION
    // ============================================
    public function GetX()              { return $this->x; }
    public function GetY()              { return $this->y; }
    public function SetX($x)            { $this->x = ($x >= 0) ? $x : $this->w + $x; }
    public function SetY($y, $reset=true) { $this->y = ($y >= 0) ? $y : $this->h + $y; if($reset) $this->x = $this->lMargin; }
    public function SetXY($x, $y)       { $this->SetX($x); $this->SetY($y, false); }

    // ============================================
    //  PUBLIC — IMAGE
    // ============================================
    public function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
    {
        if($this->state != 2)
            $this->Error('Image is only possible on an active page');
        if($x === null) $x = $this->x;
        if($y === null) $y = $this->y;

        if($type == '')
        {
            $pos = strrpos($file, '.');
            if(!$pos) $this->Error('Image file has no extension: ' . $file);
            $type = substr($file, $pos + 1);
        }
        $type = strtolower($type);
        if($type == 'jpeg') $type = 'jpg';
        if(!in_array($type, array('jpg', 'png', 'gif')))
            $this->Error('Unsupported image type: ' . $type);

        $mtd  = '_parse' . $type;
        $info = $this->$mtd($file);

        if(!isset($this->images[$file]))
        {
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        }
        else
            $info = $this->images[$file];

        if($w == 0 && $h == 0)
        {
            $w = $info['w'] * $this->k / 72;
            $h = $info['h'] * $this->k / 72;
        }
        elseif($w == 0)
            $w = $h * $info['w'] / $info['h'];
        elseif($h == 0)
            $h = $w * $info['h'] / $info['w'];

        if($y === null)
        {
            if($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak)
            {
                $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
                $x = $this->x;
            }
            $y = $this->y;
            $this->y += $h;
        }
        if($x === null) $x = $this->x;

        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $w * $this->k, $h * $this->k,
            $x * $this->k, ($this->h - ($y + $h)) * $this->k,
            $info['i']));

        if($link)
            $this->Link($x, $y, $w, $h, $link);
    }

    // ============================================
    //  PUBLIC — LINKS
    // ============================================
    public function AddLink()
    {
        $this->links[] = array('page'=>0, 'y'=>0);
        return count($this->links) - 1;
    }

    public function SetLink($link, $y=0, $page=-1)
    {
        if($y == -1)    $y    = $this->y;
        if($page == -1) $page = $this->page;
        $this->links[$link] = array('page'=>$page, 'y'=>$y);
    }

    public function Link($x, $y, $w, $h, $link)
    {
        if($this->page <= 0)
            $this->Error('Page must be started before adding links');
        $this->PageLinks[$this->page][] = array(
            'x'=>$x*$this->k, 'y'=>$this->hPt-$y*$this->k,
            'w'=>$w*$this->k, 'h'=>$h*$this->k, 'link'=>$link
        );
    }

    // ============================================
    //  PUBLIC — OUTPUT
    // ============================================
    public function Output($dest='', $name='', $isUTF8=false)
    {
        $this->Close();

        if(strlen($name) == 1 && strlen($dest) != 1)
        {
            $tmp  = $dest;
            $dest = $name;
            $name = $tmp;
        }
        if($dest == '')  $dest = 'I';
        if($name == '')  $name = 'doc.pdf';

        switch(strtoupper($dest))
        {
            case 'I':
                if(PHP_SAPI != 'cli')
                {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $name . '"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                $f = fopen($name, 'wb');
                if(!$f) $this->Error('Unable to create output file: ' . $name);
                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                return $this->buffer;
            default:
                $this->Error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    // ============================================
    //  PROTECTED — PAGE MANAGEMENT
    // ============================================
    protected function _getpagesize($size)
    {
        if(is_string($size))
        {
            $size = strtolower($size);
            if(!isset($this->StdPageSizes[$size]))
                $this->Error('Unknown page size: ' . $size);
            $a = $this->StdPageSizes[$size];
            return array($a[0]/$this->k, $a[1]/$this->k);
        }
        if(count($size) != 2)
            $this->Error('Invalid size: ' . print_r($size, true));
        return $size;
    }

    protected function _beginpage($orientation, $size, $rotation)
    {
        $this->page++;
        $this->pages[$this->page]      = '';
        $this->PageLinks[$this->page]  = array();
        $this->state     = 2;
        $this->x         = $this->lMargin;
        $this->y         = $this->tMargin;
        $this->CurRotation = $rotation;
        $this->FontFamily = '';

        if(!$orientation)
            $orientation = $this->DefOrientation;
        else
            $orientation = strtoupper($orientation[0]);

        if($orientation != $this->CurOrientation)
        {
            if($orientation == 'P')
            {
                $this->wPt = $this->DefPageSize[0];
                $this->hPt = $this->DefPageSize[1];
            }
            else
            {
                $this->wPt = $this->DefPageSize[1];
                $this->hPt = $this->DefPageSize[0];
            }
            $this->w              = $this->wPt / $this->k;
            $this->h              = $this->hPt / $this->k;
            $this->CurOrientation = $orientation;
        }
        $this->CurPageSize      = array($this->wPt, $this->hPt);
        $this->PageBreakTrigger = $this->h - $this->bMargin;
    }

    protected function _endpage()
    {
        $this->state = 1;
    }

    // ============================================
    //  PROTECTED — TEXT HELPERS
    // ============================================
    protected function _escape($s)
    {
        return str_replace('\\', '\\\\',
            str_replace(')', '\\)',
                str_replace('(', '\\(', $s)));
    }

    protected function _dounderline($x, $y, $txt)
    {
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w  = $this->GetStringWidth($txt);
        return sprintf('%.2F %.2F %.2F %.2F re f',
            $x * $this->k,
            ($this->h - ($y - $up/1000*$this->FontSize)) * $this->k,
            $w * $this->k,
            -($ut/1000*$this->FontSizePt)*$this->k);
    }

    protected function _textstring($s)
    {
        return $this->_escape((string)$s);
    }

    // ============================================
    //  PROTECTED — FONT WIDTH TABLES
    // ============================================
    protected function _getfontwidths($font)
    {
        // Default: 600 for all (Courier-like)
        $cw = array_fill(0, 256, 600);

        switch($font)
        {
            case 'helvetica':
                $cw = array_fill(0,256,278);
                $cw[32]=278;$cw[33]=278;$cw[34]=355;$cw[35]=556;$cw[36]=556;$cw[37]=889;$cw[38]=667;$cw[39]=191;
                $cw[40]=333;$cw[41]=333;$cw[42]=389;$cw[43]=584;$cw[44]=278;$cw[45]=333;$cw[46]=278;$cw[47]=278;
                $cw[48]=556;$cw[49]=556;$cw[50]=556;$cw[51]=556;$cw[52]=556;$cw[53]=556;$cw[54]=556;$cw[55]=556;
                $cw[56]=556;$cw[57]=556;$cw[58]=278;$cw[59]=278;$cw[60]=584;$cw[61]=584;$cw[62]=584;$cw[63]=556;
                $cw[64]=1015;$cw[65]=667;$cw[66]=667;$cw[67]=722;$cw[68]=722;$cw[69]=667;$cw[70]=611;$cw[71]=778;
                $cw[72]=722;$cw[73]=278;$cw[74]=500;$cw[75]=667;$cw[76]=556;$cw[77]=833;$cw[78]=722;$cw[79]=778;
                $cw[80]=667;$cw[81]=778;$cw[82]=722;$cw[83]=667;$cw[84]=611;$cw[85]=722;$cw[86]=667;$cw[87]=944;
                $cw[88]=667;$cw[89]=667;$cw[90]=611;$cw[91]=278;$cw[92]=278;$cw[93]=278;$cw[94]=469;$cw[95]=556;
                $cw[96]=333;$cw[97]=556;$cw[98]=556;$cw[99]=500;$cw[100]=556;$cw[101]=556;$cw[102]=278;$cw[103]=556;
                $cw[104]=556;$cw[105]=222;$cw[106]=222;$cw[107]=500;$cw[108]=222;$cw[109]=833;$cw[110]=556;$cw[111]=556;
                $cw[112]=556;$cw[113]=556;$cw[114]=333;$cw[115]=500;$cw[116]=278;$cw[117]=556;$cw[118]=500;$cw[119]=722;
                $cw[120]=500;$cw[121]=500;$cw[122]=500;$cw[123]=334;$cw[124]=260;$cw[125]=334;$cw[126]=584;
                break;
            case 'helveticaB':
                $cw = array_fill(0,256,278);
                $cw[32]=278;$cw[33]=333;$cw[34]=474;$cw[35]=556;$cw[36]=556;$cw[37]=889;$cw[38]=722;$cw[39]=191;
                $cw[40]=333;$cw[41]=333;$cw[42]=389;$cw[43]=584;$cw[44]=278;$cw[45]=333;$cw[46]=278;$cw[47]=278;
                $cw[48]=556;$cw[49]=556;$cw[50]=556;$cw[51]=556;$cw[52]=556;$cw[53]=556;$cw[54]=556;$cw[55]=556;
                $cw[56]=556;$cw[57]=556;$cw[58]=333;$cw[59]=333;$cw[60]=584;$cw[61]=584;$cw[62]=584;$cw[63]=611;
                $cw[64]=975;$cw[65]=722;$cw[66]=722;$cw[67]=722;$cw[68]=722;$cw[69]=667;$cw[70]=611;$cw[71]=778;
                $cw[72]=722;$cw[73]=278;$cw[74]=556;$cw[75]=722;$cw[76]=611;$cw[77]=833;$cw[78]=722;$cw[79]=778;
                $cw[80]=667;$cw[81]=778;$cw[82]=722;$cw[83]=667;$cw[84]=611;$cw[85]=722;$cw[86]=667;$cw[87]=944;
                $cw[88]=667;$cw[89]=667;$cw[90]=611;$cw[91]=333;$cw[92]=278;$cw[93]=333;$cw[94]=584;$cw[95]=556;
                $cw[96]=333;$cw[97]=556;$cw[98]=611;$cw[99]=556;$cw[100]=611;$cw[101]=611;$cw[102]=333;$cw[103]=611;
                $cw[104]=611;$cw[105]=278;$cw[106]=278;$cw[107]=556;$cw[108]=278;$cw[109]=889;$cw[110]=611;$cw[111]=611;
                $cw[112]=611;$cw[113]=611;$cw[114]=389;$cw[115]=556;$cw[116]=333;$cw[117]=556;$cw[118]=556;$cw[119]=778;
                $cw[120]=556;$cw[121]=500;$cw[122]=500;$cw[123]=334;$cw[124]=260;$cw[125]=334;$cw[126]=584;
                break;
            case 'helveticaI':
                $cw = array_fill(0,256,278);
                $cw[32]=278;$cw[33]=278;$cw[34]=355;$cw[35]=556;$cw[36]=556;$cw[37]=889;$cw[38]=667;$cw[39]=191;
                $cw[40]=333;$cw[41]=333;$cw[42]=389;$cw[43]=584;$cw[44]=278;$cw[45]=333;$cw[46]=278;$cw[47]=278;
                $cw[48]=556;$cw[49]=556;$cw[50]=556;$cw[51]=556;$cw[52]=556;$cw[53]=556;$cw[54]=556;$cw[55]=556;
                $cw[56]=556;$cw[57]=556;$cw[58]=278;$cw[59]=278;$cw[60]=584;$cw[61]=584;$cw[62]=584;$cw[63]=556;
                $cw[64]=1015;$cw[65]=667;$cw[66]=667;$cw[67]=722;$cw[68]=722;$cw[69]=667;$cw[70]=611;$cw[71]=778;
                $cw[72]=722;$cw[73]=278;$cw[74]=500;$cw[75]=667;$cw[76]=556;$cw[77]=833;$cw[78]=722;$cw[79]=778;
                $cw[80]=667;$cw[81]=778;$cw[82]=722;$cw[83]=667;$cw[84]=611;$cw[85]=722;$cw[86]=667;$cw[87]=944;
                $cw[88]=667;$cw[89]=667;$cw[90]=611;$cw[91]=278;$cw[92]=278;$cw[93]=278;$cw[94]=469;$cw[95]=556;
                $cw[96]=333;$cw[97]=556;$cw[98]=556;$cw[99]=500;$cw[100]=556;$cw[101]=556;$cw[102]=278;$cw[103]=556;
                $cw[104]=556;$cw[105]=222;$cw[106]=222;$cw[107]=500;$cw[108]=222;$cw[109]=833;$cw[110]=556;$cw[111]=556;
                $cw[112]=556;$cw[113]=556;$cw[114]=333;$cw[115]=500;$cw[116]=278;$cw[117]=556;$cw[118]=500;$cw[119]=722;
                $cw[120]=500;$cw[121]=500;$cw[122]=500;$cw[123]=334;$cw[124]=260;$cw[125]=334;$cw[126]=584;
                break;
            case 'helveticaBI':
                $cw = array_fill(0,256,278);
                $cw[32]=278;$cw[33]=333;$cw[34]=474;$cw[35]=556;$cw[36]=556;$cw[37]=889;$cw[38]=722;$cw[39]=191;
                $cw[40]=333;$cw[41]=333;$cw[42]=389;$cw[43]=584;$cw[44]=278;$cw[45]=333;$cw[46]=278;$cw[47]=278;
                $cw[48]=556;$cw[49]=556;$cw[50]=556;$cw[51]=556;$cw[52]=556;$cw[53]=556;$cw[54]=556;$cw[55]=556;
                $cw[56]=556;$cw[57]=556;$cw[58]=333;$cw[59]=333;$cw[60]=584;$cw[61]=584;$cw[62]=584;$cw[63]=611;
                $cw[64]=975;$cw[65]=722;$cw[66]=722;$cw[67]=722;$cw[68]=722;$cw[69]=667;$cw[70]=611;$cw[71]=778;
                $cw[72]=722;$cw[73]=278;$cw[74]=556;$cw[75]=722;$cw[76]=611;$cw[77]=833;$cw[78]=722;$cw[79]=778;
                $cw[80]=667;$cw[81]=778;$cw[82]=722;$cw[83]=667;$cw[84]=611;$cw[85]=722;$cw[86]=667;$cw[87]=944;
                $cw[88]=667;$cw[89]=667;$cw[90]=611;$cw[91]=333;$cw[92]=278;$cw[93]=333;$cw[94]=584;$cw[95]=556;
                $cw[96]=333;$cw[97]=556;$cw[98]=611;$cw[99]=556;$cw[100]=611;$cw[101]=611;$cw[102]=333;$cw[103]=611;
                $cw[104]=611;$cw[105]=278;$cw[106]=278;$cw[107]=556;$cw[108]=278;$cw[109]=889;$cw[110]=611;$cw[111]=611;
                $cw[112]=611;$cw[113]=611;$cw[114]=389;$cw[115]=556;$cw[116]=333;$cw[117]=556;$cw[118]=556;$cw[119]=778;
                $cw[120]=556;$cw[121]=500;$cw[122]=500;$cw[123]=334;$cw[124]=260;$cw[125]=334;$cw[126]=584;
                break;
            case 'times':
                $cw = array_fill(0,256,250);
                $cw[32]=250;$cw[33]=333;$cw[34]=408;$cw[35]=500;$cw[36]=500;$cw[37]=833;$cw[38]=778;$cw[39]=180;
                $cw[40]=333;$cw[41]=333;$cw[42]=500;$cw[43]=564;$cw[44]=250;$cw[45]=333;$cw[46]=250;$cw[47]=278;
                $cw[48]=500;$cw[49]=500;$cw[50]=500;$cw[51]=500;$cw[52]=500;$cw[53]=500;$cw[54]=500;$cw[55]=500;
                $cw[56]=500;$cw[57]=500;$cw[58]=278;$cw[59]=278;$cw[60]=564;$cw[61]=564;$cw[62]=564;$cw[63]=444;
                $cw[64]=921;$cw[65]=722;$cw[66]=667;$cw[67]=667;$cw[68]=722;$cw[69]=611;$cw[70]=556;$cw[71]=722;
                $cw[72]=722;$cw[73]=333;$cw[74]=389;$cw[75]=722;$cw[76]=889;$cw[77]=722;$cw[78]=722;$cw[79]=722;
                $cw[80]=556;$cw[81]=722;$cw[82]=667;$cw[83]=556;$cw[84]=611;$cw[85]=722;$cw[86]=722;$cw[87]=944;
                $cw[88]=722;$cw[89]=722;$cw[90]=611;$cw[91]=333;$cw[92]=278;$cw[93]=333;$cw[94]=469;$cw[95]=500;
                $cw[96]=333;$cw[97]=444;$cw[98]=500;$cw[99]=444;$cw[100]=500;$cw[101]=444;$cw[102]=333;$cw[103]=500;
                $cw[104]=500;$cw[105]=278;$cw[106]=278;$cw[107]=500;$cw[108]=278;$cw[109]=778;$cw[110]=500;$cw[111]=500;
                $cw[112]=500;$cw[113]=500;$cw[114]=333;$cw[115]=389;$cw[116]=278;$cw[117]=500;$cw[118]=500;$cw[119]=722;
                $cw[120]=500;$cw[121]=500;$cw[122]=444;$cw[123]=474;$cw[124]=474;$cw[125]=474;$cw[126]=444;
                break;
            case 'timesB':
                $cw = array_fill(0,256,250);
                $cw[32]=250;$cw[33]=333;$cw[34]=555;$cw[35]=500;$cw[36]=500;$cw[37]=1000;$cw[38]=833;$cw[39]=278;
                $cw[40]=333;$cw[41]=333;$cw[42]=500;$cw[43]=564;$cw[44]=250;$cw[45]=333;$cw[46]=250;$cw[47]=278;
                $cw[48]=500;$cw[49]=500;$cw[50]=500;$cw[51]=500;$cw[52]=500;$cw[53]=500;$cw[54]=500;$cw[55]=500;
                $cw[56]=500;$cw[57]=500;$cw[58]=278;$cw[59]=278;$cw[60]=564;$cw[61]=564;$cw[62]=564;$cw[63]=444;
                $cw[64]=921;$cw[65]=722;$cw[66]=722;$cw[67]=722;$cw[68]=722;$cw[69]=667;$cw[70]=611;$cw[71]=778;
                $cw[72]=722;$cw[73]=333;$cw[74]=389;$cw[75]=722;$cw[76]=889;$cw[77]=722;$cw[78]=722;$cw[79]=722;
                $cw[80]=556;$cw[81]=722;$cw[82]=667;$cw[83]=556;$cw[84]=611;$cw[85]=722;$cw[86]=722;$cw[87]=944;
                $cw[88]=722;$cw[89]=722;$cw[90]=611;$cw[91]=333;$cw[92]=278;$cw[93]=333;$cw[94]=584;$cw[95]=500;
                $cw[96]=333;$cw[97]=500;$cw[98]=556;$cw[99]=444;$cw[100]=556;$cw[101]=444;$cw[102]=333;$cw[103]=500;
                $cw[104]=556;$cw[105]=278;$cw[106]=333;$cw[107]=556;$cw[108]=278;$cw[109]=833;$cw[110]=556;$cw[111]=500;
                $cw[112]=556;$cw[113]=556;$cw[114]=444;$cw[115]=389;$cw[116]=333;$cw[117]=556;$cw[118]=500;$cw[119]=722;
                $cw[120]=500;$cw[121]=500;$cw[122]=444;$cw[123]=474;$cw[124]=474;$cw[125]=474;$cw[126]=444;
                break;
            case 'timesI':
            case 'timesBI':
                $cw = array_fill(0,256,250);
                $cw[32]=250;$cw[33]=333;$cw[34]=420;$cw[35]=500;$cw[36]=500;$cw[37]=833;$cw[38]=778;$cw[39]=180;
                $cw[40]=333;$cw[41]=333;$cw[42]=500;$cw[43]=564;$cw[44]=250;$cw[45]=333;$cw[46]=250;$cw[47]=278;
                $cw[48]=500;$cw[49]=500;$cw[50]=500;$cw[51]=500;$cw[52]=500;$cw[53]=500;$cw[54]=500;$cw[55]=500;
                $cw[56]=500;$cw[57]=500;$cw[58]=278;$cw[59]=278;$cw[60]=564;$cw[61]=564;$cw[62]=564;$cw[63]=444;
                $cw[64]=921;$cw[65]=722;$cw[66]=667;$cw[67]=667;$cw[68]=722;$cw[69]=611;$cw[70]=556;$cw[71]=722;
                $cw[72]=722;$cw[73]=333;$cw[74]=389;$cw[75]=722;$cw[76]=889;$cw[77]=722;$cw[78]=722;$cw[79]=722;
                $cw[80]=556;$cw[81]=722;$cw[82]=667;$cw[83]=556;$cw[84]=611;$cw[85]=722;$cw[86]=722;$cw[87]=944;
                $cw[88]=722;$cw[89]=722;$cw[90]=611;$cw[91]=333;$cw[92]=278;$cw[93]=333;$cw[94]=469;$cw[95]=500;
                $cw[96]=333;$cw[97]=444;$cw[98]=500;$cw[99]=444;$cw[100]=500;$cw[101]=444;$cw[102]=333;$cw[103]=500;
                $cw[104]=500;$cw[105]=278;$cw[106]=278;$cw[107]=500;$cw[108]=278;$cw[109]=778;$cw[110]=500;$cw[111]=500;
                $cw[112]=500;$cw[113]=500;$cw[114]=333;$cw[115]=389;$cw[116]=278;$cw[117]=500;$cw[118]=500;$cw[119]=722;
                $cw[120]=500;$cw[121]=500;$cw[122]=444;$cw[123]=474;$cw[124]=474;$cw[125]=474;$cw[126]=444;
                break;
            case 'courier':
            case 'courierB':
            case 'courierI':
            case 'courierBI':
                $cw = array_fill(0, 256, 600);
                break;
            case 'symbol':
            case 'zapfdingbats':
                $cw = array_fill(0, 256, 576);
                break;
        }
        return $cw;
    }

    protected function _getfontname($family, $style)
    {
        $map = array(
            'Helvetica'=>'Helvetica','HelveticaB'=>'Helvetica-Bold',
            'HelveticaI'=>'Helvetica-Oblique','HelveticaBI'=>'Helvetica-BoldOblique',
            'Times'=>'Times-Roman','TimesB'=>'Times-Bold',
            'TimesI'=>'Times-Italic','TimesBI'=>'Times-BoldItalic',
            'Courier'=>'Courier','CourierB'=>'Courier-Bold',
            'CourierI'=>'Courier-Oblique','CourierBI'=>'Courier-BoldOblique',
            'Symbol'=>'Symbol','ZapfDingbats'=>'ZapfDingbats'
        );
        return $map[$family . $style] ?? 'Helvetica';
    }

    // ============================================
    //  PROTECTED — IMAGE PARSERS
    // ============================================
    protected function _parsejpg($file)
    {
        $a = getimagesize($file);
        if(!$a) $this->Error('Missing or incorrect image file: ' . $file);
        $f    = fopen($file, 'rb');
        $data = '';
        while(!feof($f)) $data .= fread($f, 8192);
        fclose($f);
        return array('w'=>$a[0], 'h'=>$a[1], 'type'=>'jpg', 'data'=>$data, 'cs'=>'DeviceRGB', 'bpc'=>8);
    }

    protected function _parsepng($file)
    {
        $f = fopen($file, 'rb');
        if(!$f) $this->Error('Can\'t open image file: ' . $file);
        $info = $this->_parsepngstream($f);
        fclose($f);
        return $info;
    }

    protected function _parsepngstream($f)
    {
        if($this->_readstream($f, 8) != chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10))
            $this->Error('Not a PNG file');

        $this->_readstream($f, 4);
        $len  = $this->_readint($f);
        $type = $this->_readstream($f, 4);
        if($type != 'IHDR') $this->Error('Incorrect PNG file');

        $w    = $this->_readint($f);
        $h    = $this->_readint($f);
        $bpc  = ord($this->_readstream($f, 1));
        if($bpc > 8) $this->Error('16-bit depth not supported');
        $ct = ord($this->_readstream($f, 1));
        if($ct == 0 || $ct == 4)      $colspace = 'DeviceGray';
        elseif($ct == 2 || $ct == 6)  $colspace = 'DeviceRGB';
        elseif($ct == 3)              $colspace = 'Indexed';
        else $this->Error('Unknown color type: ' . $ct);

        $compress   = ord($this->_readstream($f, 1));
        $filter     = ord($this->_readstream($f, 1));
        $interlace  = ord($this->_readstream($f, 1));
        if($compress != 0)  $this->Error('Unknown compression method');
        if($filter != 0)    $this->Error('Unknown filter method');
        if($interlace != 0) $this->Error('Interlacing not supported');

        $this->_readstream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        $pal  = '';
        $trns = '';
        $data = '';

        do
        {
            $nlen  = $this->_readint($f);
            $ntype = $this->_readstream($f, 4);
            if($ntype == 'PLTE')
            {
                $pal = $this->_readstream($f, $nlen);
                $this->_readstream($f, 4);
            }
            elseif($ntype == 'tRNS')
            {
                $t = $this->_readstream($f, $nlen);
                if($ct == 0) $trns = array(ord(substr($t,1,1)));
                elseif($ct == 2) $trns = array(ord(substr($t,1,1)), ord(substr($t,3,1)), ord(substr($t,5,1)));
                else
                {
                    $pos = strpos($t, chr(0));
                    if($pos !== false) $trns = array($pos);
                }
                $this->_readstream($f, 4);
            }
            elseif($ntype == 'IDAT')
            {
                $data .= $this->_readstream($f, $nlen);
                $this->_readstream($f, 4);
            }
            elseif($ntype == 'IEND') break;
            else $this->_readstream($f, $nlen + 4);
        }
        while($nlen);

        if($colspace == 'Indexed' && empty($pal))
            $this->Error('Missing palette in PNG file');

        $info = array('w'=>$w, 'h'=>$h, 'type'=>'png', 'dp'=>$dp, 'ct'=>$ct, 'bpc'=>$bpc, 'cs'=>$colspace, 'data'=>$data);
        if(!empty($pal))  $info['pal'] = $pal;
        if(!empty($trns)) $info['trns'] = $trns;
        return $info;
    }

    protected function _parsegif($file)
    {
        if(!function_exists('imagecreatefromgif'))
            $this->Error('GIF requires GD library');
        $im = @imagecreatefromgif($file);
        if(!$im) $this->Error('Missing or incorrect GIF file: ' . $file);
        $tmp  = tempnam(sys_get_temp_dir(), 'gif');
        imagepng($im, $tmp);
        imagedestroy($im);
        $info = $this->_parsepng($tmp);
        unlink($tmp);
        $info['type'] = 'gif';
        return $info;
    }

    protected function _readstream($f, $n)
    {
        $res = '';
        while($n > 0 && !feof($f))
        {
            $s = fread($f, $n);
            if($s === false) $this->Error('Error reading stream');
            $n   -= strlen($s);
            $res .= $s;
        }
        if($n > 0) $this->Error('Unexpected end of stream');
        return $res;
    }

    protected function _readint($f)
    {
        $a = unpack('Ni', $this->_readstream($f, 4));
        return $a['i'];
    }

    // ============================================
    //  PROTECTED — PDF OUTPUT
    // ============================================
    protected function _out($s)
    {
        if($this->state == 2)
            $this->pages[$this->page] .= $s . "\n";
        elseif($this->state == 1)
            $this->_put($s);
    }

    protected function _put($s)
    {
        $this->buffer .= $s . "\n";
    }

    protected function _newobj()
    {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->_put($this->n . ' 0 obj');
        return $this->n;
    }

    // ============================================
    //  PROTECTED — FINAL DOCUMENT ASSEMBLY
    //  (This is the corrected _enddoc)
    // ============================================
    protected function _enddoc()
    {
        // 1) PDF header
        $this->_put('%PDF-' . $this->PDFVersion);

        // 2) Info object — Object 1
        $this->_newobj(); // n = 1
        $this->_put('<</Producer (FPDF ' . self::FPDF_VERSION . ')');
        if($this->title    !== '') $this->_put('/Title ('   . $this->_textstring($this->title)    . ')');
        if($this->author   !== '') $this->_put('/Author ('  . $this->_textstring($this->author)   . ')');
        if($this->subject  !== '') $this->_put('/Subject (' . $this->_textstring($this->subject)  . ')');
        if($this->keywords !== '') $this->_put('/Keywords ('. $this->_textstring($this->keywords) . ')');
        if($this->creator  !== '') $this->_put('/Creator (' . $this->_textstring($this->creator)  . ')');
        $this->_put('/CreationDate (D:' . date('YmdHis') . ')');
        $this->_put('>>');
        $this->_put('endobj');

        // 3) Pages object — Object 2
        $pagesObjId = $this->_newobj(); // n = 2
        // We'll come back and fill this after we know page object IDs
        $pagesObjOffset = strlen($this->buffer);
        $pagesPlaceholder = $pagesObjOffset; // We'll overwrite

        // We can't know page obj IDs yet, so use a placeholder approach:
        // First, count how many objects we need:
        // - Font objects: count($this->fonts)
        // - Image objects: count($this->images) (some images may have palette sub-objects)
        // - Per page: Page object + Content stream object

        // 4) Font objects
        foreach($this->fonts as $k => &$font)
        {
            $font['n'] = $this->_newobj();
            $this->_put('<</Type /Font /Subtype /Type1 /BaseFont /' . $font['name'] . '>>');
            $this->_put('endobj');
        }
        unset($font);

        // 5) Image objects
        foreach($this->images as $file => &$info)
        {
            $info['n'] = $this->_newobj();
            $this->_put('<</Type /XObject /Subtype /Image /Width ' . $info['w'] . ' /Height ' . $info['h']);

            if($info['type'] == 'jpg')
            {
                $this->_put('/ColorSpace /DeviceRGB /BitsPerComponent 8');
            }
            else
            {
                // PNG or GIF
                if(isset($info['cs']))
                {
                    if($info['cs'] == 'DeviceGray')
                        $this->_put('/ColorSpace /DeviceGray');
                    elseif($info['cs'] == 'DeviceRGB')
                        $this->_put('/ColorSpace /DeviceRGB');
                    elseif($info['cs'] == 'Indexed')
                        $this->_put('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal'])/3-1) . ' ' . ($this->n*1+1) . ' 0 R]');
                }
                else
                    $this->_put('/ColorSpace /DeviceRGB');

                $this->_put('/BitsPerComponent ' . (isset($info['bpc']) ? $info['bpc'] : 8));
                if(isset($info['dp']))
                    $this->_put('/DecodeParms <' . $info['dp'] . '>');
            }

            $this->_put('/Filter /FlateDecode');
            $data = $info['data'];
            if($info['type'] == 'jpg')
                $data = gzcompress($data);
            $this->_put('/Length ' . strlen($data) . '>>');
            $this->_put('stream');
            $this->_put($data);
            $this->_put('endstream');
            $this->_put('endobj');

            // Palette sub-object for indexed PNG
            if(($info['type'] == 'png' || $info['type'] == 'gif') && isset($info['pal']))
            {
                $this->_newobj();
                $pal = gzcompress($info['pal']);
                $this->_put('<</Length ' . strlen($pal) . '>>');
                $this->_put('stream');
                $this->_put($pal);
                $this->_put('endstream');
                $this->_put('endobj');
            }
        }
        unset($info);

        // 6) Page objects + content streams
        $pageObjIds = array();
        $numPages  = count($this->pages);

        for($i = 1; $i <= $numPages; $i++)
        {
            // Page dictionary object
            $pageObjIds[$i] = $this->_newobj();
            $this->_put('<</Type /Page /Parent ' . $pagesObjId . ' 0 R');

            // Resources
            $this->_put('/Resources <<');
            // Fonts
            $this->_put('/Font <<');
            foreach($this->fonts as $k => $font)
                $this->_put('/F' . $font['i'] . ' ' . $font['n'] . ' 0F');
            $this->_put('>>');
            // Images
            if(!empty($this->images))
            {
                $this->_put('/XObject <<');
                foreach($this->images as $file => $img)
                    $this->_put('/I' . $img['i'] . ' ' . $img['n'] . ' 0 R');
                $this->_put('>>');
            }
            $this->_put('>>');

            // Content stream reference
            $this->_put('/Contents ' . ($this->n + 1) . ' 0 R');
            $this->_put('>>');
            $this->_put('endobj');

            // Content stream object
            $this->_newobj();
            $content = $this->pages[$i];
            if($this->AliasNbPages)
                $content = str_replace($this->AliasNbPages, $numPages, $content);
            if($this->compress)
                $content = gzcompress($content);
            $this->_put('<</Length ' . strlen($content) . '>>');
            $this->_put('stream');
            $this->_put($content);
            $this->_put('endstream');
            $this->_put('endobj');
        }

        // 7) Now go back and fill in the Pages object
        // Replace the placeholder with actual Kids array
        $kidsStr = '/Kids [';
        for($i = 1; $i <= $numPages; $i++)
            $kidsStr .= $pageObjIds[$i] . ' 0 R ';
        $kidsStr .= ']';

        // Rebuild the Pages object content at its stored offset
        $pagesContent  = $pagesObjId . ' 0 obj' . "\n";
        $pagesContent .= '<</Type /Pages ' . $kidsStr . ' /Count ' . $numPages;
        $pagesContent .= sprintf(' /MediaBox [0 0 %.2F %.2F]', $this->wPt, $this->hPt);
        $pagesContent .= '>>' . "\n";
        $pagesContent .= 'endobj' . "\n";

        // Splice into buffer
        $before  = substr($this->buffer, 0, $this->offsets[$pagesObjId]);
        $after   = substr($this->buffer, $pagesObjOffset);
        $this->buffer = $before . $pagesContent . $after;

        // 8) Cross-reference table
        $offset = strlen($this->buffer);
        $this->_put('xref');
        $this->_put('0 ' . ($this->n + 1));
        $this->_put('0000000000 65535 f ');
        for($i = 1; $i <= $this->n; $i++)
            $this->_put(sprintf('%010d 00000 n ', $this->offsets[$i]));

        // 9) Trailer
        $this->_put('trailer');
        $this->_put('<</Size ' . ($this->n + 1) . ' /Root ' . $pagesObjId . ' 0 R /Info 1 0 R>>');
        $this->_put('startxref');
        $this->_put($offset);
        $this->_put('%%EOF');

        $this->state = 3;
    }

} // End class FPDF

} // End if(!class_exists)