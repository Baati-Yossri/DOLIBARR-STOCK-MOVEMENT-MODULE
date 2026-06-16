<?php
/**
 *	\file       commande_stock.php
 *	\ingroup    calcul_stock
 *	\brief      Tab for stock movements on commande
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/factory/class/factory.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');

$object = new Commande($db);
if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
    $object->fetch_thirdparty();
}

// Security check
if ($user->socid > 0) {
    $socid = $user->socid;
}
$result = restrictedArea($user, 'commande', $object->id);

/*
 * View
 */

$title = "Mouvements de stock";
llxHeader('', $title, 'Commande');

if ($object->id > 0) {
    $head = commande_prepare_head($object);
    dol_fiche_head($head, 'calcul_stock_tab', $langs->trans("CustomerOrder"), -1, 'order');

    $linkback = '<a href="'.DOL_URL_ROOT.'/commande/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
    $morehtmlref = '<div class="refidno">';
    $morehtmlref .= $object->ref;
    $morehtmlref .= '</div>';
    
    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';
    
    // Add custom content
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>Ligne Commande</td>';
    print '<td>Composant</td>';
    print '<td class="right">Besoin</td>';
    print '<td class="right">Qte en stock</td>';
    print '<td>Entrepôt</td>';
    print '</tr>';

    $factory = new Factory($db);
    $product_static = new Product($db);
    $entrepot_static = new Entrepot($db);

    foreach ($object->lines as $line) {
        if (!empty($line->fk_product)) {
            $components = $factory->getChildsArbo($line->fk_product);
            
            if (!empty($components) && is_array($components)) {
                foreach ($components as $compId => $compData) {
                    $product_static->fetch($compId);
                    $product_static->load_stock();
                    
                    $stock_qty = 0;
                    $fk_entrepot = !empty($compData['fk_entrepot']) ? $compData['fk_entrepot'] : 0;
                    $entrepot_name = '';
                    
                    if ($fk_entrepot > 0) {
                        if (isset($product_static->stock_warehouse[$fk_entrepot])) {
                            $stock_qty = $product_static->stock_warehouse[$fk_entrepot]->real;
                        }
                        if ($entrepot_static->fetch($fk_entrepot) > 0) {
                            $entrepot_name = $entrepot_static->label;
                        }
                    } else {
                        $stock_qty = $product_static->stock_reel;
                        $entrepot_name = '<span class="opacitymedium">Stock Global</span>';
                    }
                    
                    $comp_qty = $compData[1];
                    $comp_ref = $product_static->ref;
                    $comp_label = !empty($product_static->label) ? $product_static->label : $compData[3];
                    $needed_qty = $comp_qty * $line->qty;
                    
                    // Stock visualization class
                    $stock_class = ($stock_qty < $needed_qty) ? 'warning' : 'ok';
                    if ($stock_qty < $needed_qty) {
                        $stock_qty_html = '<span class="error">' . $stock_qty . '</span>';
                    } else {
                        $stock_qty_html = '<span class="ok">' . $stock_qty . '</span>';
                    }
                    
                    print '<tr class="oddeven">';
                    print '<td>' . $line->ref . ' - ' . $line->product_label . ' <span class="opacitymedium">(Qté: ' . $line->qty . ')</span></td>';
                    print '<td>' . $comp_ref . ' - ' . $comp_label . '</td>';
                    print '<td class="right">' . $needed_qty . '</td>';
                    print '<td class="right">' . $stock_qty_html . '</td>';
                    print '<td>' . $entrepot_name . '</td>';
                    print '</tr>';
                }
            } else {
                print '<tr class="oddeven">';
                print '<td>' . $line->ref . ' - ' . $line->product_label . ' <span class="opacitymedium">(Qté: ' . $line->qty . ')</span></td>';
                print '<td colspan="4" class="opacitymedium">Aucun composant ou nomenclature associée</td>';
                print '</tr>';
            }
        }
    }
    
    print '</table>';
    
    print '</div>';
    dol_fiche_end();
} else {
    print $langs->trans("ErrorRecordNotFound");
}

llxFooter();
$db->close();
