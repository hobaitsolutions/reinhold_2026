<?php

namespace hobaIT;

class customergroup
{
	public ?string $id;
	public string $name;
	public bool $displayGross = false;
	public customerGroupCustomFields $customFields;

	public function __construct(?string $name, ?string $number)
	{
		$this->customFields = new customerGroupCustomFields();
		if (!empty($name))
		{
			$this->setName($name);
		}
		if (!empty($number))
		{
			$this->setZurodnungsNr($number);
		}
	}

	/**
	 * @return string|null
	 */
	public function getId(): ?string
	{
		return $this->id;
	}

	/**
	 * @param string|null $id
	 */
	public function setId(?string $id): void
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return bool
	 */
	public function isDisplayGross(): bool
	{
		return $this->displayGross;
	}

	/**
	 * @param bool $displayGross
	 */
	public function setDisplayGross(bool $displayGross): void
	{
		$this->displayGross = $displayGross;
	}

	/**
	 * @param string $number
	 *
	 * @return void
	 */
	public function setZurodnungsNr(string $number)
	{
		$this->customFields->setCustomGroupZuordnungsnr($number);
	}

	/**
	 * @return string
	 */
	public function getZurodnungsNr()
	{
		return $this->customFields->getCustomGroupZuordnungsnr();
	}

	/**
	 * @param string $id
	 *
	 * @return void
	 */
	public function setIdFromPleasant(string $id)
	{
		$this->setId(preg_replace("/[^a-f0-9 ]/", '', strtolower($id)));
	}
}
