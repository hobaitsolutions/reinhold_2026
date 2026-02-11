<?php

require_once(__DIR__ . '/../client.php');
$client = new \hobaIT\APIClient();
echo $client->getCategories();
