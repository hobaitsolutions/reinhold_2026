<?php
require_once(__DIR__.'/../../mysql/dbHandler.php');

$DB = new MySQLDBInterface();
$DB->updateHistoricOrders();
