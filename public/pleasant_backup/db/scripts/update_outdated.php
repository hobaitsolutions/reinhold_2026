<?php
/**
 * Update all outdated products
 */

require_once(__DIR__.'/../dbHandler.php');
require_once(__DIR__.'/../../mysql/dbHandler.php');

$start = (new DateTime('now -1 month'))->format('Y-m-d H:i:s');
$products = (new MySQLDBInterface())->getOutdatedProducts($start);
$all= [];
foreach ($products as $product)
{
	$all[] = $product['product_number'];
}
echo "Produkte übrig: " .count($all) ."\n";

$db = new DBInterface();
$db->updateProducts($all);

