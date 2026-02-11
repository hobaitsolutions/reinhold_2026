<?php
require_once('dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();
//$DB->syncUpdateProducts();
//$DB->setPriceRulesForCustomer('46065');
//var_dump($client->getCustomerGroupName('cfbd5018d38d41d8adca10d94fc8bdd6'));

//var_dump($DB->getAdvancedPricesPerProduct('46065'));
//var_dump($client->deleteAllExtendedPrices($client->getProductIdByProductNumber('10028')));
//$DB->syncProducts();
$DB->findDeletedProducts();
