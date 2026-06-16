<?php
require_once '../../main.inc.php';

$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "extrafields WHERE name = 'entrepot_selection' AND elementtype = 'bom_bomline'";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Found $num extrafields in llx_extrafields.\n";
    if ($num > 0) {
        $obj = $db->fetch_object($resql);
        echo "Param: " . $obj->param . "\n";
    }
} else {
    echo "Error: " . $db->lasterror();
}

$sql2 = "SHOW COLUMNS FROM " . MAIN_DB_PREFIX . "bom_bomline_extrafields LIKE 'entrepot_selection'";
$resql2 = $db->query($sql2);
if ($resql2) {
    $num2 = $db->num_rows($resql2);
    echo "Found $num2 columns in llx_bom_bomline_extrafields.\n";
} else {
    echo "Error2: " . $db->lasterror();
}
