<?php

namespace hobaIT;

class salutation
{
	public string $salutationId = '';
	public string $salutationKey;
	public string $displayName;
	public string $letterName;

	/**
	 * salutation constructor.
	 *
	 * @param string|null $displayName
	 * @param string|null $letterName
	 */
	public function __construct(?string $displayName = '', ?string $letterName = '')
	{
		if (!empty($displayName))
		{
			self::setDisplayName($displayName);
			if (empty($letterName))
			{
				self::setLetterName($displayName);
			}
			else
			{
				self::setLetterName($letterName);
			}
			self::setSalutationKey($displayName);
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
	 * @param string $salutationId
	 */
	public function setSalutationId(string $salutationId): void
	{
		$this->salutationId = $salutationId;
	}

	/**
	 * @return string
	 */
	public function getSalutationKey(): string
	{
		return $this->salutationKey;
	}

	/**
	 * @param string $salutationKey
	 */
	public function setSalutationKey(string $salutationKey): void
	{
		$this->salutationKey = (string) preg_replace("/[^a-z0-9.]+/i", "", $salutationKey);;
	}

	/**
	 * @return string
	 */
	public function getDisplayName(): string
	{
		return $this->displayName;
	}

	/**
	 * @param string $displayName
	 */
	public function setDisplayName(string $displayName): void
	{
		$this->displayName = $displayName;
	}

	/**
	 * @return string
	 */
	public function getLetterName(): string
	{
		return $this->letterName;
	}

	/**
	 * @param string $letterName
	 */
	public function setLetterName(string $letterName): void
	{
		$this->letterName = $letterName;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getSalutationId();
	}
}
