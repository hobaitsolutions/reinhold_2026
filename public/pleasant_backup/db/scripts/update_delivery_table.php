<?php
require_once(__DIR__.'/../dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();
$DB->updateDeliveryTable();
