<?php

require_once DOL_DOCUMENT_ROOT . '/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/calcul_stock/class/calculstockreservation.class.php';

class pdf_calcul_de_besoin extends ModelePDFCommandes
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
        $this->name = "calcul_de_besoin";
        $this->description = "Modèle Calcul de Besoin / Nomenclature";
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
        $file = $dir . "/" . $objectref . "_calcul_de_besoin.pdf";

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
        $pdf->SetSubject("Calcul de Besoin");
        $pdf->SetCreator("Dolibarr");

        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

        // Header
        $this->_pagehead($pdf, $object, 1, $outputlangs);

        // Position for lines
        $tab_top = 40;
        $pdf->SetY($tab_top);
        $curY = $tab_top;

        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);
        $extrafields->fetch_name_optionals_label('commandedet');
        require_once DOL_DOCUMENT_ROOT . '/custom/factory/class/factory.class.php';
        $factory = new Factory($this->db);
        $reservation_static = new CalculStockReservation($this->db);
        $product_static = new Product($this->db);

        $global_components = array();

        // Pre-process lines to group by fusion_group_id
        $display_blocks = array();
        foreach ($object->lines as $line) {
            $line->fetch_optionals();
            $fusion_id = isset($line->array_options['options_fusion_group_id']) ? trim($line->array_options['options_fusion_group_id']) : '';

            if (!empty($fusion_id)) {
                if (!isset($display_blocks['fusion_' . $fusion_id])) {
                    $display_blocks['fusion_' . $fusion_id] = array(
                        'type' => 'fused',
                        'fusion_id' => $fusion_id,
                        'lines' => array(),
                        'qty' => 0,
                        'refs' => array(),
                        'labels' => array()
                    );
                }
                $display_blocks['fusion_' . $fusion_id]['lines'][] = $line;
                $display_blocks['fusion_' . $fusion_id]['qty'] += $line->qty;
                $display_blocks['fusion_' . $fusion_id]['refs'][] = $line->ref;
                $display_blocks['fusion_' . $fusion_id]['labels'][] = $line->product_label;
            } else {
                $display_blocks['line_' . $line->id] = array(
                    'type' => 'single',
                    'line' => $line
                );
            }
        }

        // Loop on blocks
        foreach ($display_blocks as $blockId => $block) {
            // Check page break before starting a new main product block
            if ($curY > $this->page_hauteur - $this->marge_basse - 30) {
                $pdf->AddPage();
                $this->_pagehead($pdf, $object, 0, $outputlangs);
                $curY = 40;
                $pdf->SetY($curY);
            }

            $aggregated_components = array();

            if ($block['type'] == 'single') {
                $line = $block['line'];
                $text_title = " Produit: " . $line->ref . " - " . $line->product_label;
                $header_qty = $line->qty;

                if (!empty($line->fk_product)) {
                    $comps = $factory->getChildsArbo($line->fk_product);
                    if (!empty($comps) && is_array($comps)) {
                        foreach ($comps as $compId => $compData) {
                            $aggregated_components[$compId] = array(
                                'compData' => $compData,
                                'needed_qty' => $compData[1] * $line->qty,
                                'lines' => array($line)
                            );
                        }
                    }
                }
            } else {
                $unique_refs = array_unique($block['refs']);
                $text_title = " Groupe de fusion: " . $block['fusion_id'] . " (" . implode(', ', $unique_refs) . ")";
                $header_qty = $block['qty'];

                foreach ($block['lines'] as $line) {
                    if (!empty($line->fk_product)) {
                        $comps = $factory->getChildsArbo($line->fk_product);
                        if (!empty($comps) && is_array($comps)) {
                            foreach ($comps as $compId => $compData) {
                                if (!isset($aggregated_components[$compId])) {
                                    $aggregated_components[$compId] = array(
                                        'compData' => $compData,
                                        'needed_qty' => 0,
                                        'lines' => array()
                                    );
                                }
                                $aggregated_components[$compId]['needed_qty'] += $compData[1] * $line->qty;
                                $aggregated_components[$compId]['lines'][] = $line;
                            }
                        }
                    }
                }
            }

            // Calculate overall status text
            $status_text = "";
            if (!empty($aggregated_components)) {
                $total_comps = count($aggregated_components);
                $reserved_comps = 0;
                $consumed_comps = 0;

                foreach ($aggregated_components as $compId => &$aggData) {
                    $comp_total_lines = count($aggData['lines']);
                    $comp_consumed_lines = 0;
                    $comp_reserved_lines = 0;

                    foreach ($aggData['lines'] as $l) {
                        if ($reservation_static->fetchByLineAndProduct($l->id, $compId) > 0) {
                            if ($reservation_static->status == 1)
                                $comp_consumed_lines++;
                            elseif ($reservation_static->status == 0)
                                $comp_reserved_lines++;
                        }
                    }

                    if ($comp_consumed_lines == $comp_total_lines) {
                        $consumed_comps++;
                        $aggData['status_text'] = "Consommé";
                        $aggData['is_consumed'] = true;
                    } elseif ($comp_reserved_lines + $comp_consumed_lines == $comp_total_lines) {
                        $reserved_comps++;
                        $aggData['status_text'] = "Réservé";
                        $aggData['is_consumed'] = false;
                    } elseif ($comp_reserved_lines > 0 || $comp_consumed_lines > 0) {
                        $aggData['status_text'] = "Rés. Partiel";
                        $aggData['is_consumed'] = false;
                    } else {
                        $aggData['status_text'] = "Non réservé";
                        $aggData['is_consumed'] = false;
                    }
                }
                unset($aggData);

                if ($consumed_comps == $total_comps) {
                    $status_text = " (CONSOMMÉ)";
                } elseif ($reserved_comps + $consumed_comps == $total_comps) {
                    $status_text = " (RÉSERVÉ)";
                } elseif ($reserved_comps > 0 || $consumed_comps > 0) {
                    $status_text = " (RÉSERVÉ PARTIEL)";
                } else {
                    $status_text = " (NON RÉSERVÉ)";
                }
            }

            $pdf->SetFont('', 'B', $default_font_size + 1);
            $text = $text_title . $status_text;

            $h_prod1 = $pdf->getStringHeight(140, $text);
            $h_prod2 = $pdf->getStringHeight($this->page_largeur - $this->marge_gauche - $this->marge_droite - 140, "Qté à produire: " . $header_qty . " ");
            $h_prod = max($h_prod1, $h_prod2);
            if ($h_prod < 8)
                $h_prod = 8;
            else
                $h_prod += 2; // Add padding if wrapping occurs

            // Big colored row for the product
            $pdf->SetFillColor(230, 240, 255); // Light blue
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $h_prod, 'DF');

            $pdf->MultiCell(140, $h_prod, $text, 0, 'L', 0, 0, $this->marge_gauche, $curY + ($h_prod - $h_prod1) / 2);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 140, $h_prod, "Qté à produire: " . $header_qty . " ", 0, 'R', 0, 0, $this->marge_gauche + 140, $curY + ($h_prod - $h_prod2) / 2);

            $curY += $h_prod + 1;
            $pdf->SetY($curY);

            // Sub table headers for components
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetDrawColor(120, 120, 120);
            $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, 6, 'F');
            $pdf->MultiCell(25, 6, "Réf", 0, 'L', 0, 0, $this->marge_gauche + 2, $curY + 1);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 113, 6, "Libellé", 0, 'L', 0, 0, $this->marge_gauche + 27, $curY + 1);
            $pdf->MultiCell(20, 6, "Statut", 0, 'C', 0, 0, $this->page_largeur - $this->marge_droite - 88, $curY + 1);
            $pdf->MultiCell(14, 6, "Besoin", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 68, $curY + 1);
            $pdf->MultiCell(14, 6, "Stock", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 54, $curY + 1);
            $pdf->MultiCell(20, 6, "Envoyé", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 40, $curY + 1);
            $pdf->MultiCell(20, 6, "Reçu", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 20, $curY + 1);

            // Top and bottom header borders
            $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
            $pdf->Line($this->marge_gauche, $curY + 6, $this->page_largeur - $this->marge_droite, $curY + 6);

            // Vertical header borders
            $pdf->Line($this->marge_gauche, $curY, $this->marge_gauche, $curY + 6);
            $pdf->Line($this->marge_gauche + 25, $curY, $this->marge_gauche + 25, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 88, $curY, $this->page_largeur - $this->marge_droite - 88, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 68, $curY, $this->page_largeur - $this->marge_droite - 68, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 54, $curY, $this->page_largeur - $this->marge_droite - 54, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 40, $curY, $this->page_largeur - $this->marge_droite - 40, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 20, $curY, $this->page_largeur - $this->marge_droite - 20, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + 6);

            $curY += 6;
            $pdf->SetY($curY);
            $pdf->SetFont('', '', $default_font_size - 1);

            // Print components
            $has_components = false;
            if (!empty($aggregated_components)) {
                $has_components = true;
                $fill = false;

                foreach ($aggregated_components as $compId => $aggData) {
                    $compData = $aggData['compData'];
                    $needed_qty = $aggData['needed_qty'];
                    $comp_status_text = $aggData['status_text'];
                    $is_consumed = $aggData['is_consumed'];

                    // Check page break inside components
                    if ($curY > $this->page_hauteur - $this->marge_basse - 10) {
                        $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY); // bottom line before break
                        $pdf->AddPage();
                        $this->_pagehead($pdf, $object, 0, $outputlangs);
                        $curY = 40;
                        $pdf->SetY($curY);
                        $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY); // top line after break
                    }

                    $product_static->fetch($compId);
                    $product_static->load_stock();

                    $stock_qty = 0;
                    $fk_entrepot = !empty($compData['fk_entrepot']) ? $compData['fk_entrepot'] : 0;
                    if ($fk_entrepot > 0 && isset($product_static->stock_warehouse[$fk_entrepot])) {
                        $stock_qty = $product_static->stock_warehouse[$fk_entrepot]->real;
                    } else {
                        $stock_qty = $product_static->stock_reel;
                    }
                    $stock_qty = round($stock_qty, 5);

                    $comp_ref = $product_static->ref;
                    $comp_label = (!empty($product_static->label) ? $product_static->label : $compData[3]);

                    $stock_qty_display = $is_consumed ? "-" : $stock_qty;

                    if (!isset($global_components[$compId])) {
                        $global_components[$compId] = array(
                            'ref' => $comp_ref,
                            'label' => $comp_label,
                            'needed' => 0,
                            'stock' => $stock_qty
                        );
                    }
                    if (!$is_consumed) {
                        $global_components[$compId]['needed'] += $needed_qty;
                    }

                    $h1 = $pdf->getStringHeight(25, " " . $comp_ref);
                    $h2 = $pdf->getStringHeight($this->page_largeur - $this->marge_gauche - $this->marge_droite - 113, " " . $comp_label);
                    $h2_status = $pdf->getStringHeight(20, " " . $comp_status_text);
                    $h3 = $pdf->getStringHeight(14, $needed_qty . " ");
                    $h4 = $pdf->getStringHeight(14, $stock_qty_display . " ");
                    $h = max($h1, $h2, $h2_status, $h3, $h4);
                    if ($h < 6)
                        $h = 6;

                    // Highlight row if stock is less than needed
                    if (!$is_consumed && $stock_qty < $needed_qty) {
                        $pdf->SetFillColor(255, 235, 235); // light red
                        $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $h, 'F');
                    } elseif ($fill) {
                        $pdf->SetFillColor(250, 250, 250);
                        $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $h, 'F');
                    }

                    $pdf->MultiCell(25, $h, " " . $comp_ref, 0, 'L', 0, 0, $this->marge_gauche, $curY + ($h - $h1) / 2);
                    $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 113, $h, " " . $comp_label, 0, 'L', 0, 0, $this->marge_gauche + 25, $curY + ($h - $h2) / 2);
                    $pdf->MultiCell(20, $h, $comp_status_text, 0, 'C', 0, 0, $this->page_largeur - $this->marge_droite - 88, $curY + ($h - $h2_status) / 2);
                    $pdf->MultiCell(14, $h, $needed_qty . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 68, $curY + ($h - $h3) / 2);
                    $pdf->MultiCell(14, $h, $stock_qty_display . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 54, $curY + ($h - $h4) / 2);
                    $pdf->MultiCell(20, $h, "", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 40, $curY);
                    $pdf->MultiCell(20, $h, "", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 20, $curY);

                    // Vertical borders
                    $pdf->Line($this->marge_gauche, $curY, $this->marge_gauche, $curY + $h);
                    $pdf->Line($this->marge_gauche + 25, $curY, $this->marge_gauche + 25, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite - 88, $curY, $this->page_largeur - $this->marge_droite - 88, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite - 68, $curY, $this->page_largeur - $this->marge_droite - 68, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite - 54, $curY, $this->page_largeur - $this->marge_droite - 54, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite - 40, $curY, $this->page_largeur - $this->marge_droite - 40, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite - 20, $curY, $this->page_largeur - $this->marge_droite - 20, $curY + $h);
                    $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + $h);

                    // Horizontal border (top of the row)
                    $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);

                    $curY += $h;
                    $pdf->SetY($curY);
                    $fill = !$fill;
                }
                // Bottom line of the table
                $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
            }

            if (!$has_components) {
                $pdf->SetTextColor(150, 150, 150);
                $pdf->SetFont('', 'I', $default_font_size - 1);
                $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 8, "Aucune nomenclature associée ou aucun composant trouvé", 'LRB', 'C', 0, 1, $this->marge_gauche, $curY);
                $pdf->SetTextColor(0, 0, 0);
                $curY = $pdf->GetY();
            }

            $curY += 8; // space before next orderline
        }

        // Print Shortages Table (Ressources manquantes)
        $shortages = array();
        foreach ($global_components as $compId => $data) {
            if ($data['needed'] > $data['stock']) {
                $data['lacking'] = $data['needed'] - $data['stock'];
                $shortages[] = $data;
            }
        }

        if (count($shortages) > 0) {
            $curY += 10;
            if ($curY > $this->page_hauteur - $this->marge_basse - 40) {
                $pdf->AddPage();
                $this->_pagehead($pdf, $object, 0, $outputlangs);
                $curY = 40;
                $pdf->SetY($curY);
            }

            // Perforation line
            $pdf->SetLineStyle(array('dash' => '2,2', 'color' => array(150, 150, 150)));
            $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
            $pdf->SetLineStyle(array('dash' => 0, 'color' => array(0, 0, 0)));
            $curY += 8;

            $pdf->SetFont('', 'B', $default_font_size + 2);
            $pdf->SetTextColor(200, 0, 0); // Red title
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 8, "Ressources manquantes", 0, 'L', 0, 1, $this->marge_gauche, $curY);
            $pdf->SetTextColor(0, 0, 0);
            $curY += 8;

            // Headers
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetDrawColor(120, 120, 120);
            $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, 6, 'F');
            $pdf->MultiCell(25, 6, "Réf", 0, 'L', 0, 0, $this->marge_gauche + 2, $curY + 1);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 85, 6, "Libellé", 0, 'L', 0, 0, $this->marge_gauche + 27, $curY + 1);
            $pdf->MultiCell(20, 6, "Besoin", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 60, $curY + 1);
            $pdf->MultiCell(20, 6, "En Stock", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 40, $curY + 1);
            $pdf->MultiCell(20, 6, "Manquant", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 20, $curY + 1);

            // Borders
            $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
            $pdf->Line($this->marge_gauche, $curY + 6, $this->page_largeur - $this->marge_droite, $curY + 6);
            $pdf->Line($this->marge_gauche, $curY, $this->marge_gauche, $curY + 6);
            $pdf->Line($this->marge_gauche + 25, $curY, $this->marge_gauche + 25, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 60, $curY, $this->page_largeur - $this->marge_droite - 60, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 40, $curY, $this->page_largeur - $this->marge_droite - 40, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite - 20, $curY, $this->page_largeur - $this->marge_droite - 20, $curY + 6);
            $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + 6);

            $curY += 6;
            $pdf->SetY($curY);
            $pdf->SetFont('', '', $default_font_size - 1);

            $fill = false;
            foreach ($shortages as $shortage) {
                if ($curY > $this->page_hauteur - $this->marge_basse - 10) {
                    $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
                    $pdf->AddPage();
                    $this->_pagehead($pdf, $object, 0, $outputlangs);
                    $curY = 40;
                    $pdf->SetY($curY);
                    $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
                }

                $h1 = $pdf->getStringHeight(25, " " . $shortage['ref']);
                $h2 = $pdf->getStringHeight($this->page_largeur - $this->marge_gauche - $this->marge_droite - 85, " " . $shortage['label']);
                $h = max($h1, $h2);
                if ($h < 6)
                    $h = 6;

                if ($fill) {
                    $pdf->SetFillColor(250, 250, 250);
                    $pdf->Rect($this->marge_gauche, $curY, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $h, 'F');
                }

                $pdf->MultiCell(25, $h, " " . $shortage['ref'], 0, 'L', 0, 0, $this->marge_gauche, $curY + ($h - $h1) / 2);
                $pdf->MultiCell($this->page_largeur - $this->marge_gauche - $this->marge_droite - 85, $h, " " . $shortage['label'], 0, 'L', 0, 0, $this->marge_gauche + 25, $curY + ($h - $h2) / 2);

                $pdf->MultiCell(20, $h, $shortage['needed'] . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 60, $curY + ($h - 6) / 2);
                $pdf->MultiCell(20, $h, $shortage['stock'] . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 40, $curY + ($h - 6) / 2);

                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 1);
                $pdf->MultiCell(20, $h, $shortage['lacking'] . " ", 0, 'R', 0, 0, $this->page_largeur - $this->marge_droite - 20, $curY + ($h - 6) / 2);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('', '', $default_font_size - 1);

                $pdf->Line($this->marge_gauche, $curY, $this->marge_gauche, $curY + $h);
                $pdf->Line($this->marge_gauche + 25, $curY, $this->marge_gauche + 25, $curY + $h);
                $pdf->Line($this->page_largeur - $this->marge_droite - 60, $curY, $this->page_largeur - $this->marge_droite - 60, $curY + $h);
                $pdf->Line($this->page_largeur - $this->marge_droite - 40, $curY, $this->page_largeur - $this->marge_droite - 40, $curY + $h);
                $pdf->Line($this->page_largeur - $this->marge_droite - 20, $curY, $this->page_largeur - $this->marge_droite - 20, $curY + $h);
                $pdf->Line($this->page_largeur - $this->marge_droite, $curY, $this->page_largeur - $this->marge_droite, $curY + $h);

                $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);

                $curY += $h;
                $pdf->SetY($curY);
                $fill = !$fill;
            }
            $pdf->Line($this->marge_gauche, $curY, $this->page_largeur - $this->marge_droite, $curY);
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

        // Title: CALCUL DE BESOIN
        $pdf->SetTextColor(0, 50, 100); // Dark Blue
        $pdf->SetFont('', 'B', $default_font_size + 6);
        $pdf->SetXY($this->marge_gauche, $this->marge_haute);
        $pdf->MultiCell(100, 10, "CALCUL DE BESOIN", 0, 'L');

        // Commande Ref inside a light gray box
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', 'B', $default_font_size + 2);
        $pdf->SetXY($this->page_largeur - $this->marge_droite - 70, $this->marge_haute);
        $pdf->MultiCell(70, 8, "COMMANDE : " . $object->ref, 1, 'C', 1);

        // Date
        $date = dol_print_date(dol_now(), 'daytext');
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
