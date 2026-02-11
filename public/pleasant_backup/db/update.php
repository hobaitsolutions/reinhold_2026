<?php

/**
 * Update a single product or multiple products split by commas
 * sample call: update.php?id=213124 or update.php?id=1231243,31414,123-abc
 */

require_once('dbHandler.php');

$client = new \hobaIT\pleasantClient();

$DB = new DBInterface();

$ids = explode(',', file_get_contents('update.txt'));
if (!is_array($ids))
{
	$ids = [(string) $ids];
}
$DB->updateProducts($ids);
