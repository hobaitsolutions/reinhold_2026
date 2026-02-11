<?php
/**
 * Get all available web-products and update them at once
 */

require_once('dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();
$DB->updatePriceRules('2000-01-01');

//$customers = $DB->getCustomers([31897, 20628]);
//foreach ($customers as $customer)
//{
//	$DB->setPriceRulesForCustomer($customer->customerNumber);
//}
//
