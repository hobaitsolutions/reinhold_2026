<?php
use Spatie\Watcher\Watch;
//$client->getMediaIdByFileName('https://reinhold.hobaitsolutions.com/artikelbilder/Artikel/64366.jpg');
//echo $client->getCountryIdByISO('D');

//$ruleCondition = new \hobaIT\ruleCondition();
//$ruleCondition->setRuleId('025f7a0773b64f43a9201fa4a70f4501');
//var_dump($client->getAllOrderedProductIdsByCustomer('10003'));


require_once('pleasantClient.php');
require_once '../db/dbHandler.php';

$client = new \hobaIT\pleasantClient();

$dir = '../../artikelbilder/Artikel/*';

$DB = new MySQLDBInterface();
$query = 'INSERT INTO hobait_fs_changes (action_type, path) VALUES ( ? , ? )';

Watch::path($dir)
	->onAnyChange(function (string $type, string $path) use (&$DB, $query) {
		$DB->query($query, [$type, $path]);
		file_put_contents('log.txt', date('d.m.Y H:i:s') .' '. $type . ' --> '. $path . "\n");
	})
	->start();
