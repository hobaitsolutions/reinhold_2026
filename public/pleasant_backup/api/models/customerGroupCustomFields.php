<?php

namespace hobaIT;

class customerGroupCustomFields
{
	public string $custom_group_zuordnungsnr;

	/**
	 * @return string
	 */
	public function getCustomGroupZuordnungsnr(): string
	{
		return $this->custom_group_zuordnungsnr;
	}

	/**
	 * @param string $custom_group_zuordnungsnr
	 */
	public function setCustomGroupZuordnungsnr(string $custom_group_zuordnungsnr): void
	{
		$this->custom_group_zuordnungsnr = $custom_group_zuordnungsnr;
	}
}
