<?php

namespace hobaIT;

class address
{

	public ?string $id;
	public ?string $customerId;
	public string $countryId = '51118f259dd741a2b10e30e49d748528';
	public string $salutationId = '1320a4a7a21e4dd9a3f369f1c2a23104';
	public string $firstName;
	public string $lastName;
	public ?string $zipcode;
	public ?string $city;
	public ?string $company = '';
	public string $street;
	public string $department;
	public string $title;
	public ?string $phoneNumber;
	public string $additionalAddressLine1;
	public string $additionalAddressLine2;
	public addressCustomFields $customFields;

	public function __construct()
	{
		$this->customFields = new addressCustomFields();
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
	public function getCustomerId(): string
	{
		return $this->customerId;
	}

	/**
	 * @param string $customerId
	 */
	public function setCustomerId(string $customerId): void
	{
		$this->customerId = $customerId;
	}

	/**
	 * @return string
	 */
	public function getCountryId(): ?string
	{
		return $this->countryId;
	}

	/**
	 * @param ?string $countryId
	 */
	public function setCountryId(?string $countryId): void
	{
		if (!empty($countryId))
		{
			$this->countryId = $countryId;
		}
	}

	/**
	 * @return string
	 */
	public function getSalutationId(): string
	{
		return $this->salutationId;
	}

	/**
	 * @param ?string $salutationId
	 */
	public function setSalutationId(?string $salutationId): void
	{
		$this->salutationId = (string) $salutationId;
	}

	/**
	 * @return string
	 */
	public function getFirstName(): string
	{
		return $this->firstName;
	}

	/**
	 * @param string $firstName
	 */
	public function setFirstName(string $firstName): void
	{
		if (empty($firstName))
		{
			$firstName = 'unbekannt';
		}
		$this->firstName = $firstName;
	}

	/**
	 * @return string
	 */
	public function getLastName(): string
	{
		return $this->lastName;
	}

	/**
	 * @param string $lastName
	 */
	public function setLastName(string $lastName): void
	{
		if (empty($lastName))
		{
			$lastName = ' ';
		}
		$this->lastName = $lastName;
	}

	/**
	 * @return string
	 */
	public function getZipcode(): string
	{
		return $this->zipcode;
	}

	/**
	 * @param string $zipcode
	 */
	public function setZipcode(?string $zipcode): void
	{
		if (empty($zipcode))
		{
			$zipcode = '00000';
		}
		$this->zipcode = $zipcode;
	}

	/**
	 * @return string
	 */
	public function getCity(): string
	{
		return $this->city;
	}

	/**
	 * @param string $city
	 */
	public function setCity(?string $city): void
	{
		if (empty($city))
		{
			$city = 'unbekannt';
		}
		$this->city = $city;
	}

	/**
	 * @return string
	 */
	public function getCompany(): string
	{
		return $this->company;
	}

	/**
	 * @param ?string $company
	 */
	public function setCompany(?string $company): void
	{
		$this->company = $company;
	}

	/**
	 * @return string
	 */
	public function getStreet(): string
	{
		return $this->street;
	}

	/**
	 * @param string $street
	 */
	public function setStreet(string $street): void
	{
		$this->street = $street;
	}

	/**
	 * @return string
	 */
	public function getDepartment(): string
	{
		return $this->department;
	}

	/**
	 * @param string $department
	 */
	public function setDepartment(string $department): void
	{
		$this->department = $department;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 */
	public function setTitle(string $title): void
	{
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getPhoneNumber(): string
	{
		return $this->phoneNumber;
	}

	/**
	 * @param ?string $phoneNumber
	 */
	public function setPhoneNumber(?string $phoneNumber): void
	{
		$this->phoneNumber = $phoneNumber;
	}

	/**
	 * @return string
	 */
	public function getAdditionalAddressLine1(): string
	{
		return $this->additionalAddressLine1;
	}

	/**
	 * @param string $additionalAddressLine1
	 */
	public function setAdditionalAddressLine1(string $additionalAddressLine1): void
	{
		$this->additionalAddressLine1 = $additionalAddressLine1;
	}

	/**
	 * @return string
	 */
	public function getAdditionalAddressLine2(): string
	{
		return $this->additionalAddressLine2;
	}

	/**
	 * @param string $additionalAddressLine2
	 */
	public function setAdditionalAddressLine2(string $additionalAddressLine2): void
	{
		$this->additionalAddressLine2 = $additionalAddressLine2;
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
