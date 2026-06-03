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
            // Print Product Ref and Label
            $pdf->SetFont('', 'B', $default_font_size);
            $text = $line->ref . " - " . $line->product_label;
            $pdf->MultiCell(120, 5, $text, 0, 'L', 0, 0, $this->marge_gauche, $curY);
            
            // Print Qty
            $pdf->MultiCell(40, 5, "Qté Cmd: " . $line->qty, 0, 'R', 0, 1, $this->page_largeur - $this->marge_droite - 40, $curY);
            
            $curY = $pdf->GetY();
            $pdf->SetFont('', '', $default_font_size - 1);

            // Fetch BOM
            if (!empty($line->fk_product)) {
                $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "bom_bom WHERE fk_product = " . (int)$line->fk_product . " AND status = 1 LIMIT 1";
                $res = $this->db->query($sql);
                if ($res && $this->db->num_rows($res) > 0) {
                    $obj = $this->db->fetch_object($res);
                    $bom_id = $obj->rowid;

                    // Fetch Components
                    $sql_bom = "SELECT b.qty, p.ref, p.label FROM " . MAIN_DB_PREFIX . "bom_bomline b INNER JOIN " . MAIN_DB_PREFIX . "product p ON b.fk_product = p.rowid WHERE b.fk_bom = " . (int)$bom_id;
                    $res_bom = $this->db->query($sql_bom);
                    if ($res_bom && $this->db->num_rows($res_bom) > 0) {
                        while ($comp = $this->db->fetch_object($res_bom)) {
                            $needed_qty = $comp->qty * $line->qty;
                            $comp_text = "   - " . $comp->ref . " | " . $comp->label;
                            $pdf->MultiCell(120, 5, $comp_text, 0, 'L', 0, 0, $this->marge_gauche + 10, $curY);
                            $pdf->MultiCell(40, 5, "Besoin: " . $needed_qty, 0, 'R', 0, 1, $this->page_largeur - $this->marge_droite - 40, $curY);
                            $curY = $pdf->GetY();
                        }
                    } else {
                        // Empty BOM
                        $pdf->MultiCell(0, 5, "   Aucune nomenclature associée", 0, 'L', 0, 1, $this->marge_gauche + 10, $curY);
                        $curY = $pdf->GetY();
                    }
                } else {
                    // No active BOM found
                    $pdf->MultiCell(0, 5, "   Aucune nomenclature associée", 0, 'L', 0, 1, $this->marge_gauche + 10, $curY);
                    $curY = $pdf->GetY();
                }
            } else {
                // Free text line (no fk_product)
                $pdf->MultiCell(0, 5, "   Aucune nomenclature associée", 0, 'L', 0, 1, $this->marge_gauche + 10, $curY);
                $curY = $pdf->GetY();
            }
            
            $curY += 5; // space before next orderline
            
            // Check page break
            if ($curY > $this->page_hauteur - $this->marge_basse - 20) {
                $pdf->AddPage();
                $this->_pagehead($pdf, $object, 0, $outputlangs);
                $curY = 30;
                $pdf->SetY($curY);
            }
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
        
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $pdf->SetXY($this->marge_gauche, $this->marge_haute);
        $pdf->MultiCell(100, 5, "CALCUL DE STOCK", 0, 'L');
        
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($this->marge_gauche, $this->marge_haute + 8);
        $pdf->MultiCell(100, 5, "Commande: " . $object->ref, 0, 'L');
        $date = dol_print_date($object->date, 'daytext');
        $pdf->SetXY($this->marge_gauche, $this->marge_haute + 13);
        $pdf->MultiCell(100, 5, "Date: " . $date, 0, 'L');

        if ($showaddress && is_object($object->thirdparty)) {
            $pdf->SetXY($this->page_largeur / 2, $this->marge_haute);
            $pdf->MultiCell($this->page_largeur / 2 - $this->marge_droite, 5, $object->thirdparty->name, 0, 'R');
        }
        
        return 0;
    }
}
