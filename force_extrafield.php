<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

$extrafields = new ExtraFields($db);

$res = $extrafields->addExtraField(
    'entrepot_selection', 
    'Entrepôt', 
    'sellist', 
    100,            // pos
    '255',          // size
    'bom_bomline', 
    0,              // unique
    0,              // required
    '',             // default_value
    array('options' => array('entrepot:ref:rowid' => '')), // param
    1,              // alwayseditable
    '',             // perms
    '1',            // list
    '',             // help
    '',             // computed
    '',             // entity
    '',             // langfile
    '1',            // enabled
    0,              // totalizable
    1               // printable
);

if ($res > 0) {
    echo "Extrafield created successfully!\n";
} else {
    echo "Error creating extrafield: " . $extrafields->error . "\n";
    // If it failed because column exists, let's force the label insertion
    if ($extrafields->errno == 'DB_ERROR_COLUMN_ALREADY_EXISTS' || $res == -2) {
        echo "Column already exists, forcing label creation...\n";
        $res2 = $extrafields->create_label(
            'entrepot_selection', 'Entrepôt', 'sellist', 100, '255', 'bom_bomline', 0, 0, 
            array('options' => array('entrepot:ref:rowid' => '')), 1, '', '1', '', '', '', '', '', '1', 0, 1
        );
        if ($res2 > 0) {
            echo "Label created successfully!\n";
        } else {
            echo "Failed to create label: " . $extrafields->error . "\n";
        }
    }
}

// FORCE UPDATE THE PARAM
$serialized_param = serialize(array('options' => array('entrepot:ref:rowid' => '')));
$sql = "UPDATE " . MAIN_DB_PREFIX . "extrafields SET param = '" . $db->escape($serialized_param) . "' WHERE name = 'entrepot_selection' AND elementtype = 'bom_bomline'";
$resql = $db->query($sql);
if ($resql) {
    echo "Param forcibly updated in database!\n";
} else {
    echo "Error updating param: " . $db->lasterror() . "\n";
}
