<?php

namespace hobaIT;

class productPriceRule
{

	public string $productId;
	public string $ruleId;
	public int $quantityStart;
	public ?int $quantityEnd;
	public array $price;
	public ?productPriceCustomFields $customFields;

	public function __construct(string $ruleId = '', string $productId = '', float $price = 1.0, $quantityStart = 1, $quantityEnd = null, $validUntil = null)
	{
		$this->setProductId($productId);
		$this->setRuleId($ruleId);
		$this->setPrice(
			[new productPrice($price)]
		);
		$this->quantityStart = $quantityStart;

		if (!empty($quantityEnd) && ($quantityEnd > 0))
		{
			$this->quantityEnd = $quantityEnd;
		}

		if (!empty($validUntil))
		{
			$this->customFields = new productPriceCustomFields();
			$this->customFields->setValidUntil($validUntil);
		}
	}

	/**
	 * @return string
	 */
	public function getProductId(): string
	{
		return $this->productId;
	}

	/**
	 * @param string $productId
	 */
	public function setProductId(string $productId): void
	{
		$this->productId = $productId;
	}

	/**
	 * @return string
	 */
	public function getRuleId(): string
	{
		return $this->ruleId;
	}

	/**
	 * @param string $ruleId
	 */
	public function setRuleId(string $ruleId): void
	{
		$this->ruleId = $ruleId;
	}

	/**
	 * @return int
	 */
	public function getQuantityStart(): int
	{
		return $this->quantityStart;
	}

	/**
	 * @param int $quantityStart
	 */
	public function setQuantityStart(int $quantityStart): void
	{
		$this->quantityStart = $quantityStart;
	}

	/**
	 * @return int|null
	 */
	public function getQuantityEnd(): ?int
	{
		return $this->quantityEnd;
	}

	/**
	 * @param int|null $quantityEnd
	 */
	public function setQuantityEnd(?int $quantityEnd): void
	{
		$this->quantityEnd = $quantityEnd;
	}

	/**
	 * @return price
	 */
	public function getPrice(): price
	{
		return $this->price;
	}

	/**
	 * @param array $price
	 */
	public function setPrice(array $price): void
	{
		$this->price = $price;
	}
}
