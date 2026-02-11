<?php
require_once('dbHandler.php');
$db       = new \MySQLDBInterface();
$orders   = $db->getOutdatedProducts('2022-01-30 19:59:00');
var_dump($orders);

