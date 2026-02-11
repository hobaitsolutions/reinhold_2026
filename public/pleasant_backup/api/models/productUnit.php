<?php

namespace hobaIT;

class productUnit
{
	public string $id;
	protected ?string $name;

	public function __construct(string $id, ?string $name = '')
	{
		$this->id   = $id;
		$this->name = $name;
	}


	/**
	 * Get string for display purposes
	 * @return string
	 */
	public function __toString(): string
	{
		$string = (string) $this->id;
		if (!empty($this->name))
		{
			$string = $this->name . '(' . $string . ')';
		}

		return $string;
	}
}
