<?php
require_once('dbHandler.php');

	$DB = new DBInterface();
	$DB->getWebGroups();
