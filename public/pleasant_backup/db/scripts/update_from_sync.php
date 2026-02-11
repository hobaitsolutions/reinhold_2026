<?php
/**
 * Update all products gotten from _sync table and update them
 */

require_once(__DIR__.'/../dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();
$DB->syncProducts();

