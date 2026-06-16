<?php

require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

class pdf_calcul_de_stock extends ModelePDFCommandes
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $format;
    public $marge_gauche;
    public $marge_droite;
    public $marge_haute;
    public $marge_basse;
    public $page_largeur;
    public $page_hauteur;

    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $this->db = $db;
        $this->name = "calcul_de_stock";
        $this->description = "Modèle Calcul de Stock / Nomenclature";
        $this->type = 'pdf';
        
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
        $this->marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
        $this->marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
        $this->marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $user, $langs, $conf, $mysoc;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        $objectref = dol_sanitizeFileName($object->ref);
        $dir = $conf->commande->multidir_output[$object->entity] . "/" . $objectref;
        $file = $dir . "/" . $objectref . "_calcul_de_stock.pdf";

        if (!file_exists($dir)) {
            dol_mkdir($dir);
        }

        $pdf = pdf_getInstance($this->format);
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetAutoPageBreak(1, 0);

        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        $pdf->SetFont(pdf_getPDFFont($outputlangs));
        $pdf->Open();
        $pdf->AddPage();

        $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
        $pdf->SetSubject("Calcul de Stock");
        $pdf->SetCreator("Dolibarr");

        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

        // Header
        $this->_pagehead($pdf, $object, 1, $outputlangs);

        // Position for lines
        $tab_top = 40;
        $pdf->SetY($tab_top);
        $curY = $tab_top;

        // Loop on lines
        foreach ($object->lines as $line) {
            // Check page break before starting a new main product block
            if ($curY > $this->page_hauteur - $this->marge_basse - 30) {
                $pdf->AddPage();
                $this->_pagehead($pdf, $object, 0, $outputlangs);
                $curY = 40;
                $pdf->SetY($curY);
            }

            $pdf->SetFont('', 'B', $default_font_size + 1);
            $text = " Produit: " . $line->ref . " - " . $line->product_label;
            
            $h_prod1 = $pdf->getStringHeight(140, $text);
            $h_prod2 = $pdf->getStringHeight($this->page_largeur - $this->marge_gauche - $this->marge_droite - 140, "Qté à produire: " . $line->qty . " ");
            $h_prod = max($h_prod1, $h_prod2);
            if ($h_prod < 8) $h_prod = 8;
            else $h_prod += 2; // Add padding if wrapping occurs
            
            // Big colored row for the product
            $pdf->SetFillColor(230, 240, 255); // Light blue
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $h_prod, 'DF');
            
            $pdf->MultiCell(140, $h_prod, $text, 0, 'L', 0, 0, $this->marge_gauche, $curY + ($h_prod - $h_prod1)/2);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 140, $h_prod, "Qté à produire: " . $line->qty . " ", 0, 'R', 0, 0, $this->marge_gauche + 140, $curY + ($h_prod - $h_prod2)/2);
            
            $curY += $h_prod + 1;
            $pdf->SetY($curY);
            
            // Sub table headers for components
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite - 10, 6, 'F');
            $pdf->MultiCell(30, 6, "Réf", 0, 'L', 0, 0, $this->marge_gauche + 12, $curY + 1);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 65, 6, "Libellé", 0, 'L', 0, 0, $this->marge_gauche + 42, $curY + 1);
            $pdf->MultiCell(25, 6, "Besoin", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 25, $curY + 1);
            
            // Top and bottom header borders
            $pdf->Line($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_droite, $curY);
            $pdf->Line($this->marge_gauche + 10, $curY + 6, $this->page_largeur - $this->marge_droite, $curY + 6);
            
            // Vertical header borders
            $pdf->Line($this->marge_gauche + 10, $curY, $this->marge_gauche + 10, $curY + 6);
            $pdf->Line($this->marge_gauche + 40, $curY, $this->marge_gauche + 40, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 25, $curY, $this->page_largeur - $this->marge_droite - 25, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + 6);
            
            $curY += 6;
            $pdf->SetY($curY);
            $pdf->SetFont('', '', $default_font_size - 1);

            // Fetch BOM
            $has_components = false;
            if (!empty($line->fk_product)) {
                require_once DOL_DOCUMENT_ROOT . '/custom/factory/class/factory.class.php';
                $factory = new Factory($this->db);

                // Fetch Components using getChildsArbo which uses llx_product_factory
                $components = $factory->getChildsArbo($line->fk_product);
                
                if (!empty($components) && is_array($components)) {
                    $has_components = true;
                    $fill = false;
                    
                    $product_static = new Product($this->db);
                    
                    foreach ($components as $compId => $compData) {
                        // Check page break inside components
                        if ($curY > $this->page_hauteur - $this->marge_basse - 10) {
                            $pdf->Line($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_droite, $curY); // bottom line before break
                            $pdf->AddPage();
                            $this->_pagehead($pdf, $object, 0, $outputlangs);
                            $curY = 40;
                            $pdf->SetY($curY);
                            $pdf->Line($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_droite, $curY); // top line after break
                        }
                        
                        $product_static->fetch($compId);
                        
                        // $compData[1] is qty
                        // $compData[3] is label
                        $comp_qty = $compData[1];
                        $comp_ref = $product_static->ref;
                        $comp_label = !empty($product_static->label) ? $product_static->label : $compData[3];
                        
                        $needed_qty = $comp_qty * $line->qty;
                        
                        $h1 = $pdf->getStringHeight(30, " " . $comp_ref);
                        $h2 = $pdf->getStringHeight($this->page_largeur - $this->marge_gauche - $this->marge_droite - 65, " " . $comp_label);
                        $h3 = $pdf->getStringHeight(25, $needed_qty . " ");
                        $h = max($h1, $h2, $h3);
                        if ($h < 6) $h = 6;
                        
                        // Alternate row color
                        if ($fill) {
                            $pdf->SetFillColor(250, 250, 250);
                            $pdf->Rect($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite - 10, $h, 'F');
                        }
                        
                        $pdf->MultiCell(30, $h, " " . $comp_ref, 0, 'L', 0, 0, $this->marge_gauche + 10, $curY + ($h - $h1)/2);
                        $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 65, $h, " " . $comp_label, 0, 'L', 0, 0, $this->marge_gauche + 40, $curY + ($h - $h2)/2);
                        $pdf->MultiCell(25, $h, $needed_qty . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 25, $curY + ($h - $h3)/2);
                        
                        // Vertical borders
                        $pdf->Line($this->marge_gauche + 10, $curY, $this->marge_gauche + 10, $curY + $h);
                        $pdf->Line($this->marge_gauche + 40, $curY, $this->marge_gauche + 40, $curY + $h);
                        $pdf->Line($this->page_largeur - $this->marge_droite - 25, $curY, $this->page_largeur - $this->marge_droite - 25, $curY + $h);
                        $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + $h);
                        
                        $curY += $h;
                        $pdf->SetY($curY);
                        $fill = !$fill;
                    }
                    // Bottom line of the table
                    $pdf->Line($this->marge_gauche + 10, $curY, $this->page_largeur - $this->marge_droite, $curY);
                }
            }
            
            if (!$has_components) {
                $pdf->SetTextColor(150, 150, 150);
                $pdf->SetFont('', 'I', $default_font_size - 1);
                $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 10, 8, "Aucune nomenclature associée ou aucun composant trouvé", 'LRB', 'C', 0, 1, $this->marge_gauche + 10, $curY);
                $pdf->SetTextColor(0, 0, 0);
                $curY = $pdf->GetY();
            }
            
            $curY += 8; // space before next orderline
        }

        $pdf->Close();
        $pdf->Output($file, 'F');
        
        $this->result = array('fullpath' => $file);
        return 1;
    }

    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $conf, $langs;
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        
        // Title: CALCUL DE STOCK
        $pdf->SetTextColor(0, 50, 100); // Dark Blue
        $pdf->SetFont('', 'B', $default_font_size + 6);
        $pdf->SetXY($this->marge_gauche, $this->marge_haute);
        $pdf->MultiCell(100, 10, "CALCUL DE STOCK", 0, 'L');
        
        // Commande Ref inside a light gray box
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', 'B', $default_font_size + 2);
        $pdf->SetXY($this->page_largeur - $this->marge_droite - 70, $this->marge_haute);
        $pdf->MultiCell(70, 8, "COMMANDE : " . $object->ref, 1, 'C', 1);

        // Date
        $date = dol_print_date($object->date, 'daytext');
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($this->page_largeur - $this->marge_droite - 70, $this->marge_haute + 10);
        $pdf->MultiCell(70, 5, "Date : " . $date, 0, 'R');

        // Customer
        if ($showaddress && is_object($object->thirdparty)) {
            $pdf->SetFont('', 'B', $default_font_size + 1);
            $pdf->SetXY($this->marge_gauche, $this->marge_haute + 12);
            $pdf->MultiCell(100, 5, "Client : " . $object->thirdparty->name, 0, 'L');
        }
        
        // Horizontal separator
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line($this->marge_gauche, $this->marge_haute + 22, $this->page_largeur - $this->marge_droite, $this->marge_haute + 22);
        
        return 0;
    }
}
