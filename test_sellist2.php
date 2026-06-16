<?php
require_once '../../main.inc.php';

$sql = "SELECT name, elementtype, param FROM " . MAIN_DB_PREFIX . "extrafields WHERE type = 'sellist'";
$resql = $db->query($sql);

echo "<pre>";
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        echo "Field: " . $obj->name . " | Element: " . $obj->elementtype . " | Param: " . $obj->param . "\n";
    }
} else {
    echo "Error: " . $db->lasterror();
}
echo "</pre>";

// Also let's check what the SQL error is when we query entrepot:ref:rowid
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('bom_bomline');
$extrafields->attributes['bom_bomline']['param']['entrepot_selection'] = array('options' => array('entrepot:ref:rowid' => ''));

echo "Testing query...\n";
$out = $extrafields->showInputField('entrepot_selection', '');
echo htmlspecialchars($out);
echo "\nLast DB error: " . $db->lasterror();
