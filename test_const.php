<?php
define('NOLOGIN', '1');
require '../../main.inc.php';

$res = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE '%CALCUL_STOCK_TABS%'");
if ($res) {
    while ($obj = $db->fetch_object($res)) {
        var_dump($obj);
    }
} else {
    echo "Query failed";
}
