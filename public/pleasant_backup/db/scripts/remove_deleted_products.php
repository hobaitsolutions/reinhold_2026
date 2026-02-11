<?php

/**
 * Removes all deleted products which are found in shop, but not in pleasant.
 * Reason for this: Occasionally it seems, pleasant does not log the deletion of some products.
 */

require_once(__DIR__.'/../dbHandler.php');
$DB = new DBInterface();
$DB->removeDeletedProductsFromShop();
