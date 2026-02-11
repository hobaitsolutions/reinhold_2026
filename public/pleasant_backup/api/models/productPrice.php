<?php

namespace hobaIT;
const CURRENCYIDDEFAULT = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
const TAXDEFAULT        = 1.19;
const CURRENCYSYMBOL    = '€';

class productPrice
{
	public float $gross = 0.01;
	public float $net = 0.01;
	public string $currencyId = CURRENCYIDDEFAULT;
	public bool $linked = true;

	public function __construct(float $net)
	{
		$this->net   = self::round($net);
		$this->gross = self::round($net * TAXDEFAULT);
	}

	/**
	 * Round price to 2 decimals
	 *
	 * @param float $price
	 *
	 * @return float
	 */
	public function round(float $price): float
	{
		return round($price, 2);
	}

	/**
	 * Get string
	 * @return string
	 */
	public function __toString(): string
	{
		return (string) $this->net . ' ' . CURRENCYSYMBOL;
	}
}
