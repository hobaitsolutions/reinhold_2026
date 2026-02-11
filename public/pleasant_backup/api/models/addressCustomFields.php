<?php

namespace hobaIT;

class addressCustomFields
{
	public ?string $custom_pleasant_addressnumber;
	public ?string $custom_pleasant_salutationtext;
	public ?string $custom_pleasant_salutation;
	public ?string $custom_pleasant_name;

	public function getCustomPleasantName(): ?string
	{
		return $this->custom_pleasant_name;
	}

	public function setCustomPleasantName(?string $custom_pleasant_name): void
	{
		$this->custom_pleasant_name = $custom_pleasant_name;
	}

	public function getCustomPleasantSalutation(): ?string
	{
		return $this->custom_pleasant_salutation;
	}

	public function setCustomPleasantSalutation(?string $custom_pleasant_salutation): void
	{
		$this->custom_pleasant_salutation = $custom_pleasant_salutation;
	}

	public function getCustomPleasantSalutationtext(): ?string
	{
		return $this->custom_pleasant_salutationtext;
	}

	public function setCustomPleasantSalutationtext(?string $custom_pleasant_salutationtext): void
	{
		$this->custom_pleasant_salutationtext = $custom_pleasant_salutationtext;
	}

	public function getCustomPleasantAddressnumber(): ?string
	{
		return $this->custom_pleasant_addressnumber;
	}

	public function setCustomPleasantAddressnumber(?string $custom_pleasant_addressnumber): void
	{
		$this->custom_pleasant_addressnumber = $custom_pleasant_addressnumber;
	}

}
