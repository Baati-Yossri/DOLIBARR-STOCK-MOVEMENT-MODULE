<?php
define('INC_FROM_CRON_SCRIPT', true);
require '../../main.inc.php';
$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "calcul_stock_reservation LIMIT 1";
$res = $db->query($sql);
if (!$res) {
    echo "DB ERROR: " . $db->lasterror();
} else {
    echo "Table exists!";
}
