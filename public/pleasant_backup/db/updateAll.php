<?php
/**
 * Get all available web-products and update them at once
 */

require_once('dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();
$DB->updateAllProducts();
