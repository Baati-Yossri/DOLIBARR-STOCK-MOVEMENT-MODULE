<?php
define('NOLOGIN', '1');
require '../../main.inc.php';

$res = $db->query("SHOW COLUMNS FROM llx_entrepot");
while($obj = $db->fetch_object($res)) {
    echo $obj->Field . "\n";
}
