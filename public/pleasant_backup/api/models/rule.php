<?php

namespace hobaIT;

class rule
{
	public string $name;
	public int $priority = 100;
	public array $conditions = [];
	public ?string $id;
	public ruleCustomFrields $customFields;

	public function __construct(?string $personennr = '')
	{
		$this->customFields = new ruleCustomFrields();
		$this->customFields->setCustomRulePersonennr($personennr);
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
	 * @return int
	 */
	public function getPriority(): int
	{
		return $this->priority;
	}

	/**
	 * @param int $priority
	 */
	public function setPriority(int $priority): void
	{
		$this->priority = $priority;
	}

	/**
	 * @return array
	 */
	public function getConditions(): array
	{
		return $this->conditions;
	}

	/**
	 * @param array $conditions
	 */
	public function setConditions(array $conditions): void
	{
		$this->conditions = $conditions;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId(string $id): void
	{
		$this->id = $id;
	}

	/**
	 * @param string $id
	 *
	 * @return void
	 */
	public function setIdFromPleasant(string $id)
	{
		$this->setId(self::convertIdFromPleasant($id));
	}

	/**
	 * @param $id
	 *
	 * @return array|string|string[]|null
	 */
	public static function convertIdFromPleasant($id)
	{
		return preg_replace("/[^a-f0-9 ]/", '', strtolower($id));
	}
}
