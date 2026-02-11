<?php

namespace hobaIT;

class notFilter extends filter
{
	public array $filter;

	public function __construct(array $filter)
	{
		$this->setFilter($filter);
	}

	/**
	 * @return array
	 */
	public function getFilter()
	{
		return $this->filter;
	}

	/**
	 * @param mixed $filter
	 */
	public function setFilter($filter): void
	{
		$this->filter = [[
			'type'    => 'not',
			'queries' => $filter
		]];
	}
}
