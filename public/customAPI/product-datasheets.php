<?php

ini_set('display_errors', true);

if (isset($_REQUEST['id']))
{
	$id = $_REQUEST['id'];

	require_once('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/pleasant/api/pleasantClient.php');
	$client = new \hobaIT\pleasantClient();

	if ($client->isValidUUID($id))
	{
		$filepath = $client->createProductZip($id);
		$filename = basename($filepath);
	}
	@ob_end_clean();
	if (!empty($filename)){
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header("Content-type: application/zip");
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " . filesize($filepath));
		@ob_end_flush();
		@readfile($filepath);
	}
}
else
{
	die('NO PERMISSION');
}


