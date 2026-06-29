<?php
/**
 *	\file       commande_stock.php
 *	\ingroup    calcul_stock
 *	\brief      Tab for stock movements on commande
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/factory/class/factory.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/calcul_stock/class/calculstockreservation.class.php';

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

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

$reserve_warehouse_id = !empty($conf->global->CALCUL_STOCK_RESERVE_WAREHOUSE_ID) ? $conf->global->CALCUL_STOCK_RESERVE_WAREHOUSE_ID : 0;

/*
 * Actions
 */
if ($action == 'reserve' && $reserve_warehouse_id > 0) {
    $fk_commandeline = GETPOST('fk_commandeline', 'int');
    $fk_product = GETPOST('fk_product', 'int');
    $qty_raw = isset($_POST['qty']) ? $_POST['qty'] : (isset($_GET['qty']) ? $_GET['qty'] : 0);
    $qty_to_reserve = (float) str_replace(',', '.', $qty_raw);
    
    $fk_raw = isset($_POST['fk_entrepot_source']) ? $_POST['fk_entrepot_source'] : (isset($_GET['fk_entrepot_source']) ? $_GET['fk_entrepot_source'] : 0);
    $fk_entrepot_source = (int) $fk_raw;

    if ($qty_to_reserve > 0 && $fk_entrepot_source > 0) {
        // Verify stock doesn't exceed available
        $product_static = new Product($db);
        $product_static->fetch($fk_product);
        $product_static->load_stock();
        $stock_available = 0;
        if (isset($product_static->stock_warehouse[$fk_entrepot_source])) {
            $stock_available = $product_static->stock_warehouse[$fk_entrepot_source]->real;
        }

        if ($qty_to_reserve > $stock_available) {
            setEventMessages("Action annulée : La quantité demandée (" . $qty_to_reserve . ") dépasse le stock actuellement disponible (" . $stock_available . ") dans l'entrepôt source.", null, 'errors');
        } else {
            $mouv = new MouvementStock($db);
            $res1 = $mouv->livraison($user, $fk_product, $fk_entrepot_source, $qty_to_reserve, 0, 'Reservation commande ' . $object->ref);
            $res2 = $mouv->reception($user, $fk_product, $reserve_warehouse_id, $qty_to_reserve, 0, 'Reservation commande ' . $object->ref);

            if ($res1 > 0 && $res2 > 0) {
                $reservation = new CalculStockReservation($db);
                $res_save = 0;
                if ($reservation->fetchByLineAndProduct($fk_commandeline, $fk_product) > 0) {
                    $reservation->qty += $qty_to_reserve;
                    $res_save = $reservation->update($user);
                } else {
                    $reservation->fk_commande = $object->id;
                    $reservation->fk_commandeline = $fk_commandeline;
                    $reservation->fk_product = $fk_product;
                    $reservation->fk_entrepot_source = $fk_entrepot_source;
                    $reservation->qty = $qty_to_reserve;
                    $reservation->status = 0;
                    $res_save = $reservation->create($user);
                }
                
                if ($res_save > 0) {
                    setEventMessages($langs->trans("StockReservedSuccessfully"), null, 'mesgs');
                } else {
                    setEventMessages("Erreur d'enregistrement de la réservation: " . implode(', ', $reservation->errors), null, 'errors');
                }
            } else {
                $err_msg = $mouv->error;
                if (empty($err_msg) && count($mouv->errors) > 0) {
                    $err_msg = implode(', ', $mouv->errors);
                }
                setEventMessages("Erreur de mouvement de stock : " . $err_msg, null, 'errors');
            }
        }
    } else {
        setEventMessages("Quantité ou entrepôt source invalide.", null, 'errors');
    }
} elseif ($action == 'cancel' && $reserve_warehouse_id > 0) {
    $reservation_id = GETPOST('reservation_id', 'int');
    $reservation = new CalculStockReservation($db);
    if ($reservation->fetch($reservation_id) > 0) {
        $mouv = new MouvementStock($db);
        $res1 = $mouv->livraison($user, $reservation->fk_product, $reserve_warehouse_id, $reservation->qty, 0, 'Annulation reservation ' . $object->ref);
        $res2 = $mouv->reception($user, $reservation->fk_product, $reservation->fk_entrepot_source, $reservation->qty, 0, 'Annulation reservation ' . $object->ref);

        if ($res1 > 0 && $res2 > 0) {
            $reservation->delete($user);
            setEventMessages($langs->trans("StockReservationCanceled"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("ErrorCancelReservation"), null, 'errors');
        }
    }
} elseif ($action == 'finalize') {
    $reservation_id = GETPOST('reservation_id', 'int');
    $reservation = new CalculStockReservation($db);
    if ($reservation->fetch($reservation_id) > 0) {
        if ($reserve_warehouse_id > 0) {
            $mouv = new MouvementStock($db);
            $mouv->livraison($user, $reservation->fk_product, $reserve_warehouse_id, $reservation->qty, 0, 'Consommation finale ' . $object->ref);
        }
        $reservation->status = 1;
        $reservation->update($user);
        setEventMessages($langs->trans("ReservationFinalized"), null, 'mesgs');
    }
} elseif ($action == 'reserve_all' && $reserve_warehouse_id > 0) {
    $factory = new Factory($db);
    $product_static = new Product($db);
    $reservation_static = new CalculStockReservation($db);
    $mouv = new MouvementStock($db);
    $success_count = 0;

    foreach ($object->lines as $line) {
        if (!empty($line->fk_product)) {
            $components = $factory->getChildsArbo($line->fk_product);
            if (!empty($components) && is_array($components)) {
                foreach ($components as $compId => $compData) {
                    $fk_entrepot = !empty($compData['fk_entrepot']) ? $compData['fk_entrepot'] : 0;
                    if ($fk_entrepot > 0) {
                        $product_static->fetch($compId);
                        $product_static->load_stock();
                        $stock_qty = 0;
                        if (isset($product_static->stock_warehouse[$fk_entrepot])) {
                            $stock_qty = $product_static->stock_warehouse[$fk_entrepot]->real;
                        }

                        $needed_qty = $compData[1] * $line->qty;
                        $qty_reserved = 0;
                        $status = 0;
                        if ($reservation_static->fetchByLineAndProduct($line->id, $compId) > 0) {
                            $qty_reserved = $reservation_static->qty;
                            $status = $reservation_static->status;
                        }

                        $a_reserver = $needed_qty - $qty_reserved;
                        if ($a_reserver > 0 && $stock_qty > 0 && $status == 0) {
                            $qty_to_reserve = min($a_reserver, $stock_qty);

                            $res1 = $mouv->livraison($user, $compId, $fk_entrepot, $qty_to_reserve, 0, 'Reservation commande ' . $object->ref);
                            $res2 = $mouv->reception($user, $compId, $reserve_warehouse_id, $qty_to_reserve, 0, 'Reservation commande ' . $object->ref);

                            if ($res1 > 0 && $res2 > 0) {
                                $reservation = new CalculStockReservation($db);
                                if ($reservation->fetchByLineAndProduct($line->id, $compId) > 0) {
                                    $reservation->qty += $qty_to_reserve;
                                    $reservation->update($user);
                                } else {
                                    $reservation->fk_commande = $object->id;
                                    $reservation->fk_commandeline = $line->id;
                                    $reservation->fk_product = $compId;
                                    $reservation->fk_entrepot_source = $fk_entrepot;
                                    $reservation->qty = $qty_to_reserve;
                                    $reservation->status = 0;
                                    $reservation->create($user);
                                }
                                $success_count++;
                            }
                        }
                    }
                }
            }
        }
    }

    if ($success_count > 0) {
        setEventMessages($langs->trans("StockReservedSuccessfully") . " (" . $success_count . " composants)", null, 'mesgs');
    } else {
        setEventMessages("Aucun stock supplémentaire disponible pour la réservation.", null, 'warnings');
    }
} elseif ($action == 'finalize_all') {
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation";
    $sql .= " WHERE fk_commande = " . $object->id . " AND status = 0";
    $resql = $db->query($sql);

    if ($resql) {
        $success_count = 0;
        $mouv = new MouvementStock($db);
        while ($obj = $db->fetch_object($resql)) {
            $reservation = new CalculStockReservation($db);
            if ($reservation->fetch($obj->rowid) > 0) {
                if ($reserve_warehouse_id > 0) {
                    $mouv->livraison($user, $reservation->fk_product, $reserve_warehouse_id, $reservation->qty, 0, 'Consommation finale ' . $object->ref);
                }
                $reservation->status = 1;
                $reservation->update($user);
                $success_count++;
            }
        }
        if ($success_count > 0) {
            setEventMessages("Toutes les réservations ont été finalisées (" . $success_count . " lignes).", null, 'mesgs');
        } else {
            setEventMessages("Aucune réservation active à finaliser.", null, 'warnings');
        }
    }
} elseif ($action == 'cancel_all' && $reserve_warehouse_id > 0) {
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation";
    $sql .= " WHERE fk_commande = " . $object->id . " AND status = 0";
    $resql = $db->query($sql);

    if ($resql) {
        $success_count = 0;
        $mouv = new MouvementStock($db);
        while ($obj = $db->fetch_object($resql)) {
            $reservation = new CalculStockReservation($db);
            if ($reservation->fetch($obj->rowid) > 0) {
                $res1 = $mouv->livraison($user, $reservation->fk_product, $reserve_warehouse_id, $reservation->qty, 0, 'Annulation reservation ' . $object->ref);
                $res2 = $mouv->reception($user, $reservation->fk_product, $reservation->fk_entrepot_source, $reservation->qty, 0, 'Annulation reservation ' . $object->ref);

                if ($res1 > 0 && $res2 > 0) {
                    $reservation->delete($user);
                    $success_count++;
                }
            }
        }
        if ($success_count > 0) {
            setEventMessages("Toutes les réservations ont été annulées et remises en stock (" . $success_count . " lignes).", null, 'mesgs');
        } else {
            setEventMessages("Aucune réservation active à annuler.", null, 'warnings');
        }
    }
}

/*
 * View
 */

$title = "Mouvements de stock";
llxHeader('', $title, 'Commande');

if ($object->id > 0) {
    $head = commande_prepare_head($object);
    dol_fiche_head($head, 'calcul_stock_tab', $langs->trans("CustomerOrder"), -1, 'order');

    $linkback = '<a href="' . DOL_URL_ROOT . '/commande/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';
    $morehtmlref = '<div class="refidno">';
    $morehtmlref .= $object->ref;
    $morehtmlref .= '</div>';

    dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    if (empty($reserve_warehouse_id)) {
        print '<div class="warning">ATTENTION: L\'entrepôt de réservation n\'est pas défini. Allez dans les paramètres du module Calcul Stock pour le sélectionner.</div>';
    }

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>Ligne Commande</td>';
    print '<td>Composant</td>';
    print '<td class="right">Besoin</td>';
    print '<td class="right">Réservé</td>';
    print '<td class="right">A Réserver</td>';
    print '<td class="right">Stock Dispo</td>';
    print '<td>Entrepôt Source</td>';
    print '<td class="right" style="min-width: 200px;">Action</td>';
    print '</tr>';

    $factory = new Factory($db);
    $product_static = new Product($db);
    $entrepot_static = new Entrepot($db);
    $reservation_static = new CalculStockReservation($db);

    foreach ($object->lines as $line) {
        if (!empty($line->fk_product)) {
            $parent_product = new Product($db);
            $parent_product->fetch($line->fk_product);
            $parent_ref_link = $parent_product->getNomUrl(1);

            $components = $factory->getChildsArbo($line->fk_product);

            if (!empty($components) && is_array($components)) {
                $comp_count = count($components);
                $is_first_comp = true;
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
                    $comp_ref_link = $product_static->getNomUrl(1);
                    $comp_label = !empty($product_static->label) ? $product_static->label : $compData[3];
                    $needed_qty = $comp_qty * $line->qty;

                    // Reservation state
                    $qty_reserved = 0;
                    $status = 0;
                    $reservation_id = 0;
                    if ($reservation_static->fetchByLineAndProduct($line->id, $compId) > 0) {
                        $qty_reserved = $reservation_static->qty;
                        $status = $reservation_static->status;
                        $reservation_id = $reservation_static->id;
                    }

                    $a_reserver = $needed_qty - $qty_reserved;
                    if ($a_reserver < 0)
                        $a_reserver = 0;

                    $border_style = ($compId === array_key_last($components)) ? 'border-bottom: 2px solid #ddd;' : 'border-bottom: 1px solid #eee;';

                    $tr_style = '';
                    if ($status != 1 && $stock_qty < $a_reserver) {
                        $tr_style = ' style="background-color: #fff0f0;"';
                    }

                    print '<tr class="oddeven"' . $tr_style . '>';
                    if ($is_first_comp) {
                        print '<td rowspan="' . $comp_count . '" style="vertical-align: top; background-color: #f8f8f8; border-bottom: 2px solid #ddd;">' . $parent_ref_link . '<br>' . $line->product_label . '<br><span class="opacitymedium">Qté Commande: ' . $line->qty . '</span></td>';
                        $is_first_comp = false;
                    }
                    print '<td style="' . $border_style . '">' . $comp_ref_link . ' - ' . $comp_label . '</td>';
                    print '<td class="right" style="' . $border_style . '">' . round($needed_qty, 5) . '</td>';
                    print '<td class="right" style="color: #5cb85c; ' . $border_style . '"><b>' . round($qty_reserved, 5) . '</b></td>';

                    if ($status == 1) {
                        print '<td class="right" style="' . $border_style . '"><span class="badge" style="background-color: #5cb85c; color: white; padding: 3px 6px; border-radius: 3px;">Consommé</span></td>';
                        print '<td class="right opacitymedium" style="' . $border_style . '">-</td>';
                        print '<td style="' . $border_style . '">' . $entrepot_name . '</td>';
                        print '<td class="right opacitymedium" style="' . $border_style . '">Terminé</td>';
                    } else {
                        $color = $a_reserver > 0 ? '#d9534f' : '#5cb85c';
                        print '<td class="right" style="color: ' . $color . '; ' . $border_style . '"><b>' . round($a_reserver, 5) . '</b></td>';
                        
                        if ($stock_qty < $a_reserver) {
                            print '<td class="right" style="color: #d9534f; font-weight: bold; ' . $border_style . '">' . round($stock_qty, 5) . ' <span class="fa fa-exclamation-triangle" title="Stock insuffisant"></span></td>';
                        } else {
                            print '<td class="right" style="' . $border_style . '">' . round($stock_qty, 5) . '</td>';
                        }
                        print '<td style="' . $border_style . '">' . $entrepot_name . '</td>';

                        print '<td class="right" style="' . $border_style . '">';
                        if (empty($reserve_warehouse_id)) {
                            print '<span class="error" title="Entrepôt non configuré">Non configuré</span>';
                        } else if ($fk_entrepot == 0) {
                            print '<span class="error" title="Ce composant n\'a pas d\'entrepôt défini dans la nomenclature.">Entrepôt manquant</span>';
                        } else {
                            if ($a_reserver > 0 && $stock_qty > 0) {
                                $max_reserve = min($a_reserver, $stock_qty);
                                print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '" style="display:inline-block; margin-bottom: 2px;">';
                                print '<input type="hidden" name="token" value="' . currentToken() . '">';
                                print '<input type="hidden" name="action" value="reserve">';
                                print '<input type="hidden" name="fk_commandeline" value="' . $line->id . '">';
                                print '<input type="hidden" name="fk_product" value="' . $compId . '">';
                                print '<input type="hidden" name="fk_entrepot_source" value="' . $fk_entrepot . '">';
                                print '<input type="number" name="qty" value="' . round($max_reserve, 5) . '" max="' . round($max_reserve, 5) . '" min="0.00001" step="any" style="width: 70px; margin-right: 5px; padding: 4px;">';
                                print '<input type="submit" class="button" value="Réserver" style="padding: 4px 8px;">';
                                print '</form><br>';
                            }

                            if ($qty_reserved > 0) {
                                print '<a class="button" style="background-color: #d9534f; color: white; border-color: #d43f3a; padding: 4px 8px; text-decoration: none;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=cancel&reservation_id=' . $reservation_id . '&token=' . currentToken() . '">Annuler</a> ';
                                print '<a class="button" style="background-color: #5cb85c; color: white; border-color: #4cae4c; padding: 4px 8px; text-decoration: none;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=finalize&reservation_id=' . $reservation_id . '&token=' . currentToken() . '">Finaliser</a>';
                            }
                            
                            if ($stock_qty < $a_reserver) {
                                print '<div style="margin-top: 8px;">';
                                print '<a class="button" style="padding: 4px 6px; font-size: 0.85em; background-color: #f0ad4e; color: white; border-color: #eea236; text-decoration: none;" href="'.DOL_URL_ROOT.'/fourn/commande/card.php?action=create" target="_blank" title="Créer Commande d\'Achat"><span class="fa fa-shopping-cart"></span> Achat</a> ';
                                print '<a class="button" style="padding: 4px 6px; font-size: 0.85em; background-color: #5bc0de; color: white; border-color: #46b8da; text-decoration: none;" href="'.DOL_URL_ROOT.'/commande/card.php?id='.$object->id.'&action=presend&mode=init" title="Envoyer Email Client"><span class="fa fa-envelope"></span> Email</a>';
                                print '</div>';
                            }
                        }
                        print '</td>';
                    }
                    print '</tr>';
                }
            } else {
                $parent_ref_link = $parent_product->getNomUrl(1);
                print '<tr class="oddeven">';
                print '<td>' . $parent_ref_link . ' - ' . $line->product_label . ' <span class="opacitymedium">(Qté: ' . $line->qty . ')</span></td>';
                print '<td colspan="7" class="opacitymedium">Aucun composant ou nomenclature associée</td>';
                print '</tr>';
            }
        }
    }

    print '</table>';

    if ($reserve_warehouse_id > 0) {
        $has_active_reservations = false;
        $sql = "SELECT COUNT(rowid) as nb FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation WHERE fk_commande = " . $object->id . " AND status = 0";
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj && $obj->nb > 0) {
                $has_active_reservations = true;
            }
        }

        print '<br><div class="center">';
        print '<a class="button" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=reserve_all&token=' . currentToken() . '">Réserver tout le disponible</a>';
        if ($has_active_reservations) {
            print '&nbsp; &nbsp;';
            print '<a class="button" style="background-color: #5cb85c; color: white; border-color: #4cae4c; text-decoration: none;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=finalize_all&token=' . currentToken() . '">Finaliser tout</a>';
            print '&nbsp; &nbsp;';
            print '<a class="button" style="background-color: #d9534f; color: white; border-color: #d43f3a; text-decoration: none;" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=cancel_all&token=' . currentToken() . '">Annuler tout</a>';
        }
        print '</div>';
    }

    print '</div>';
    dol_fiche_end();
} else {
    print $langs->trans("ErrorRecordNotFound");
}

llxFooter();
$db->close();
