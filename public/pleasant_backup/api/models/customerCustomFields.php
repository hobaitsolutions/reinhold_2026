<?php

namespace hobaIT;

class customerCustomFields
{
	public ?string $custom_customer_personennr;
	public ?string $custom_customer_contact;
	public ?string $custom_customer_login_id;
	public ?string $custom_customer_eg_ident;
	public ?string $custom_customer_field04;

	public function getCustomCustomerEgIdent(): ?string
	{
		return $this->custom_customer_eg_ident;
	}

	public function setCustomCustomerEgIdent(?string $custom_customer_eg_ident): void
	{
		$this->custom_customer_eg_ident = $custom_customer_eg_ident;
	}

	public function getCustomCustomerField04(): ?string
	{
		return $this->custom_customer_field04;
	}

	public function setCustomCustomerField04(?string $custom_customer_field04): void
	{
		$this->custom_customer_field04 = $custom_customer_field04;
	}

	/**
	 * @return string
	 */
	public function getCustomCustomerPersonennr(): string
	{
		return $this->custom_customer_personennr;
	}

	/**
	 * @param string $custom_customer_personennr
	 */
	public function setCustomCustomerPersonennr(?string $custom_customer_personennr): void
	{
		$this->custom_customer_personennr = $custom_customer_personennr;
	}

	/**
	 * @return string
	 */
	public function getCustomCustomerContact(): string
	{
		return $this->custom_customer_contact;
	}

	/**
	 * @param ?string $custom_customer_contact
	 */
	public function setCustomCustomerContact(?string $custom_customer_contact): void
	{
		$this->custom_customer_contact = $custom_customer_contact;
	}

	/**
	 * @return string|null
	 */
	public function getCustomCustomerLoginId(): ?string
	{
		return $this->custom_customer_login_id;
	}

	/**
	 * @param string|null $custom_customer_login_id
	 */
	public function setCustomCustomerLoginId(?string $custom_customer_login_id): void
	{
		$this->custom_customer_login_id = $custom_customer_login_id;
	}

}
