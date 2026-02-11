<?php
/**
 * Get all available web-products and update them at once
 */

require_once('dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();

$customers = $DB->getCustomers([34090, 20628, 31898]);
foreach ($customers as $customer)
{
	$DB->setPriceRulesForCustomer($customer->customerNumber);
}

