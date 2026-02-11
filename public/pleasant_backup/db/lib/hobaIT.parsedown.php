<?php
namespace hobaIT;

require_once (__DIR__.'/../vendor/erusev/parsedown/Parsedown.php');

class Parsedown extends \Parsedown{
	protected function blockQuote($Line)
	{
		if (preg_match('/^>>[ ]?(.*)|^<<<[ ]?(.*)>>>/', $Line['text'], $matches))
		{
			$Block = array(
				'element' => array(
					'name' => 'aside',
					'handler' => 'lines',
					'text' => (array) $matches[1],
				),
			);

			return $Block;
		}
	}

}
