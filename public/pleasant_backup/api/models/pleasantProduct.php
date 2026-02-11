<?php

namespace hobaIT;

class pleasantProduct extends product
{
	public ?string $categoryNumberPleasant;
	public ?pleasantProductCustomFields $customFields;

	public function __construct()
	{
		parent::__construct();
		$this->customFields = new pleasantProductCustomFields();
	}

	/**
	 * @return string|null
	 */
	public function getCategoryNumberPleasant(): ?string
	{
		return $this->categoryNumberPleasant;
	}

	/**
	 * @param string|null $categoryNumberPleasant
	 */
	public function setCategoryNumberPleasant(?string $categoryNumberPleasant): void
	{
		$this->categoryNumberPleasant = $categoryNumberPleasant;
	}

	/**
	 * @return pleasantProductCustomFields|null
	 */
	public function getCustomFields(): ?pleasantProductCustomFields
	{
		return $this->customFields;
	}

	/**
	 * @param pleasantProductCustomFields|null $customFields
	 */
	public function setCustomFields(?pleasantProductCustomFields $customFields): void
	{
		$this->customFields = $customFields;
	}

	/**
	 * Set category id by given pleasant internal id
	 *
	 * @param string $id
	 */
	public function setCategoryByPleasantInternalId(string $id)
	{
		$client   = new pleasantClient();
		$response = $client->getCategoryByPleasantInternalId($id);
		if ($response->total > 0)
		{
			self::setCategories([$response->data[0]->id]);
		}
		else
		{
			self::setCategories([]);
		}
	}

	/**
	 * Get product html for debugging purposes
	 * @return string
	 */
	public function toHtml(): string
	{

		$string = '<div class="pleasant-product">' . parent::toHtml();

		if (!empty($this->customFields->getHazards()))
		{
			$string .= '<h3 style="color:#f00">Gefahrenstoffe:</h3>';
			$string .= self::getHazardsForDisplay($this->customFields->getHazards());
		}

		if (!empty($this->customFields->getDatasheets()))
		{
			$string .= '<h3 style="color:#0f0">Datenblätter:</h3>';
			$string .= '<div class="datasheets">' . self::getDatasheetsForDisplay($this->customFields->getDatasheets()) . '</div>';
		}

		return $string . '</div><hr/>';
	}

	/**
	 * Get pictures of an article (if more than one)
	 *
	 * @param string  $articleNumber
	 * @param ?string $picture
	 * @param ?string $path
	 *
	 * @return string[]
	 */
	protected function getPictures(string $articleNumber, ?string $picture = '', ?string $path = '/artikelbilder/Artikel/'): array
	{
		$pictures = [];

		if (!empty($picture))
		{
			$pictures[] = self::wrapImage($picture, $path);
		}

		$fullPath = BASEDIR . $path . '/' . $articleNumber . ' *.*';
		foreach (glob($fullPath) as $image)
		{
			$pictures[] = self::wrapImage(pathinfo($image)['basename'], $path);
		}

		return $pictures;
	}

	/**
	 * @param string $date
	 *
	 * @return void
	 */
	public function setLastUpdate(string $date): void
	{
		$this->customFields->setCustomProductLastupdate($date);
	}

	/**
	 * @return string
	 */
	public function getLastUpdate(): string
	{
		return $this->customFields->getCustomProductLastupdate();
	}

	/**
	 * @param string $picto
	 *
	 * @return void
	 */
	public function setPicto(?string $picto): void
	{
		$this->customFields->setCustomProductPicto($picto);
	}

	/**
	 * @return string
	 */
	public function getPicto(): string
	{
		return $this->customFields->getCustomProductPicto();
	}

}
