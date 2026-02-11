<?php
require_once('dbHandler.php');

$DB = new DBInterface();
$DB->getArticles(0);
