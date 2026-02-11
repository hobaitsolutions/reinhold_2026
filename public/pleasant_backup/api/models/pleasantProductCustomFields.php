<?php

namespace hobaIT;

class pleasantProductCustomFields
{
	protected ?array $hazards;
	protected ?array $datasheets;
	public string $custom_product_datasheets = '';
	public string $custom_product_hazards = '';
	public string $custom_product_hazards_p = '';
	public string $custom_product_manufacturer = '';
	public string $custom_product_short_desc = '';
	public string $custom_product_information = '';
	public string $custom_product_lastupdate = '';
	public string $custom_product_picto = '';
	public string $custom_product_row_id = '';
	public string $custom_product_pack_unit_quantity = '1';
	public ?string $custom_product_HazardStatements = '';
	public ?string $custom_product_AdrNumbers = '';
	public ?string $custom_product_AdrSpecs = '';
	public ?string $custom_product_AdrText = '';

	/**
	 * @return string
	 */
	public function getCustomProductPackUnitQuantity(): string
	{
		return $this->custom_product_pack_unit_quantity;
	}

	/**
	 * @param string $custom_product_pack_unit_quantity
	 */
	public function setCustomProductPackUnitQuantity(string $custom_product_pack_unit_quantity): void
	{
		$this->custom_product_pack_unit_quantity = $custom_product_pack_unit_quantity;
	}

	/**
	 * @return string
	 */
	public function getCustomProductRowId(): string
	{
		return $this->custom_product_row_id;
	}

	/**
	 * @param string $custom_product_row_id
	 */
	public function setCustomProductRowId(string $custom_product_row_id): void
	{
		$this->custom_product_row_id = $custom_product_row_id;
	}


	/**
	 * @return string
	 */
	public function getCustomProductLastupdate(): string
	{
		return $this->custom_product_lastupdate;
	}

	/**
	 * @param string $custom_product_lastupdate
	 */
	public function setCustomProductLastupdate(string $custom_product_lastupdate): void
	{
		$this->custom_product_lastupdate = $custom_product_lastupdate;
	}

	/**
	 * @return string
	 */
	public function getCustomProductPicto(): string
	{
		return $this->custom_product_picto;
	}

	/**
	 * @param string $custom_product_picto
	 */
	public function setCustomProductPicto(?string $custom_product_picto): void
	{
		if (!empty($custom_product_picto))
		{
//			var_dump($custom_product_picto);
			$this->custom_product_picto = $custom_product_picto;
		}
	}

	/**
	 * @return array|null
	 */
	public function getHazards(): ?array
	{
		return $this->hazards;
	}



	/**
	 * Sets the attribute using the provided hazards array and encodes it as a JSON string.
	 * If the hazards array is empty, the attribute is unset.
	 *
	 * @param array|null $attrData   An array of hazards or null if no hazards are provided
	 * @param string     $attribute The name of the attribute to be set or unset
	 *
	 * @return void
	 */
	public function setAttribute(?array $attrData, string $attribute): void
	{
		if (!empty($attrData))
		{
			$this->hazards                = $attrData;
			$this->$attribute = json_encode($attrData);
		}
		else
		{
			unset($this->$attribute);
		}
	}

	/**
	 * @return string
	 */
	public function getLastUpdate(): string
	{
		return $this->custom_product_lastupdate;
	}

	/**
	 * @param string $custom_product_lastupdate
	 */
	public function setLastUpdate(string $custom_product_lastupdate): void
	{
		$this->custom_product_lastupdate = $custom_product_lastupdate;
	}

	/**
	 * @return array|null
	 */
	public function getDatasheets(): ?array
	{
		return $this->datasheets;
	}

	/**
	 * @param array|null $datasheets
	 */
	public function setDatasheets(?array $datasheets): void
	{
		if (!empty($datasheets))
		{
			$this->datasheets                = $datasheets;
			$this->custom_product_datasheets = json_encode($datasheets);
		}
		else
		{
			unset($this->custom_product_datasheets);
		}
	}

	/**
	 * @return string
	 */
	public function getCustomProductDatasheets(): string
	{
		return $this->custom_product_datasheets;
	}

	/**
	 * @param string $custom_product_datasheets
	 */
	public function setCustomProductDatasheets(string $custom_product_datasheets): void
	{
		$this->custom_product_datasheets = $custom_product_datasheets;
	}

	/**
	 * @return string
	 */
	public function getCustomProductHazards(): string
	{
		return $this->custom_product_hazards;
	}

	/**
	 * @param string $custom_product_hazards
	 */
	public function setCustomProductHazards(string $custom_product_hazards): void
	{
		$this->custom_product_hazards = $custom_product_hazards;
	}

	/**
	 * @return string
	 */
	public function getCustomProductShortDesc(): string
	{
		return $this->custom_product_short_desc;
	}

	/**
	 * @param string $custom_product_short_desc
	 */
	public function setCustomProductShortDesc(string $custom_product_short_desc): void
	{
		$this->custom_product_short_desc = $custom_product_short_desc;
	}

	/**
	 * @return string
	 */
	public function getCustomProductInformation(): string
	{
		return $this->custom_product_information;
	}

	/**
	 * @param string $custom_product_information
	 */
	public function setCustomProductInformation(string $custom_product_information): void
	{
		$this->custom_product_information = $custom_product_information;
	}

	/**
	 * @return string
	 */
	public function getCustomProductHazardStatements(): ?string
	{
		return $this->custom_product_HazardStatements;
	}

	/**
	 * @param string $custom_product_HazardStatements
	 */
	public function setCustomProductHazardStatements(?string $custom_product_HazardStatements): void
	{
		$this->custom_product_HazardStatements = $custom_product_HazardStatements;
	}



	/**
	 * @return string
	 */
	public function getCustomProductAdrNumbers(): ?string
	{
		return $this->custom_product_AdrNumbers;
	}

	/**
	 * @param string $custom_product_AdrNumbers
	 */
	public function setCustomProductAdrNumbers(?string $custom_product_AdrNumbers): void
	{
		$this->custom_product_AdrNumbers = $custom_product_AdrNumbers;
	}

	/**
	 * @return string
	 */
	public function getCustomProductAdrSpecs(): ?string
	{
		return $this->custom_product_AdrSpecs;
	}

	/**
	 * @param string $custom_product_AdrSpecs
	 */
	public function setCustomProductAdrSpecs(?string $custom_product_AdrSpecs): void
	{
		$this->custom_product_AdrSpecs = $custom_product_AdrSpecs;
	}

	/**
	 * @return string
	 */
	public function getCustomProductAdrText(): ?string
	{
		return $this->custom_product_AdrText;
	}

	/**
	 * @param string $custom_product_AdrText
	 */
	public function setCustomProductAdrText(?string $custom_product_AdrText): void
	{
		$this->custom_product_AdrText = $custom_product_AdrText;
	}

	/**
	 * @return string
	 */
	public function getCustomProductHazardsP(): string
	{
		return $this->custom_product_hazards_p;
	}

	/**
	 * @param string $custom_product_hazards_p
	 *
	 * @return void
	 */
	public function setCustomProductHazardsP(?string $custom_product_hazards_p): void
	{
		$this->custom_product_hazards_p = $custom_product_hazards_p;
	}

	/**
	 * @return string
	 */
	public function getCustomProductManufacturer(): string
	{
		return $this->custom_product_manufacturer;
	}

	/**
	 * @param string $custom_product_manufacturer
	 *
	 * @return void
	 */
	public function setCustomProductManufacturer(string $custom_product_manufacturer): void
	{
		$this->custom_product_manufacturer = $custom_product_manufacturer;
	}


}
