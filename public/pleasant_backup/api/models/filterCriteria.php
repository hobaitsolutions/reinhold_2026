<?php

namespace hobaIT;

class filterCriteria
{
	public string $type;
	public string $field;
	public $value;
	public ?object $parameters;

	public function __construct(string $field, $value, string $type = 'equals')
	{
		self::setField($field);
		self::setValue($value);
		self::setType($type);
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType(string $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getField(): string
	{
		return $this->field;
	}

	/**
	 * @param string $field
	 */
	public function setField(string $field): void
	{
		$this->field = $field;
	}

	/**
	 * @return string
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param ?string $value
	 */
	public function setValue( $value): void
	{
		$this->value = $value;
	}

}
