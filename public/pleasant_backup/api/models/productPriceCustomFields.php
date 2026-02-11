<?php

namespace hobaIT;

class productPriceCustomFields
{
	public ?string $validUntil;

	public function getValidUntil(): ?string
	{
		return $this->validUntil;
	}

	public function setValidUntil(?string $validUntil): void
	{
		$this->validUntil = $validUntil;
	}
}
