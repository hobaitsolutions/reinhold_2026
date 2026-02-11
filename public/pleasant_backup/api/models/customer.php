<?php

namespace hobaIT;

class customer
{
	public ?string $id;
	public string $salesChannelId = 'f8395072c9774e6c96b534b504d6d073';
	public string $languageId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
	public string $groupId = 'cfbd5018d38d41d8adca10d94fc8bdd6';
	public string $defaultPaymentMethodId = 'f4656806ae164ce7b01c1ece646464ac';
	public string $customerNumber;
	public string $salutationId;

	public string $firstName;
	public string $lastName;
	public string $email;
	public ?string $company;
	public bool $active = true;
	public bool $guest = false;

	public address $defaultBillingAddress;
	public address $defaultShippingAddress;
	public ?string $password;
	public ?array $addresses;

	public customerCustomFields $customFields;

	public function __construct()
	{
		$this->customFields = new customerCustomFields();
	}

	/**
	 * @return ?string
	 */
	public function getId(): ?string
	{
		return $this->id;
	}

	/**
	 * @param ?string $id
	 */
	public function setId(?string $id): void
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getSalesChannelId(): string
	{
		return $this->salesChannelId;
	}

	/**
	 * @param string $salesChannelId
	 */
	public function setSalesChannelId(string $salesChannelId): void
	{
		$this->salesChannelId = $salesChannelId;
	}

	/**
	 * @return string
	 */
	public function getLanguageId(): string
	{
		return $this->languageId;
	}

	/**
	 * @param string $languageId
	 */
	public function setLanguageId(string $languageId): void
	{
		$this->languageId = $languageId;
	}

	/**
	 * @return string
	 */
	public function getGroupId(): string
	{
		return $this->groupId;
	}

	/**
	 * @param string $groupId
	 */
	public function setGroupId(string $groupId): void
	{
		$this->groupId = $groupId;
	}

	/**
	 * @return string
	 */
	public function getCustomerNumber(): string
	{
		return $this->customerNumber;
	}

	/**
	 * @param string $customerNumber
	 */
	public function setCustomerNumber(string $customerNumber): void
	{
		$this->customerNumber = $customerNumber;
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
			$firstName = ' ';
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
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail(string $email): void
	{

		$this->email = $email;
	}

	/**
	 * @return string|null
	 */
	public function getCompany(): ?string
	{
		return $this->company;
	}

	/**
	 * @param string|null $company
	 */
	public function setCompany(?string $company): void
	{
		$this->company = $company;
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param bool $active
	 */
	public function setActive(bool $active): void
	{
		$this->active = $active;
	}

	/**
	 * @return bool
	 */
	public function isGuest(): bool
	{
		return $this->guest;
	}

	/**
	 * @param bool $guest
	 */
	public function setGuest(bool $guest): void
	{
		$this->guest = $guest;
	}

	/**
	 * @param address $address
	 *
	 * @return array
	 */
	public function setDefaultAddresses(address $address): void
	{
		$this->defaultBillingAddress  = $address;
		$this->defaultShippingAddress = $address;
	}

	/**
	 * @return address
	 */
	public function getDefaultBillingAddress(): address
	{
		return $this->defaultBillingAddress;
	}

	/**
	 * @param address $defaultBillingAddress
	 */
	public function setDefaultBillingAddress(address $defaultBillingAddress): void
	{
		$this->defaultBillingAddress = $defaultBillingAddress;
	}

	/**
	 * @return address
	 */
	public function getDefaultShippingAddress(): address
	{
		return $this->defaultShippingAddress;
	}

	/**
	 * @param address $defaultShippingAddress
	 */
	public function setDefaultShippingAddress(address $defaultShippingAddress): void
	{
		$this->defaultShippingAddress = $defaultShippingAddress;
	}


	/**
	 * @return string
	 */
	public function getDefaultPaymentMethodId(): string
	{
		return $this->defaultPaymentMethodId;
	}

	/**
	 * @param string $defaultPaymentMethodId
	 */
	public function setDefaultPaymentMethodId(string $defaultPaymentMethodId): void
	{
		$this->defaultPaymentMethodId = $defaultPaymentMethodId;
	}

	/**
	 * @return string
	 */
	public function getSalutationId(): string
	{
		return $this->salutationId;
	}

	/**
	 * @param string $salutationId
	 */
	public function setSalutationId(string $salutationId): void
	{
		$this->salutationId = $salutationId;
	}

	/**
	 * @return string|null
	 */
	public function getPassword(): ?string
	{
		return $this->password;
	}

	/**
	 * @param string|null $password
	 */
	public function setPassword(?string $password): void
	{
		$this->password = $password;
	}

	/**
	 * @return array|null
	 */
	public function getAddresses(): ?array
	{
		return $this->addresses;
	}

	/**
	 * @param array|null $addresses
	 */
	public function setAddresses(?array $addresses): void
	{
		$this->addresses = $addresses;
	}

	/**
	 * @return string|null
	 */
	public function getPersonennr(): ?string
	{
		return $this->customFields->getCustomCustomerPersonennr();
	}

	/**
	 * @param string|null $personennr
	 */
	public function setPersonennr(?string $personennr): void
	{
		$this->customFields->setCustomCustomerPersonennr($personennr);
	}

	/**
	 * @return string|null
	 */
	public function getContact(): ?string
	{
		return $this->customFields->getCustomCustomerContact();
	}

	/**
	 * @param string|null $contact
	 */
	public function setContact(?string $contact): void
	{
		$this->customFields->setCustomCustomerContact($contact);
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
//
//{
//	"salesChannelId": "f8395072c9774e6c96b534b504d6d073",
//	"languageId": "/2fbb5fe2e29a4d70aa5854ce7ce3e20b",
//	"groupId": "cfbd5018d38d41d8adca10d94fc8bdd6",
//	"customerNumber": "oaedrmaorbn121`2323plaease",
//	"firstName": "Hans",
//	"lastName": "Wurst",
//	"email": "hans@wurst.com",
//	"company": "wurst ltd.",
//	"active": true,
//	"guest": false,
//}
