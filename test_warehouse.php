<?php
require_once '../../main.inc.php';

$sql = "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "entrepot";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    echo "Found $num warehouses.\n";
    while ($obj = $db->fetch_object($resql)) {
        echo "Warehouse: " . $obj->rowid . " - " . $obj->ref . "\n";
    }
} else {
    echo "Error: " . $db->lasterror();
}
