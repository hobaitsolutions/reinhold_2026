<?php
namespace hobaIT;
#[\AllowDynamicProperties]

class value
{
	public string $operator;
	public array $numbers;

	public function __construct(string $operator = '=', array $numbers = [], $property = null)
	{
		$this->operator = $operator;
		if (empty($property)){
			$this->numbers  = $numbers;
		}
		else{
			$this->$property = $numbers;
		}
	}

	/**
	 * @return string
	 */
	public function getOperator(): string
	{
		return $this->operator;
	}

	/**
	 * @param string $operator
	 */
	public function setOperator(string $operator): void
	{
		$this->operator = $operator;
	}

	/**
	 * @return array
	 */
	public function getNumbers(): array
	{
		return $this->numbers;
	}

	/**
	 * @param array $numbers
	 */
	public function setNumbers(array $numbers): void
	{
		$this->numbers = $numbers;
	}
}
