<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('bom_bomline');

echo "<pre>";
print_r($extrafields->attributes['bom_bomline']);
echo "</pre>";

// Let's test the output field generation natively
echo "<h3>Testing showInputField for entrepot_selection</h3>";
echo $extrafields->showInputField('entrepot_selection', '');

// Let's also test another param directly
$extrafields->attributes['bom_bomline']['param']['entrepot_selection'] = array('options' => array('entrepot:ref:rowid' => ''));
echo "<h3>Testing with param entrepot:ref:rowid</h3>";
echo $extrafields->showInputField('entrepot_selection', '');

$extrafields->attributes['bom_bomline']['param']['entrepot_selection'] = array('options' => array('entrepot:ref:rowid::' => ''));
echo "<h3>Testing with param entrepot:ref:rowid::</h3>";
echo $extrafields->showInputField('entrepot_selection', '');

$extrafields->attributes['bom_bomline']['param']['entrepot_selection'] = array('options' => array('llx_entrepot:ref:rowid' => ''));
echo "<h3>Testing with param llx_entrepot:ref:rowid</h3>";
echo $extrafields->showInputField('entrepot_selection', '');

$extrafields->attributes['bom_bomline']['param']['entrepot_selection'] = array('options' => array('entrepot:ref:rowid::statut=1' => ''));
echo "<h3>Testing with param entrepot:ref:rowid::statut=1</h3>";
echo $extrafields->showInputField('entrepot_selection', '');

