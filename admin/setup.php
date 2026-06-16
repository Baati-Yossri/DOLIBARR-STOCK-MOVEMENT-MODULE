<?php
/**
 * \file admin/setup.php
 * \ingroup calcul_stock
 * \brief Setup page for calcul_stock module
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/entrepot.class.php';

$langs->loadLangs(array("admin", "calcul_stock@calcul_stock"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'update') {
    $error = 0;
    
    $warehouse_id = GETPOST('CALCUL_STOCK_RESERVE_WAREHOUSE_ID', 'int');
    if ($warehouse_id > 0) {
        dolibarr_set_const($db, 'CALCUL_STOCK_RESERVE_WAREHOUSE_ID', $warehouse_id, 'chaine', 0, '', $conf->entity);
    } else {
        dolibarr_del_const($db, 'CALCUL_STOCK_RESERVE_WAREHOUSE_ID', $conf->entity);
    }

    if (!$error) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

/*
 * View
 */

$page_name = "Calcul Stock Setup";
llxHeader('', $page_name);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');

print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . currentToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Paramètre</td>';
print '<td>Valeur</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Entrepôt de réservation (Reserved Stock Warehouse)</td>';
print '<td>';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
$form = new Form($db);

$warehouses = array();
$sql = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."entrepot WHERE entity IN (".getEntity('stock').") AND statut = 1";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $warehouses[$obj->rowid] = $obj->ref;
    }
}

$warehouse_val = !empty($conf->global->CALCUL_STOCK_RESERVE_WAREHOUSE_ID) ? $conf->global->CALCUL_STOCK_RESERVE_WAREHOUSE_ID : 0;
print $form->selectarray('CALCUL_STOCK_RESERVE_WAREHOUSE_ID', $warehouses, $warehouse_val, 1, 0, 0, '', 0, 0, 0, '', 'minwidth200', 1);

print '</td>';
print '</tr>';

print '</table>';

print '<div class="center"><br><input type="submit" class="button" value="' . $langs->trans("Save") . '"></div>';

print '</form>';

llxFooter();
$db->close();
