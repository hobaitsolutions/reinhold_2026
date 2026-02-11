<?php

/**
 * Update products that do not have an image yet
 */

require_once('dbHandler.php');
$DB = new DBInterface();
$DB->updateProductsWithoutImages();
