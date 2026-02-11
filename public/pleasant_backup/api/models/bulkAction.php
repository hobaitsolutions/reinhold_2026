<?php

namespace hobaIT;


class bulkAction
{
	public string $entity = 'product';
	public string $action = 'upsert';
	public array $payload;

	/**
	 * @return string
	 */
	public function getEntity(): string
	{
		return $this->entity;
	}

	/**
	 * @param string $entity
	 */
	public function setEntity(string $entity): void
	{
		$this->entity = $entity;
	}

	/**
	 * @return string
	 */
	public function getAction(): string
	{
		return $this->action;
	}

	/**
	 * @param string $action
	 */
	public function setAction(string $action): void
	{
		$this->action = $action;
	}

	/**
	 * @return array
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * @param array $payload
	 */
	public function setPayload(array $payload): void
	{
		$this->payload = $payload;
	}


}





