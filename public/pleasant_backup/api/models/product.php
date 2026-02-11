<?php

namespace hobaIT;
const BASEDIR               = '/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/';
const URL_EXTERNAL          = "https://shop.reinhold-sohn-hygiene.de/";
const IMAGE_EXTERNAL_FOLDER = 'artikelbilder/Artikel/';

//
//{
//	"productNumber": "12345",
//  "customFields": {
//	"hazards": null,
//    "datasheets": null
//  },
//  "name": "Basisprodukt",
//  "content": "Lorem IPSUM",
//  "price":
//    [
//
//    ]
//  ,
//  "taxId": "996ba1788caf4f62aa2e08500a618af0",
//  "stock": 1
//}

class product
{

	public array $categories = [];
	public string $description;
	public ?string $image;
	public string $name = 'Product';
	public array $price = [];
	public string $productNumber;
	public ?float $purchaseUnit = 1;
	public ?float $referenceUnit = 1;
	public string $salesChannelDefault = 'f8395072c9774e6c96b534b504d6d073';
	public int $stock = 0;
	public string $taxId = '996ba1788caf4f62aa2e08500a618af0';
	public productUnit $unit;
	public array $visibilities = [];
	public image $cover;
	public ?string $id;
	public bool $active = true;
	public bool $isCloseout = false;
	public string $packUnit;


	public function __construct()
	{
		$this->setVisibilities();
	}

	/**
	 * @return bool
	 */
	public function isCloseout(): bool
	{
		return $this->isCloseout;
	}

	/**
	 * @param bool $isCloseout
	 */
	public function setIsCloseout(bool $isCloseout): void
	{
		$this->isCloseout = $isCloseout;
	}

	/**
	 * @return array
	 */
	public function getVisibilities(): array
	{
		return $this->visibilities;
	}

	/**
	 * @param array $visibilities
	 */
	public function setVisibilities(array $visibilities = []): void
	{
		if (empty($visibilities))
		{
			$visibilities = [
				$this->getSalesChannelDefault()
			];
		}

		foreach ($visibilities as $visibility)
		{
			$salesChannel                 = (object) [];
			$salesChannel->salesChannelId = $visibility;
			$salesChannel->visibility     = 30;
			$this->visibilities[]         = $salesChannel;
		}
	}

	/**
	 * @return array
	 */
	public function getCategories(): array
	{
		return $this->categories;
	}

	/**
	 * @param array $categories
	 */
	public function setCategories(array $categories): void
	{
		foreach ($categories as $category)
		{
			$this->categories[] = new productCategory($category);
		}
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getPrice(): string
	{
		return (string) $this->price[0];
	}

	/**
	 * @param float $price
	 */
	public function setPrice(float $price): void
	{
		$this->price = [
			new productPrice($price)
		];
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription(string $description): void
	{
		$this->description = $description;
	}

	/**
	 * @return string|null
	 */
	public function getImage(): ?string
	{
		return $this->image;
	}

	/**
	 * @return string
	 */
	public function getSalesChannelDefault(): string
	{
		return $this->salesChannelDefault;
	}

	/**
	 * @param string $salesChannelDefault
	 */
	public function setSalesChannelDefault(string $salesChannelDefault): void
	{
		$this->salesChannelDefault = $salesChannelDefault;
	}


	/**
	 * @param string|null $image
	 */
	public function setImage(?string $image): void
	{

		if (!empty($image))
		{
			$this->image = 'Z:\data\Artikel\\' . ($image);
			$info        = pathinfo($this->image);
			if (strtolower($info['extension']) == 'gif')
			{
				$this->image = null;
			}
		}
		else
		{
			$this->image = $image;
		}
	}

	/**
	 * @return float|null
	 */
	public function getPurchaseUnit(): ?float
	{
		return $this->purchaseUnit;
	}

	/**
	 * @param float|null $purchaseUnit
	 */
	public function setPurchaseUnit(?float $purchaseUnit): void
	{
		if ($purchaseUnit != 0)
		{
			$this->purchaseUnit = 1 / $purchaseUnit;
		}
		else
		{
			$this->purchaseUnit = 0;
		}
	}

	/**
	 * @return float|int
	 */
	public function getReferenceUnit()
	{
		return $this->referenceUnit;
	}

	/**
	 * @param float|int $referenceUnit
	 */
	public function setReferenceUnit($referenceUnit): void
	{
		$this->referenceUnit = $referenceUnit;
	}

	/**
	 * @return int
	 */
	public function getStock(): int
	{
		return $this->stock;
	}

	/**
	 * @param int $stock
	 */
	public function setStock(int $stock): void
	{
		$this->stock = $stock;
	}

	/**
	 * @return productUnit
	 */
	public function getUnit(): productUnit
	{
		return $this->unit;
	}

	/**
	 * @param string  $unit
	 * @param ?string $name
	 */
	public function setUnit(string $unit, ?string $name = ''): void
	{
		$this->unit = new productUnit($unit, $name);
	}

	/**
	 * @return string
	 */
	public function getProductNumber(): string
	{
		return $this->productNumber;
	}

	/**
	 * @param string $productNumber
	 */
	public function setProductNumber(string $productNumber): void
	{
		$this->productNumber = $productNumber;
	}

	/**
	 * @return string
	 */
	public function getTaxId(): string
	{
		return $this->taxId;
	}

	/**
	 * @param string $taxId
	 */
	public function setTaxId(string $taxId): void
	{
		$this->taxId = $taxId;
	}


	/**
	 * Display product for debugging purposes
	 */
	public function toHtml(): string
	{
		$string = '<div class="article"><h2>' . $this->getName() . '</h2>';
		if (!empty($this->subtitle))
		{
			$string .= '<h3>' . $this->getSubtitle() . '</h3>';
		}
		$string .= '<p>Artikelnummer: ' . $this->getProductNumber() . '</p>';

//		if (!empty($this->categories[$this->categoryNumberPleasant]))
//		{
//			$string .= 'Kategorie: ' . $this->categories[$this->categoryNumberPleasant]['breadcrumb'];
//		}

		$string .= '<p>' . $this->getDescription() . '</p>';
		$string .= '<p>Preis: <strong>' . $this->getPrice() . '</strong> </p>';

		if (!empty($this->getReferenceUnit()))
		{
			$basePrice = new \hobaIT\productPrice(floatval($this->getPurchaseUnit()) * floatval($this->getPrice()));
			$string    .= '<p>Grundpreis:</p> ' . $basePrice . ' / ' . $this->getUnit() . '</p>';
		}

		$string .= $this->image ? self::wrapImage($this->image) : '';
		$string .= '</div>';

		return $string;
	}


	/**
	 * wrap image in html tag
	 *
	 * @param string  $name
	 * @param ?string $path
	 *
	 * @return string
	 */
	protected function wrapImage(string $name, ?string $path = '/artikelbilder/artikel/'): string
	{
		return '<img src="' . URL_EXTERNAL . $path . $name . '" alt="" />';
	}

	/**
	 * @return image
	 */
	public function getCover(): image
	{
		return $this->cover;
	}

	/**
	 * @param string $cover
	 */
	public function setCover(string $cover): void
	{
		$this->cover = new image($cover);
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
	 * @return string
	 */
	public function getPackUnit(): string
	{
		return $this->packUnit;
	}

	/**
	 * @param string $packUnit
	 */
	public function setPackUnit(string $packUnit): void
	{
		$this->packUnit = $packUnit;
	}
}





