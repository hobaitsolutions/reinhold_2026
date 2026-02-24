<?php

use GuzzleHttp\Exception\GuzzleException;
use hobaIT\pleasantClient;
use hobaIT\pleasantProduct;
use hobaIT\productPriceRule;


require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/lib/PDO.class.php');
require_once(__DIR__ . '/../api/pleasantClient.php');
require_once(__DIR__ . '/../api/models/product.php');
require_once(__DIR__ . '/lib/hobaIT.parsedown.php');
require_once(__DIR__ . '/../mysql/dbHandler.php');

const LOG = true;

/**
 * Class DBInterface
 *
 * This class serves as an interface for database-related operations, handling categories,
 * datasheets, base prices, units, stocks, customer groups, and other related entities.
 */
class DBInterface extends DB
{
	protected array $categories = [];
	protected array $datasheets = [];
	protected array $baseprices = [];
	protected array $units = [];
	protected array $stocks = [];
	protected array $customergroups = [];
	protected array $customergroupRules = [];
	protected array $productIds = [];
	protected array $representatives = [];
	protected array $pleasantCustomergroupRules = [];
	protected array $pleasantCustomerPriceRules = [];
	protected array $currentScopeVariables = [];
	protected array $salutations;

	protected string $scope_productNumber = '';
	protected stdClass $scope_currentOrder;

	public function __construct(string $db = 'Office')
	{
		parent::__construct('localhost', 1433, $db, 'sa', 'manageR01!');

	}

	/**
	 * Represents a string variable.
	 *
	 * @var string $string A simple string variable.
	 */
	public static function log(string $string, bool $writeToErrorFile = false)
	{
		if (!LOG) return;

		$log = date('d.m.Y H:i:s') . " {$string} \n";
		if ($writeToErrorFile)
		{
			file_put_contents('errors.txt', $log, FILE_APPEND);
		}
		echo $log;
	}

	/**
	 * Retrieves and processes web groups from the database, categorizing them into a tree structure based on their hierarchy.
	 *
	 * This function fetches groups from the database where the group designation starts with 'WEB' and meets other conditions.
	 * It organizes these groups into main categories, subcategories, and potential child subcategories based on a predefined
	 * category numbering scheme. The categories are stored in an array, with each category including its metadata and child categories.
	 *
	 * @throws Exception If any database query or processing functions throw an error.
	 */
	public function getWebGroups(): void
	{
		/*
		 * Scheme of category numbers:
		 *
		 *  0100 Main Category
		 *  -> 0101 Sub Category
		 *  -> 0102 Sub Category 2
		 *  ...
		 *  -> 0199 Sub Category 99
		 *  ----> 019901 Sub Sub Category for whatever reason
		 */
		$count      = 0;
		$g0         = $g1 = $g2 = [];
		$categories = [];

		$sql = "
			SELECT 
			    DISTINCT g.*,ggz.POSITION AS ebene 
			FROM 
			     gruppen g, gruppezuordnung ggz
			WHERE 
			        ggz.BEZEICHNUNG=g.BEZEICHNUNG AND upper(g.BEZEICHNUNG) LIKE 'WEB%' 
					AND g.GRUPPENZUORDNUNG<>'' and g.GRUPPENZUORDNUNG<>'.' and g.KENNZEICHEN='A'
			ORDER BY
				g.GRUPPENZUORDNUNG ASC
";

		$r = self::query($sql);
		foreach ($r as $res)
		{
			switch ($res['U_BEZEICHNUNG'])
			{
				case 'WEBGRUPP':
					array_push($g0, $res);
					break;
				case 'WEBGRUP1':
					array_push($g1, $res);
					break;
				case 'WEBGRUP2':
					array_push($g2, $res);
					break;
				default:
					break;
			}
		}

		//@todo simplify

		foreach ($g0 as $mainGroup)
		{
			$mainCat = (object) [];

			//category full name
			$grp = $mainGroup['GRUPPENZUORDNUNG'];

			$mainCat->id         = self::groupId($grp);
			$mainCat->name       = self::groupName($grp);
			$mainCat->pleasantId = $mainGroup['ZUORDNUNGSNR'];
			$mainCat->children   = [];
			$mainCat->parentId   = 'b72710dd17404caeb79190dd08bf50b9';
			$mainCat->sw_id      = self::addCategoryToDB($mainCat);

			echo '+ ' . $mainCat->name . '[' . $mainCat->id . ']<br>';

			foreach ($g1 as $subGroup)
			{
				$subCat = (object) [];

				$subGrp = $subGroup['GRUPPENZUORDNUNG'];

				$subCat->id         = self::groupId($subGrp);
				$subCat->name       = self::groupName($subGrp);
				$subCat->pleasantId = $subGroup['ZUORDNUNGSNR'];

				if (!empty($mainCat->sw_id))
				{
					$subCat->parentId = $mainCat->sw_id;
				}

				$diff = (int) $subCat->id - (int) $mainCat->id;

				if (($diff > 0) && ($diff < 100))
				{
					//echo '&nbsp;|----- ' . $subCat->name . ' [' . $subCat->id . ']<br>';
					$subCat->sw_id = self::addCategoryToDB($subCat);

					foreach ($g1 as $mightBeChildren)
					{
						//now look for children who are on the same level according to scheme
						$mbc = (object) [];

						$mbcGrp = $mightBeChildren['GRUPPENZUORDNUNG'];

						$mbc->id         = self::groupId($mbcGrp);
						$mbc->name       = self::groupName($mbcGrp);
						$mbc->pleasantId = $mightBeChildren['ZUORDNUNGSNR'];


						if (!empty($subCat->sw_id))
						{
							$mbc->parentId = $subCat->sw_id;
						}

						if (!empty($subCat->id) && ($subCat->id !== $mbc->id) && self::startsWith($mbc->id, $subCat->id))
						{
							if (!isset($subCat->children))
							{
								$subCat->children = [];
							}
							//echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|----- ' . $mbc->name . ' [' . $mbc->id . ']<br>';
							$subCat->children[] = $mbc;
							$mbc->sw_id         = $this->addCategoryToDB($mbc);
						}
					}
					$mainCat->children[] = $subCat;
				}
			}
			$categories[] = $mainCat;
			//echo '<br>';
		}
		var_dump($categories);
	}

	/**
	 * Group identifier string.
	 *
	 * This property holds a group identifier which can be used
	 * for categorization or grouping purposes within the application.
	 *
	 * @var string $grp
	 */
	private function groupId(string $grp): string
	{
		return substr($grp, 0, self::groupIdPos($grp));
	}

	/**
	 * Retrieve the position of the first space character in the given string.
	 *
	 * @param string $grp The string to search in.
	 *
	 * @return int The position of the first space character, or false if not found.
	 */
	private function groupIdPos(string $grp): int
	{
		return strpos($grp, ' ');
	}

	/**
	 * Extracts and returns the group name from a given group identifier.
	 *
	 * @param string $grp The group identifier.
	 *
	 * @return string The extracted group name.
	 */
	private function groupName(string $grp): string
	{
		return substr($grp, self::groupIdPos($grp) + 1);
	}

	/**
	 * Add or update a category in the database by checking its existence.
	 *
	 * @param object $category The category object containing the necessary data.
	 *
	 * @return string The ID of the newly added or updated category.
	 *
	 * @throws GuzzleException
	 */

	protected function addCategoryToDB(object $category): string
	{
		$client   = new pleasantClient();
		$existing = $client->getCategoryByPleasantInternalId($category->pleasantId);

		$dbArray = [
			'name'         => $category->name,
			'customFields' => [
				'category_pleasant_id'          => $category->id,
				'category_pleasant_internal_id' => $category->pleasantId
			]
		];

		if (!empty($category->parentId))
		{
			$dbArray['parentId'] = $category->parentId;
		}

		if (0 == $existing->total)
		{
			//id of new category
			return $client->addCategory($dbArray);
		}
		else
		{
			$dbArray['id'] = $existing->data[0]->id;

			return $client->updateCategory($dbArray);
		}

		//id of existing category
	}

	/**
	 * Test if a string starts with another string
	 *
	 * @param string $str
	 * @param string $query
	 *
	 * @return bool
	 * @todo replace with native function in PHP8
	 *
	 */
	private function startsWith(string $str, string $query): bool
	{
		return substr($str, 0, strlen($query)) === $query;
	}

	/**
	 * Retrieves articles from the database, processes them, and adds them to an external system if not already added.
	 *
	 * @param int|null $limit The maximum number of articles to process. Defaults to -1 (no limit).
	 *
	 * @throws GuzzleException If there is an issue while adding the product to the external system.
	 * @throws JsonException   If there is an issue decoding or encoding the JSON data.
	 */

	public function getArticles(?int $limit = -1)
	{

		$sql = 'SELECT * FROM V$hobaIT_webartikel';

		self::setCategoriesArray();
		self::setBasePriceArray();

		$count         = 0;
		$articles      = self::query($sql);
		$addedProducts = json_decode(file_get_contents('added_products.json'));


		foreach ($articles as $item)
		{
			if ($limit > 0)
			{
				$count++;
				if ($count > $limit)
				{
					break;
				}
			}

			if (!in_array($item['ARTIKELNR'], $addedProducts))
			{
				$client     = new pleasantClient();
				$categories = self::getMatchingCategoryIdsByString($item['all_groups']);
				$product    = self::assembleProduct($item, $categories);


				if ($client->addProduct($product, true) !== null)
				{
					$addedProducts[] = $product->getProductNumber();
					file_put_contents('added_products.json', json_encode($addedProducts));
				}
			}
			else
			{
				echo 'Skipping ' . $item['ARTIKELNR'] . "\n";
			}
			//echo $product->toHtml();
		}
	}

	/**
	 * Fetches and processes other articles with a specified limit.
	 *
	 * This method retrieves articles from the database, processes them
	 * by matching categories and assembling products, and then attempts
	 * to add the products via a client. Products already added will be skipped.
	 *
	 * @param int|null $limit The maximum number of articles to process. Use -1 for no limit.
	 *
	 * @throws GuzzleException
	 */
	public function getOtherArticles(?int $limit = -1)
	{

		$sql = 'SELECT * FROM V$hobaIT_all_articles';

		self::setCategoriesArray();
		self::setBasePriceArray();

		$count         = 0;
		$articles      = self::query($sql);
		$addedProducts = json_decode(file_get_contents('added_products.json'));


		foreach ($articles as $item)
		{
			if ($limit > 0)
			{
				$count++;
				if ($count > $limit)
				{
					break;
				}
			}

			if (!in_array($item['ARTIKELNR'], $addedProducts))
			{
				$client     = new pleasantClient();
				$categories = self::getMatchingCategoryIdsByString($item['all_groups']);
				$product    = self::assembleProduct($item, $categories);

				$product->setActive(false);
				if ($client->addProduct($product, true) !== null)
				{
					$addedProducts[] = $product->getProductNumber();
					file_put_contents('added_products.json', json_encode($addedProducts));
				}
			}
			else
			{
				echo 'Skipping ' . $item['ARTIKELNR'] . "\n";
			}
			//echo $product->toHtml();
		}
	}

	/**
	 * Fetches and processes articles with detailed information.
	 *
	 * Executes a SQL query to retrieve articles, processes them,
	 * and prepares them for further operations while observing
	 * an optional limit.
	 *
	 * @param int|null $limit Limit for the number of articles to fetch.
	 *
	 * @throws GuzzleException If an error occurs during HTTP requests.
	 */

	public function getArticlesFull(?int $limit = -1): void
	{

		$sql = 'SELECT * FROM V$hobaIT_webartikel_full';

		self::setCategoriesArray();
		self::setBasePriceArray();

		$count         = 0;
		$articles      = self::query($sql);
		$addedProducts = json_decode(file_get_contents('added_products_full.json'));

		foreach ($articles as $item)
		{
			if ($limit > 0)
			{
				$count++;
				if ($count > $limit)
				{
					break;
				}
			}

			if (!in_array($item['ARTIKELNR'], $addedProducts))
			{
				$client  = new pleasantClient();
				$product = self::assembleProduct($item);


//				if ($client->addProduct($product, true))
//				{
//					$addedProducts[] = $product->getProductNumber();
//					file_put_contents('added_products_full.json', json_encode($addedProducts));
//				}
			}
			//echo $product->toHtml();
		}
	}

	/**
	 * Retrieves manufacturer data for a given product number.
	 *
	 * @param string $productNumber The product number to fetch manufacturer data for.
	 *
	 * @return string Manufacturer data retrieved from the database.
	 *
	 * @throws Exception
	 */
	public function getManufacturerData(string $productNumber): array
	{
		$sql    = 'SELECT NAME1, NAME2,STRASSE, PLZ, ORT, LANDBEZ, Internet_Kontakt FROM V$_FGH_Hersteller WHERE ARTIKELNR = \''.$productNumber.'\'';
		return  self::query($sql);
	}


	/**
	 * Assemble a product instance with data provided from an array.
	 *
	 * @param array      $item       Product data array.
	 * @param array|null $categories Optional categories for the product.
	 *
	 * @return pleasantProduct
	 *
	 * @throws GuzzleException
	 * @throws Exception
	 */
	protected function assembleProduct(array $item, ?array $categories = null): pleasantProduct
	{
		$product = new pleasantProduct();

		$product->setPrice(floatval($item['LISTENPREIS']));
		$product->setProductNumber($item['ARTIKELNR']);
		$product->customFields->setAttribute(self::getHazards($item['FIELD15']), 'custom_product_hazards');
		$product->customFields->setCustomProductHazardStatements($item['FIELD16']);
		$product->customFields->setCustomProductHazardsP($item['FIELD18']);
		$product->customFields->setAttribute(self::getManufacturerData($item['ARTIKELNR']), 'custom_product_manufacturer');
		$product->customFields->setCustomProductAdrNumbers($item['FIELD11']);
		$product->customFields->setCustomProductAdrSpecs($item['FIELD12']);
		$product->customFields->setCustomProductAdrText($item['FIELD13']);
		$product->customFields->setDatasheets(self::getLinkedDatasheets($item['ARTIKELNR']));
		$product->customFields->setLastUpdate(date('Y-m-d H:i:s'));
		$packunit = $item['mengeneinheit_voll'];

		if (!empty($packunit) && !empty($item['MENGENEINHEIT']))
		{
			$product->setPackUnit($packunit);
			$product->setStock($this->getStocksByProduct($product->getProductNumber(), $item['MENGENEINHEIT']));
		}

		if ($item['PREISEINHEIT'] != 1)
		{
			$product->customFields->setCustomProductPackUnitQuantity($item['PREISEINHEIT']);
		}
		//$product->customFields->datasheets = self::getLinkedDatasheets($product->productNumber);

		$product->setImage($item['PICTURE']);

		$names = $item['KURZTEXT'];
		if (empty($names))
		{
			$names = 'Produkt ohne KURZTEXT';
		}
		$names = self::getProductName($names);

		if ($names['available'] != true)
		{
			$product->setIsCloseout(true);
			$product->setStock(0);
		}
		else
		{
			if (!empty($categories))
			{
				$product->setCategories($categories);
			}
		}

		$product->setName(mb_convert_encoding($names['name'], 'UTF-8', 'UTF-8'));
		$product->customFields->setCustomProductShortDesc($names['short_desc']);
		$product->customFields->setCustomProductInformation($names['info']);
		$product->customFields->setCustomProductRowId($item['ROWID']);
		$product->setPicto($item['piktogramme'] ?? null);

		$description = self::prepareContent($item['BESCHREIBUNG']);
		$product->setDescription($description);

		if (!empty($item['BASICPRICEUOQ']))
		{
			$unitName = $item['BASICPRICEUOQ'];
			$product->setUnit(self::getUnitId($unitName), $unitName);
			$product->setPurchaseUnit(floatval(@$this->baseprices[$product->getProductNumber()] ?? 1));
		}


		return $product;
	}

	/**
	 * Retrieves the product name and associated details.
	 *
	 * Processes a string of product names, determines their availability, and
	 * extracts product information including name, short description, and additional info.
	 * Handles special cases like "Auslaufprodukte" and restructures data accordingly.
	 *
	 * @param string $names Product names as a string, split by newline.
	 *
	 * @return array Returns an associative array containing:
	 *               - 'name' (string): The product name (max 254 characters).
	 *               - 'short_desc' (string): A short description or additional detail.
	 *               - 'info' (string): Additional product information if available.
	 *               - 'available' (bool): Indicates the availability of the product.
	 */
	protected function getProductName(string $names): array
	{
		$titles = explode("\n", $names);
		$avail  = true;
//		var_dump($names);

		// Check for availability in the entire string first
		if (str_contains($names, '>>'))
		{
			$avail = false;
		}

		for ($i = 0; $i < sizeof($titles); $i++)
		{
			$start = substr($titles[$i], 0, 2);
			if ($start != '>>' && $start != '++')
			{
				break;
			}
		}

		if (empty($titles[$i]))
		{
			$titles[$i] = $names;
			echo "+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n";
			echo "CHECK PRODUCT $names \n";
			echo "+++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n";
		}
		//handle "Auslaufprodukte"
		if (strstr($titles[$i], '<<<'))
		{
			$tmp            = str_replace('>>>', '', str_replace('<<<', '', $titles[$i]));
			$titles[$i]     = $titles[$i + 1];
			$titles[$i + 1] = $tmp;
		}

		return [
			'name'       => substr($titles[$i], 0, 254),
			'short_desc' => $titles[$i + 1] ?? '',
			'info'       => $titles[$i + 2] ?? '',
			'available'  => $avail
		];
	}

	/**
	 * Sets the categories array for faster access if not already set.
	 *
	 * @return void
	 *
	 * @throws GuzzleException
	 */
	protected function setCategoriesArray(): void
	{
		if (empty($this->categories))
		{
			$this->categories = (new pleasantClient())->getCategoriesArray();
		}
	}

	/**
	 * Sets the base prices as a flattened array for quicker access.
	 *
	 * Fetches data from the database using a SQL query.
	 *
	 * @throws \Exception
	 */
	protected function setBasePriceArray()
	{
		$bpSQL      = <<<SQL
				SELECT 
			        v.ARTIKELNR, v.UNITOFQUANTITYFACTOR 
				FROM 
			        dbo.ARTIKEL AS a 
				INNER JOIN 
					dbo.ARTIKELVERPACKUNGSZUORD AS v ON (a.ARTIKELNR = v.ARTIKELNR) AND (v.NAME = a.BASICPRICEUOQ)
SQL;
		$basePrices = self::query($bpSQL);
		//flatten to array for quick use
		$this->baseprices = [];

		foreach ($basePrices as $bp)
		{
			$this->baseprices[$bp['ARTIKELNR']] = $bp['UNITOFQUANTITYFACTOR'];
		}
	}

	/**
	 * Prepares and formats the content string by replacing specific placeholders,
	 * converting RTF to plain text, and parsing Markdown to HTML.
	 *
	 * @param string|null $content The content to prepare. If null or empty, an empty string will be used.
	 *
	 * @return string The prepared and formatted content.
	 *
	 * @throws Exception If there is an issue with RTF formatting.
	 */
	protected function prepareContent(?string $content): string
	{
		if (empty($content))
		{
			$content = '';
		}
		$content = str_replace('<<<Auslaufprodukt>>>', '<div class="alert alert-warning">Auslaufprodukt</div>', $content);
		if (strstr($content, "\\rtf"))
		{
			$content   = new RtfHtmlPhp\Document($content);
			$formatter = new RtfHtmlPhp\Html\HtmlFormatter();
			$content   = strip_tags($formatter->Format($content));
		}
		$Parsedown = new \hobaIT\Parsedown();
		$Parsedown->setBreaksEnabled(true);
		$content = $Parsedown->text($content);

//		$content = nl2br($content);
		return $content;
	}

	/**
	 * Retrieves an array of hazards from a space-separated string.
	 *
	 * @param string|null $hazards A space-separated string of hazards.
	 *
	 * @return array|null Returns an array of hazards if the input string is not empty, otherwise null.
	 */
	public function getHazards(?string $hazards): ?array
	{
		if (!empty($hazards))
		{
			return explode(' ', $hazards);
		}

		return null;
	}

	/**
	 * Retrieves the datasheet name by replacing codes in the description with their corresponding values.
	 *
	 * @param string|null $description The description to be processed.
	 *
	 * @return string The processed datasheet name or an empty string if the description is empty.
	 */
	protected function getDatasheetName(?string $description): string
	{
		if (empty($description))
		{
			return '';
		}

		$types = [
			'TM'  => 'Produktinformation',
			'BA'  => 'Betriebsanweisung',
			'SDB' => 'Sicherheitsdatenblatt',
			'RKI' => 'RKI',
			'VAH' => 'VAH',
			'Öko' => 'Öko'
		];

		return str_replace(array_keys($types), array_values($types), $description);
	}

	/**
	 * Retrieves linked datasheets for a specific article number.
	 *
	 * Constructs the file paths of datasheets associated with the given article number
	 * and maps them to their respective names based on their description.
	 *
	 * @param string $articleNumber The article number for which datasheets are retrieved.
	 * @param string $path          The base path used to construct the full file paths of the datasheets.
	 *
	 * @return array An associative array where keys are the full file paths of the datasheets,
	 *               and values are their corresponding names.
	 * @throws Exception
	 */
	protected function getLinkedDatasheets(string $articleNumber, string $path = '/artikelbilder/Artikel/'): array
	{
		$this->setDatasheetArray();
		$files = [];

		if (!empty($this->datasheets[$articleNumber]))
		{
			foreach ($this->datasheets[$articleNumber] as $item)
			{
				$fullPath         = $path . $articleNumber . '/' . $item['FILENAME'];
				$files[$fullPath] = self::getDatasheetName($item['DESCRIPTION']);
			}
		}

		return $files;
	}

	/**
	 * Populate an array with datasheets for optimized access.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function setDatasheetArray(): void
	{
		if (empty($this->datasheets))
		{
			$sql  = <<<SQL
			SELECT 
			    ARTIKELNR, U_DOCUMENTNUMBER, CREATIONDATE, DESCRIPTION, FILENAME, MODIFICATIONDATE
			FROM 
			     FOREIGNARTDOCS			
SQL;
			$docs = self::query($sql);
			foreach ($docs as $doc)
			{
				if (!isset($this->datasheets[$doc['ARTIKELNR']]))
				{
					$this->datasheets[$doc['ARTIKELNR']] = [];
				}
				$this->datasheets[$doc['ARTIKELNR']][] = $doc;
			}
		}
	}


	/**
	 * Fetch and set available units from the client.
	 *
	 * @throws GuzzleException
	 */
	protected function setUnits()
	{
		$client      = new pleasantClient();
		$this->units = $client->getAvailableUnits();
	}

	/**
	 * Retrieve the unit ID based on the given name.
	 * If the unit does not exist, it will be created and stored.
	 *
	 * @param string $name Name of the unit.
	 *
	 * @return string Unit ID.
	 *
	 * @throws GuzzleException
	 */
	protected function getUnitId(string $name): string
	{
		if (empty($this->units))
		{
			$this->setUnits();
		}

		if (array_key_exists($name, $this->units))
		{
			return $this->units[$name];
		}
		else
		{
			$client = new pleasantClient();
			$id     = $client->addUnit($name);;
			$this->units[$name] = $id;

			return $id;
		}
	}

	/**
	 * Converts a customer address array into an address object.
	 *
	 * @param array $customerAddress The customer address data.
	 *
	 * @return \hobaIT\address The address object populated with the provided data.
	 *
	 * @throws GuzzleException
	 */
	public function arrayToAddress($customerAddress): \hobaIT\address
	{

		$name    = self::getNamesFromString($customerAddress['name']);
		$country = $customerAddress['land'];
		if ($country == 'D' || empty($country))
		{
			$country = '51118f259dd741a2b10e30e49d748528';
		}
		else if ($country == 'A' || $country == 'AT')
		{
			$country = 'b606df6c7ff644a7a545e991dff13782';
		}
		else if ($country == 'I' || $country == 'IT')
		{
			$country = '2ff909879362484396662ff38f7cdfdf';
		}
		else
		{
			$client  = new pleasantClient();
			$country = $client->getCountryIdByIso($customerAddress['land']);
		}

		$company = self::getCompanyName($customerAddress['anrede'], $customerAddress['name1'], $customerAddress['name2']);

		$address = new \hobaIT\address();
		$address->setSalutationId(self::guessSalutation($customerAddress['name'])); //id == not defined
		$address->setCompany($company);
		$address->setZipcode($customerAddress['plz']);
		$address->setCity($customerAddress['ort']);
		$address->setStreet($customerAddress['strassepostfach'] ?? 'unbekannt');
		$address->setPhoneNumber($customerAddress['telephonnr']);
		$address->setFirstName($name['first']);
		$address->setLastName($name['last']);
		$address->setCountryId($country);
		$address->setIdFromPleasant($customerAddress['id']);
		$address->customFields->setCustomPleasantSalutationtext($customerAddress['anredetitel']);
		$address->customFields->setCustomPleasantName($customerAddress['name']);

		return $address;
	}


	/**
	 * Adds customers to the system.
	 * This function handles the following:
	 * - Retrieves and processes customer data from the database.
	 * - Groups customer addresses and identifies unique customer records.
	 * - Creates customer objects with associated addresses, groups, and rules.
	 * - Logs errors for customers with missing details, such as email addresses.
	 * - Prepares and executes bulk actions using a pleasantClient for groups, rules, and customer entities.
	 * - Skips processing of customers with improper details or missing representative data.
	 *
	 * @throws Exception If operations with the pleasantClient fail.
	 * @todo fix error with empty first names
	 */
	public function addCustomers()
	{

//		$mysql = new MySQLDBInterface;
//		$mysql->updateHistoricOrders();

		$this->getRepresentatives();
		$client = new \hobaIT\pleasantClient();


//		$customerIds = $client->getCustomerIdArray();
//		foreach (array_chunk($customerIds, 50) as $item)
//		{
//			$client      = new \hobaIT\pleasantClient();
//			$client->bulkDelete(array_values($item), 'customer');
//			var_dump($item);
//		}
//		die();

		$sql            = 'SELECT * FROM V$hobaIT_webcustomers';
		$result         = self::query($sql);
		$customerArray  = [];
		$customersToAdd = [];
		$groups         = [];
		$rules          = [];
		$errorflag      = false;

		foreach ($result as $res)
		{
			$customerArray[$res['personennr'] . '-' . $res['ansprechpartnernr']][] = $res;
		}


		foreach ($customerArray as $customerAdresses)
		{
			$contact = $this->representatives[$customerAdresses[0]['personennr']] ?? new stdClass();

			if (empty($contact->name) || substr($contact->name, 0, 1) == ' ')
			{
				//echo "skipping (irrelevant): " . $customerAdresses[0]['personennr'] . "\n";
				continue;
			}

			$i        = 0;
			$customer = new \hobaIT\customer();

			foreach ($customerAdresses as $addressArray)
			{
				$address = self::arrayToAddress($addressArray);
				if ($i++ < 1)
				{
					$customer->setFirstName($address->getFirstName());
					$customer->setLastName($address->getLastName());
					$customer->setCompany($address->getCompany());
					$customer->setPersonennr($addressArray['personennr']);
					$customer->setIdFromPleasant($addressArray['ap_id']);

					if (!empty($contact))
					{
						$customer->setContact(json_encode($contact));
					}
					else
					{
						unset($customer->customFields->custom_customer_contact);
					}
					$email = self::getValidEmail($customerAdresses);
					if (!empty($email))
					{
						$customer->setEmail($email);
					}
					else
					{
						self::log("ERROR: Kunde (" . $customer->getPersonennr() . ") " . $customerAdresses[0]['internalnumber'] . " konnte nicht hinzugefügt werden, E-Mail fehlt\n");
						$errorflag = true; //break out of upper loop too
						continue;
					}
					$customer->setCustomerNumber($addressArray['internalnumber']);
					$customer->customFields->setCustomCustomerLoginId($customer->getCustomerNumber() . '-' . $customer->getEmail());
					$customer->customFields->setCustomCustomerEgIdent($addressArray['EG_IDENTNR']);
					$customer->customFields->setCustomCustomerField04($addressArray['Field04']);

					$customer->setDefaultAddresses($address);
					$customer->setSalutationId(self::guessSalutation($addressArray['internalnumber'])); //id == not defined

					if (!empty($addressArray['kundengruppe']) && $addressArray['kundengruppe'] != ' ')
					{

						$cgroup = new \hobaIT\customergroup($addressArray['kundengruppe'], $addressArray['zuordnungsnr']);
						$gName  = 'Kundengruppe:' . $addressArray['kundengruppe'];

						$cgroup->setId(md5($gName));

						$groups[$addressArray['internalnumber']] = $cgroup;
						$groupId                                 = $cgroup->getId();
						$customer->setGroupId($groupId);
						//prepare rule
						$gRule = $client->prepareRule('customerCustomerGroup', $groupId, $gName, '', $groupId, 'customerGroupIds');
						if (!empty($gRule))
						{
							//rule does not exist, if anything else than null is returned
							$rules[$addressArray['g_id']] = $gRule;
						}
					}
					$customer->password = 'bysf4ykBwwiJBtS6';

					$customerRuleId = \hobaIT\rule::convertIdFromPleasant($addressArray['k_id']);
					$cRule          = $client->prepareRule('customerCustomerNumber', $customer->getCustomerNumber(), 'Kunde: (' . $customer->getPersonennr() . ') :' . $customer->getCustomerNumber(), $customer->getPersonennr(), $customerRuleId);
					if (!empty($cRule))
					{
						//rule does not exist, if anything else than null is returned
						$rules[$addressArray['g_id']] = $cRule;
					}

				}
				else
				{
					$customer->addresses[] = $address;
				}
			}

			if ($errorflag == true)
			{
				$errorflag = false;
				continue;
			}

			if (empty($customer->addresses))
			{
				$customer->addresses[] = $address;
			}

			$customersToAdd[] = $customer;

			if (sizeof($customersToAdd) > 50)
			{
				$client = new \hobaIT\pleasantClient();
				if (!empty($groups)) $client->bulkAction('customer_group', array_values($groups));
				if (!empty($rules)) $client->bulkAction('rule', array_values($rules));
				if (!empty($customersToAdd)) $client->bulkAction('customer', $customersToAdd);
				$customersToAdd = $rules = $groups = [];
			}
		}
		$client = new \hobaIT\pleasantClient();
		if (!empty($groups)) $client->bulkAction('customer_group', array_values($groups));
		if (!empty($rules)) $client->bulkAction('rule', array_values($rules));
		if (!empty($customersToAdd)) $client->bulkAction('customer', $customersToAdd);
	}

	/**
	 * Retrieve customer data based on provided customer numbers or fetch all customers if none are specified.
	 *
	 * @param array|null $customerNumbers Array of customer numbers to filter the customers.
	 *
	 * @return array|object The list of customers or a single customer object.
	 *
	 * @throws GuzzleException
	 */
	public function getCustomers(?array $customerNumbers = null): array|object
	{
		$client = new pleasantClient();

		return $client->getCustomers($customerNumbers);
	}

	/**
	 * Fetch and populate representatives data
	 *
	 * Executes a SQL query to retrieve representative details and organizes them
	 * in a structured way, categorizing details such as name, image, telephone,
	 * and email depending on the 'BEZEICHNUNG' column.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function getRepresentatives()
	{
		$sql    = 'SELECT * FROM V$hobaIT_vertreter_komplett';
		$result = self::query($sql);
		foreach ($result as $rep)
		{
			if (empty($this->representatives[$rep['PERSONENNR']]))
			{
				$this->representatives[$rep['PERSONENNR']] = new \stdClass();
			}
			if ($rep['BEZEICHNUNG'] == 'Vertrete')
			{
				$this->representatives[$rep['PERSONENNR']]->name  = $rep['GRUPPENZUORDNUNG'];
				$this->representatives[$rep['PERSONENNR']]->image = $rep['BILD'];
			}
			else if ($rep['BEZEICHNUNG'] == 'Vertret1')
			{
				$this->representatives[$rep['PERSONENNR']]->tel = $rep['GRUPPENZUORDNUNG'];
			}
			else if ($rep['BEZEICHNUNG'] == 'Vertret2')
			{
				$this->representatives[$rep['PERSONENNR']]->mail = $rep['GRUPPENZUORDNUNG'];
			}
		}
	}

	/**
	 * Validates if the provided email address is in a valid format.
	 *
	 * @param string $email The email address to validate.
	 *
	 * @return bool Returns true if the email is valid, otherwise false.
	 */
	public function isValidEmail($email)
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL, ['flags' => FILTER_FLAG_EMAIL_UNICODE]) !== false;
	}

	/**
	 * Retrieve the first valid email address from the provided list.
	 *
	 * @param array $addresses List of email address data.
	 *
	 * @return string|null The first valid email address or null if none are valid.
	 */
	public function getValidEmail(array $addresses)
	{
		foreach ($addresses as $address)
		{
			$email = $address['email'] ?? null;
			if (self::isValidEmail($email))
			{
				return $email;
			}
		}

		return null;
	}

	/**
	 * A string to be searched within a haystack.
	 *
	 * @param string $needle The specific string to search for.
	 */
	public function alreadyAdded(string $needle, array $array): bool
	{
		foreach ($array as $value)
		{
			if ($value->id == $needle)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Extracts and sanitizes first and last names from a provided string.
	 *
	 * @param string|null $name The full name as a string, where the format can be "last,first" or "first last".
	 *
	 * @return array An associative array with keys 'first' and 'last', representing the extracted names.
	 */
	public function getNamesFromString(?string $name): array
	{
		if (empty($name))
		{
			return ['first' => 'unbekannt', 'last' => 'unbekannt'];
		}

		$names = [];
		if (strstr($name, ','))
		{
			$tmp            = explode(',', $name);
			$names['last']  = $tmp[0];
			$names['first'] = $tmp[1];
		}
		else
		{
			$pos            = strrpos($name, ' ') + 1;
			$names['last']  = substr($name, $pos);
			$names['first'] = substr($name, 0, $pos);
		}
		$names['last']  = self::sanitizeName($names['last']);
		$names['first'] = self::sanitizeName($names['first']);

		return $names;

	}

	/**
	 * Sanitizes a given name by removing specified prefixes and trimming spaces.
	 *
	 * @param string $name The name to be sanitized.
	 *
	 * @return string The sanitized name.
	 */
	public function sanitizeName(string $name): string
	{
		$search  = ['Frau ', 'Herr ', 'Firma '];
		$replace = [];
		foreach ($search as $s)
		{
			$replace[] = '';
		}

		$name = str_replace($search, $replace, $name);

		//remove preceding space
		if (substr($name, 0, 1) === ' ')
		{
			$name = substr($name, 1);
		}

		//remove trailing space
		if (substr($name, -1, 1) === ' ')
		{
			$name = substr($name, 0, -1);
		}

		return $name;
	}

	/**
	 * Guess the salutation based on the given name.
	 *
	 * @param string|null $name The name to determine the salutation for.
	 *
	 * @return string The guessed salutation or a default value if unable to determine.
	 *
	 * @throws GuzzleException
	 */
	public function guessSalutation(?string $name): string
	{
		if (empty($name))
		{
			return '1320a4a7a21e4dd9a3f369f1c2a23104';
		}

		if (empty($this->salutations))
		{
			$client            = new pleasantClient();
			$this->salutations = $client->getSalutationsArray();
		}

		return $this->salutations[explode(' ', $name)[0]] ?? '1320a4a7a21e4dd9a3f369f1c2a23104';
	}

	/**
	 * Get the full company name based on provided details.
	 *
	 * @param string|null $anrede Prefix or salutation for the company name.
	 * @param string|null $name1  First part of the company name.
	 * @param string|null $name2  Second part of the company name.
	 *
	 * @return string|null Returns the constructed company name or an empty string/null based on logic.
	 */
	public function getCompanyName(?string $anrede = '', ?string $name1 = '', ?string $name2 = ''): ?string
	{
		if ($anrede === null || ('>' == substr($anrede, 0, 1)))
		{
			return '';
		}

		if ('An' == $anrede)
		{
			$anrede = '';
		}
		else
		{
			return $anrede . ' ' . $name1 . ' ' . $name2;
		}

		return $name1 . ' ' . $name2;
	}

	/**
	 * Retrieve the gender identifier based on the given name.
	 *
	 * @param string $name The name to analyze for gender determination.
	 *
	 * @return string|null The gender identifier if found, otherwise null.
	 */
	public function getGenderFromName(string $name): ?string
	{
		$name = explode(' ', strtolower($name))[0];
		if (strstr($name, 'herr '))
		{
			return '422b086a99d64e44be56c96817ec9cd4';
		}
		else if (strstr($name, 'frau '))
		{
			return '30df74c3e08f4f2ab0fda65ac1cf0f52';
		}

		return null;
	}

	/**
	 * Set price rules for a specific customer.
	 *
	 * This method fetches and processes price rules based on customer data
	 * and product conditions, creating rules for assigning product-specific pricing
	 * for the given customer. It handles data fetching, preparation, and API calls.
	 *
	 * @param string|int $customerNumber The unique identifier of the customer.
	 *
	 * @throws GuzzleException
	 * @throws Exception
	 */
	public function setPriceRulesForCustomer($customerNumber)
	{

		$client        = new pleasantClient();
		$productPrices = [];
		$rules         = [];

		$client::log('Start');

		$personenNr = 'SELECT  personennr FROM KUNDE WHERE internalnumber = ?';
		$personenNr = self::query($personenNr, [$customerNumber]);

		$sql     = "SELECT * FROM VERKAUFSKONDITIONEN as v LEFT JOIN ARTIKEL as a ON a.ARTIKELNR = v.ARTIKELNR WHERE personennr = ? AND a.MENGENEINHEIT = v.ABNAHMEEINHEIT ORDER BY  v.ARTIKELNR, v.ABNAHMEMENGE";
		$results = self::query($sql, [$personenNr[0]['personennr']]);
		$client::log('Got rules ' . date('H:i:s'));

		$ids         = array_column($results, 'ARTIKELNR');
		$allProducts = $client->getProductIdsByProductNumbers($ids);
		$client::log('Produkte gefunden');


		foreach ($results as $result)
		{
			$id = $allProducts[$result['ARTIKELNR']] ?? null;
			if (!empty($id))
			{
				if (empty($productPrices[$id]))
				{
					$productPrices[$id] = [];
				}
				$productPrices[$id][((int) $result['ABNAHMEMENGE']) + 1] = $result['NETTOPREIS'];
			}
		}
		$client::log('Preise verarbeitet');

		$ruleId = $client->addCustomerIndividualRuleCondition($customerNumber);
		$client::log('Regeln gelöscht ');
		$client->deleteAllExtendedPricesByRuleId($ruleId);

		foreach ($productPrices as $productId => $currentProduct)
		{
			krsort($currentProduct, SORT_NUMERIC);
			$lastMoq      = 0;
			$currentRules = [];
			foreach ($currentProduct as $moq => $price)
			{
				$currentRules[] = new productPriceRule($ruleId, $productId, $price, $moq, $lastMoq - 1);
				$lastMoq        = $moq;
			}
			$rules = array_merge($rules, array_reverse($currentRules));
		}
		$client::log('Neue Preise eingefügt ');
		$client->bulkAction('product_price', array_reverse($rules));
		$client::log('Ende ');

	}

	/**
	 * Retrieves historic orders from the database within the specified date range.
	 *  The method constructs and executes a SQL query with the specified date range, processes the result set,
	 *  and structures the data into a nested array containing order details and associated articles.
	 *
	 * @param string $minDate The minimum date for fetching orders in 'Ymd' format. Defaults to '20000101'.
	 * @param string $maxDate The maximum date for fetching orders in 'Ymd' format. Defaults to the current date if empty.
	 *
	 * @return array Returns an array of orders, each containing document number, articles, customer internal number, and document date.
	 *
	 * @throws Exception If any error occurs during query execution or processing.
	 *
	 */
	public function getHistoricOrders(string $minDate = '20000101', string $maxDate = '')
	{
//		$sql       = "SELECT dbo.GOODSELLINGDAYBOOK.DOCUMENTDATE, dbo.GOODSELLINGDAYBOOK.DOCUMENTNUMBER, dbo.GOODSELLINGDAYBOOK.ARTIKELNR, dbo.ARTIKELSPRACHENZUORDNUNG.KURZTEXT,
//                         CASE WHEN Goodsellingdaybook.Quantity <> 0 THEN (Goodsellingdaybook.Sellingpricedc + Goodsellingdaybook.Rabatt) / Goodsellingdaybook.Quantity ELSE '0.' END AS Expr1,
//                         100 * dbo.GOODSELLINGDAYBOOK.RABATT / (CASE Goodsellingdaybook.SELLINGPRICE + Goodsellingdaybook.RABATT WHEN 0 THEN null ELSE Goodsellingdaybook.SELLINGPRICE + Goodsellingdaybook.RABATT END)
//                         AS Expr2, CASE WHEN Goodsellingdaybook.Quantity <> 0 THEN Goodsellingdaybook.Sellingpricedc / Goodsellingdaybook.Quantity ELSE '0.' END AS SELLINGPRICESINGLE, dbo.GOODSELLINGDAYBOOK.QUANTITY,
//                         dbo.ARTIKEL.MENGENEINHEIT, dbo.GOODSELLINGDAYBOOK.SELLINGPRICEDC, dbo.GOODSELLINGDAYBOOK.DOSSIERNUMBER,
//                         dbo.GOODSELLINGDAYBOOK.PERSONENNR, dbo.GOODSELLINGDAYBOOK.PURCHASEPRICE, (SELECT TEXT FROM dbo.DOCUMENTTYPE WHERE DOCUMENTTYPE = dbo.GOODSELLINGDAYBOOK.DOCUMENTTYPE) AS TYPE, (SELECT INTERNALNUMBER FROM dbo.KUNDE WHERE PERSONENNR = dbo.GOODSELLINGDAYBOOK.PERSONENNR) as INTERNALNUMBER
//FROM            dbo.GOODSELLINGDAYBOOK INNER JOIN
//                         dbo.ARTIKELSPRACHENZUORDNUNG ON dbo.GOODSELLINGDAYBOOK.ARTIKELNR = dbo.ARTIKELSPRACHENZUORDNUNG.ARTIKELNR INNER JOIN
//                         dbo.ARTIKEL ON dbo.GOODSELLINGDAYBOOK.ARTIKELNR = dbo.ARTIKEL.ARTIKELNR
//WHERE        (dbo.GOODSELLINGDAYBOOK.TYPE = 'A') AND (dbo.ARTIKELSPRACHENZUORDNUNG.SPRACHENCODE = 'DEM') AND ((DOCUMENTTYPE = 10) OR (DOCUMENTTYPE = 05)) ORDER BY dbo.GOODSELLINGDAYBOOK.DOCUMENTNUMBER, dbo.GOODSELLINGDAYBOOK.ITEMKEY DESC";
//		$sql = "select /*+ first_rows */
//       		GOODSELLINGDAYBOOK.DOCUMENTDATE,(SELECT INTERNALNUMBER FROM dbo.KUNDE WHERE PERSONENNR = dbo.GOODSELLINGDAYBOOK.PERSONENNR) AS INTERNALNUMBER,
//       		GOODSELLINGDAYBOOK.ARTIKELNR,GOODDOCUMENTITEM.ITEMTEXT,GOODDOCUMENTITEM.LOTNUANCENRS,GOODDOCUMENTITEM.PRICEPERUNIT1,
//       GOODDOCUMENTITEM.DISCOUNTP1,GOODDOCUMENTITEM.DISCOUNTP2,GOODDOCUMENTITEM.NETSURCHARGE,GOODDOCUMENTITEM.PRICEPERUNIT,GOODDOCUMENTITEM.QUANTITY,
//       GOODDOCUMENTITEM.MENGENEINHEIT,GOODDOCUMENTITEM.NETSELLINGPRICE,GOODDOCUMENTITEM.DOCUMENTNUMBER,GOODDOCUMENTITEM.DOSSIERNUMBER,
//       GOODSELLINGDAYBOOK.PURCHASEPRICE,GOODSELLINGDAYBOOK.DOCUMENTTYPE,ARTIKEL.MENGENEINHEIT
//			FROM GOODSELLINGDAYBOOK,GOODDOCUMENTITEM,ARTIKELSPRACHENZUORDNUNG,ARTIKEL
//			WHERE ((GOODSELLINGDAYBOOK.DOCUMENTDATE>=?) AND (GoodSellingDayBook.Type ='A'
//			AND GoodSellingDayBook.ArtikelNr=ARTIKEL.ARTIKELNR
//			AND GoodSellingDayBook.ArtikelNr=ARTIKELSPRACHENZUORDNUNG.ARTIKELNR
//			AND ARTIKELSPRACHENZUORDNUNG.SPRACHENCODE='DEM'
//			AND GoodSellingDayBook.ITEMKEY=GOODDOCUMENTITEM.ITEMKEY
//			AND GoodSellingDayBook.DOCUMENTNUMBER=GOODDOCUMENTITEM.DOCUMENTNUMBER
//			AND GOODDOCUMENTITEM.DOCUMENTTYPE IN (10,06,05))) ORDER BY dbo.GOODSELLINGDAYBOOK.DOCUMENTNUMBER, dbo.GOODSELLINGDAYBOOK.ITEMKEY DESC";
		if (empty($maxDate))
		{
			$maxDate = date('Ymd');
		}
		$sql = "select /*+ first_rows */ 
       		GOODSELLINGDAYBOOK.DOCUMENTDATE,(SELECT INTERNALNUMBER FROM dbo.KUNDE WHERE PERSONENNR = dbo.GOODSELLINGDAYBOOK.PERSONENNR) AS INTERNALNUMBER,
       		GOODSELLINGDAYBOOK.ARTIKELNR,GOODDOCUMENTITEM.ITEMTEXT, GOODDOCUMENTITEM.PRICEPERUNIT1,GOODDOCUMENTITEM.DISCOUNTP1,GOODDOCUMENTITEM.DISCOUNTP2,GOODDOCUMENTITEM.NETSURCHARGE,GOODDOCUMENTITEM.PRICEPERUNIT,GOODDOCUMENTITEM.QUANTITY,
       GOODDOCUMENTITEM.MENGENEINHEIT,GOODDOCUMENTITEM.NETSELLINGPRICE,GOODDOCUMENTITEM.DOCUMENTNUMBER,GOODDOCUMENTITEM.DOSSIERNUMBER,
       GOODSELLINGDAYBOOK.PURCHASEPRICE,GOODSELLINGDAYBOOK.DOCUMENTTYPE,ARTIKEL.MENGENEINHEIT,ARTIKELSPRACHENZUORDNUNG.BESCHREIBUNG 
			FROM GOODSELLINGDAYBOOK,GOODDOCUMENTITEM,ARTIKELSPRACHENZUORDNUNG,ARTIKEL 
			WHERE ((GOODSELLINGDAYBOOK.DOCUMENTDATE>=?) AND (GOODSELLINGDAYBOOK.DOCUMENTDATE<=?)
			           AND (GoodSellingDayBook.Type ='A'
						AND GoodSellingDayBook.ArtikelNr=ARTIKEL.ARTIKELNR
						AND GoodSellingDayBook.ArtikelNr=ARTIKELSPRACHENZUORDNUNG.ARTIKELNR
						AND ARTIKELSPRACHENZUORDNUNG.SPRACHENCODE='DEM'
						AND GoodSellingDayBook.ITEMKEY=GOODDOCUMENTITEM.ITEMKEY
						AND GoodSellingDayBook.DOCUMENTNUMBER=GOODDOCUMENTITEM.DOCUMENTNUMBER
						AND GOODDOCUMENTITEM.DOCUMENTTYPE IN (10,06,05))
			    ) ORDER BY dbo.GOODSELLINGDAYBOOK.DOCUMENTNUMBER, dbo.GOODSELLINGDAYBOOK.ITEMKEY DESC";

		$orderList = self::query($sql, [$minDate, $maxDate]);

		$doc       = '';
		$articles  = [];
		$orders    = [];
		$total     = 0;
		$i         = 0;
		$formatter = new RtfHtmlPhp\Html\HtmlFormatter();

		foreach ($orderList as $order)
		{
			if ($order['INTERNALNUMBER'] == null)
			{
				continue;
			}
			if ($order['DOCUMENTNUMBER'] != $doc)
			{
				if ($i++ > 0)
				{
					//skip first empty insert
					$orders[] = [
						'DOCUMENTNUMBER' => $doc,
						'ARTICLES'       => json_encode($articles),
						'CUSTOMER'       => $personennr,
						'DATE'           => $date
					];
				}
				$doc        = $order['DOCUMENTNUMBER'];
				$articles   = [];
				$personennr = $order['INTERNALNUMBER'];
				$date       = date('Y-m-d', strtotime($order['DOCUMENTDATE']));
			}

			$name = $order['BESCHREIBUNG'];
			if (strstr($name, "\\rtf"))
			{
				$name = @new RtfHtmlPhp\Document($name);
				$name = @strip_tags(@$formatter->Format($name));
			}

			$item = new article(
				$order['ARTIKELNR'],
				floatval($order['QUANTITY']),
				$order['MENGENEINHEIT'],
				floatval($order['PRICEPERUNIT']),
				floatval($order['DISCOUNTP1']),
				floatval($order['DISCOUNTP2']),
				floatval($order['NETSURCHARGE']),
				$name
			);

			$articles[] = $item;
		}

		$orders[] = [
			'DOCUMENTNUMBER' => $doc,
			'ARTICLES'       => json_encode($articles),
			'CUSTOMER'       => $order['INTERNALNUMBER'],
			'DATE'           => $order['DOCUMENTDATE']
		];

		return $orders;
	}


	/**
	 * Updates the product database entries by retrieving data from specific sources and synchronizing the information.
	 *
	 * @param array $products List of product IDs to be updated.
	 *
	 * @throws Exception If an error occurs during database queries or product updates.
	 */
	public function updateProducts(array $products): void
	{
		self::setCategoriesArray();
		self::setBasePriceArray();
		
		$sql = "SELECT * FROM V\$hobaIT_webartikel WHERE ARTIKELNR IN ('" . implode("','", $products) . "')";

		$webProducts = self::query($sql);
		$result      = self::updateProductsFromProductArray($webProducts);


		if (LOG)
		{
			echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n";
			echo "FOLGENDE PRODUKT SIND NICHT IN WEBGRUPPE UND WERDEN DEAKTIVIERT\n";
			echo "++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++\n";
		}

		$sql           = "SELECT * FROM V\$hobaIT_all_articles WHERE ARTIKELNR IN ('" . implode("','", $products) . "') AND  ARTIKELNR NOT IN (SELECT ARTIKELNR FROM V\$hobaIT_webartikel)";
		$otherProducts = self::query($sql);
		self::updateProductsFromProductArray($otherProducts, false);

	}

	protected function updateProductsFromProductArray(array $products, ?bool $active = true, $resume = false)
	{

		if ($resume)
		{
			$tmp = json_decode(file_get_contents('tmp.json'));
			if (!empty($tmp))
			{
				$products = (array) $tmp;
			}
		}

		$updated = [];
		$errors  = [];

		while ($item = array_pop($products))
		{
			$artNr = $item['ARTIKELNR'];

			self::log("Produkt [" . $artNr . "] wird aktualisiert");
			$categories = self::getMatchingCategoryIdsByString($item['all_groups']);
			$product    = self::assembleProduct($item, $categories);

			$client = new pleasantClient();
			$product->setActive($active);

			if ($product->getPrice() == 0)
			{
				$product->setActive(false);
			}

			if (!empty($client->updateProduct($product)))
			{
				$updated[] = $product->getProductNumber();
			}
			else
			{
				$errors[] = $product->getProductNumber();
				self::log('Fehler beim Update von : ' . $product->getProductNumber(), true);
			}
			$client->addCategoryAssignments($client->getProductIdByProductNumber($product->getProductNumber()), $categories);

			if ($resume)
			{
				file_put_contents('tmp.json', json_encode($products));
			}
		}

		return [
			'updated' => $updated,
			'errors'  => $errors
		];
	}

	/**
	 * An array of IDs used for various operations within the application.
	 *
	 * @var array $ids
	 */
	public function getMatchingCategoryIdsByPleasantIds(array $ids): array
	{

		//@todo return only deepest category
		$match = [];
		self::setCategoriesArray();
		foreach ($ids as $id)
		{
			if (@!empty($this->categories[$id]))
			{
				$match[] = $this->categories[$id]['id'];
			}
		}
//		$max = 0;
//		foreach ($ids as $id)
//		{
//			if ((int) $id > (int) $max)
//			{
//				$max = $id;
//				if (@!empty($this->categories[$id]))
//				{
//					$match = [$this->categories[$id]['id']]; //keep interface compatibility
//				}
//			}
//		}

		return $match;
	}

	/**
	 * Get matching category IDs by a comma-separated string of IDs.
	 *
	 * This method takes a comma-separated string of IDs, splits it into an array,
	 * removes spaces in the input, sorts the array in descending order, and retrieves
	 * any matching category IDs associated with the IDs.
	 *
	 * @param string $ids Comma-separated string of IDs.
	 *
	 * @return array An array of matching category IDs.
	 *
	 * @throws Exception
	 */
	public function getMatchingCategoryIdsByString(string $ids): array
	{
		$pleasantIds = explode(',', str_replace(' ', '', $ids));
		rsort($pleasantIds);
		$ids = self::getMatchingCategoryIdsByPleasantIds($pleasantIds);

		return $ids;
	}

	/**
	 * Updates all products by invoking the update of all shop products.
	 *
	 * @return void
	 */
	public function updateAllProducts()
	{
		$this->updateAllShopProducts();
	}

	/**
	 * Updates all shop products by retrieving all product numbers and processing them.
	 *
	 * @throws Exception
	 */
	public function updateAllShopProducts()
	{
		$products = ((new MySQLDBInterface())->getAllProductNumbers());
		self::updateProducts($products);
	}


	/**
	 * Updates all Pleasant products by fetching article numbers and updating each product.
	 *
	 * @throws Exception
	 */
	public function updateAllPleasantProducts()
	{
		$sql    = 'SELECT ARTIKELNR FROM V$hobaIT_webartikel';
		$result = self::query($sql);
		foreach ($result as $res)
		{
			self::updateProducts([$res['ARTIKELNR']]);
		}
	}

	public function addDS()
	{

	}

	/**
	 * Deletes all products by invoking the respective client method.
	 *
	 * @throws GuzzleException
	 */
	public function deleteAllProducts()
	{
		$client   = new hobaIT\pleasantClient();
		$products = $client->deleteAllProducts();
	}

	/**
	 * Indicates whether the operation should be forced.
	 *
	 * @param bool|null $force Optional. Set to true to force the operation.
	 *                         If false or null, the operation will not be forced.
	 *
	 * @return void
	 */
	public function setProductStocks(?bool $force = false)
	{
		if (empty($this->stocks) || $force)
		{
			$sql    = 'SELECT * FROM V$hobaIT_stock';
			$result = self::query($sql);
			foreach ($result as $res)
			{
				$this->stocks[(string) $res['ARTIKELNR']][(string) $res['MENGENEINHEIT']] = $res['Quantity_all'];
			}
		}
	}

	/**
	 * Retrieve the stock quantity for a specific product and packaging.
	 *
	 * @param string $articlenumber The article number of the product.
	 * @param string $packaging     The packaging type of the product.
	 *
	 * @return int The stock quantity if available, or -9999 if not found.
	 *
	 * @throws Exception If setting product stocks fails.
	 */
	public function getStocksByProduct(string $articlenumber, string $packaging): int
	{
		$this->setProductStocks();

		if (isset($this->stocks[$articlenumber][$packaging]))
		{
			return (int) $this->stocks[$articlenumber][$packaging];
		}
		else return -9999;
	}

	/**
	 * Add customer groups to the system from the database and log the process.
	 *
	 * @throws GuzzleException
	 */
	public function addCustomerGroups()
	{
		$sql     = 'SELECT * FROM V$hobaIT_alle_kundengruppen';
		$results = self::query($sql);
		$client  = new pleasantClient();
		foreach ($results as $result)
		{
			$id = $client->addCustomerGroup(new \hobaIT\customergroup($result['NAME'], $result['ZUORDNUNGSNR']));
			self::log($result['NAME'] . ' wird hinzugefügt  --> ' . $id);
			self::addCustomerGroupRuleConditions();
		}
	}

	/**
	 * Set array for quicker access to customer groups
	 *
	 * @param bool|null $force
	 *
	 * @throws GuzzleException
	 */
	public function setCustomerGroups(?bool $force = false)
	{
		if (empty($this->customergroups) || $force)
		{
			$client  = new pleasantClient();
			$results = $client->getCustomerGroups();
			foreach ($results as $result)
			{
				$this->customergroups[$result->customFields->custom_group_zuordnungsnr] = $result->id;
			}
		}
	}

	/**
	 * Set customer array for quick access
	 *
	 * @param bool|null $force
	 *
	 * @throws GuzzleException
	 */
	public function setCustomers(?bool $force = false)
	{
		if (empty($this->customers) || $force)
		{
			$client  = new pleasantClient();
			$results = $client->getCustomers();
			foreach ($results as $result)
			{
				$this->customers[$result->customFields->custom_customer_personennr] = $result->id;
			}
		}
	}

	/**
	 * Add conditions to customer group rules.
	 *
	 * This method sets customer groups and loops through existing customer groups,
	 * adding individual rule conditions for each group.
	 *
	 * @throws GuzzleException
	 */
	public function addCustomerGroupRuleConditions()
	{
		self::setCustomerGroups(true);
		$client = new pleasantClient();

		foreach ($this->customergroups as $id => $customergroup)
		{
			$client->addCustomergroupIndividualRuleCondition($customergroup);
		}
	}

	/**
	 * Set product IDs for efficient access.
	 *
	 * @param bool $force
	 *
	 * @throws GuzzleException
	 */
	protected function setProductIds(bool $force = false): void
	{
		if (empty($this->productIds) || $force)
		{
			$client           = new pleasantClient();
			$this->productIds = $client->getProductIdArray();
		}
	}

	/**
	 * Set advanced prices for products.
	 *
	 * This method processes the given product numbers to generate and apply advanced
	 * pricing rules. It queries the database for pricing data, validates and transforms
	 * it to create price rules, and applies them based on customer and product group
	 * relationships.
	 *
	 * @param array $productNumbers Array of product numbers to set advanced prices for.
	 *
	 * @throws GuzzleException
	 * @throws \RuntimeException Thrown when database or API operations fail.
	 */
	public function setAdvancedPricesPerProduct(array $productNumbers)
	{
		self::setPleasantCustomerPriceRules();
		self::setPleasantCustomerGroupRules();
		self::setProductIds();


		$mysql  = new MySQLDBInterface();
		$client = new pleasantClient();

		$allPrices = $client->getProductIdArrayWithPrices($productNumbers);

		$sql = "SELECT 
				ROWID
				,ARTIKELNR
				,ABNAHMEMENGE
				,ABNAHMEEINHEIT
				,PERSONENNR
				,PGRELATIONSHIP
				,GUELTIGAB
				,GUELTIGBIS
				,LISTENPREIS
				,RABATT
				,RABATT2
				,NETTOPREIS
				,Min(NETTOPREIS) OVER (PARTITION BY Artikelnr, personennr, pgrelationship ORDER BY ARTIKELNR, ABNAHMEMENGE,PGRELATIONSHIP, PERSONENNR) as Nettopreis_aktuell
				from verkaufskonditionen where 
				(((CONVERT(varchar(8), GetDate(), 112)) between (case when GUELTIGAB=99999999 then 20000101 else GUELTIGAB end) and GUELTIGBIS)) --or (CONVERT(varchar(8), GetDate(), 112))<GUELTIGBIS) -- nur heute gültig
				and ARTIKELNR not like '#%'  and ARTIKELNR=? ";

		$productPrices = [];
		$count         = 0;

		$priceArray = [];


		foreach ($productNumbers as $pN)
		{
			$result    = self::query($sql, [$pN]);
			$productId = @$this->productIds[$result[0]['ARTIKELNR']] ?? null;
			$mysql->deleteProductPricesHard($productId);
			$client = new pleasantClient();

			if (empty($productId))
			{
				continue;
			}
			//		//$mysql->deleteProductPricesHard($productId);

			for ($i = 0; $i < count($result); $i++)
			{
				$item = $result[$i];
				$next = null;
				if (!empty($result[$i + 1]))
				{
					$next = $result[$i + 1];
				}

				$to            = null;
				$customerGroup = $item['PGRELATIONSHIP'];
				$price         = (float) $item['NETTOPREIS'];

				if ($price > $allPrices[$item['ARTIKELNR']]['price'])
				{
					//abort if price is higher than default price
					echo 'higher then default';
					continue;
				}

				$quantityStart = $item['ABNAHMEMENGE'];
				if ($quantityStart < 1)
				{
					$quantityStart = 1;
				}
				$quantityEnd = null;

				if (is_array($next))
				{
					if (
						($item['ARTIKELNR'] == $next['ARTIKELNR']) &&
						($item['ABNAHMEEINHEIT'] == $next['ABNAHMEEINHEIT']) &&
						($item['PGRELATIONSHIP'] == $next['PGRELATIONSHIP']) &&
						($item['PERSONENNR'] == $next['PERSONENNR'])
					)
					{
						$quantityEnd = ((int) $next['ABNAHMEMENGE']) - 1;
					}
				}


//				if ($item['GUELTIGBIS'] == '99999999')
//				{
//					continue;
//				}

				if ($item['GUELTIGBIS'] != '99999999')
				{
					//set date max date
					$to = date('Y-m-d', strtotime($item['GUELTIGBIS']));
				}

				if (!empty($customerGroup) && $customerGroup != ' ')
				{
					//set customer group prices
					$rule = $this->pleasantCustomergroupRules[$customerGroup]['ruleId'] ?? null;
					continue;
				}
				if (!empty($item['PERSONENNR']) && $item['PERSONENNR'] != ' ')
				{
					//customer individual prices
					$rule = $this->pleasantCustomerPriceRules[$item['PERSONENNR']] ?? null;
					continue;
				}

				if (empty($rule))
				{
					$rule = '8c8a6d0ccbfc4e1884146698e9eba9f1';
					//no rule found? --> customergroup not (yet) in 'webgruppe', therefore no rule to be found
//				continue;
				}

				if (empty($priceArray[$rule]))
				{
					$priceArray[$rule] = [];
				}
				$priceArray[$rule][$quantityStart] = [
					'ruleId'        => $rule,
					'productId'     => $productId,
					'price'         => $price,
					'quantityStart' => $quantityStart,
					'quantityEnd'   => $quantityEnd,
					'validUntil'    => $to
				];

//				$swId                              = $client->setPriceRule($rule, $productId, $price, $quantityStart);
				$productPrices[] = new productPriceRule($rule, $productId, $price, $quantityStart, $quantityEnd, $to);
			}


			if ($count++ > 50)
			{
				$result        = $client->addProductPricesBatch($productPrices);
				$productPrices = [];
				$count         = 0;
			}

		}
		$result = $client->addProductPricesBatch($productPrices);
	}


	/**
	 * Set advanced prices for a specific product based on provided product number.
	 * This method fetches pricing information from a database, processes rules
	 * and conditions, and applies them to products using the client and database interface.
	 *
	 * @param string $productNumber The unique identifier for the product.
	 *
	 * @return bool Returns false if the product number is empty or if no product ID is found.
	 *
	 * @throws GuzzleException
	 * @throws Exception
	 */
	public function setAdvancedPricesPerProductOld(string $productNumber)
	{
		if (empty($productNumber))
		{
			return false;
		}
		self::setPleasantCustomerPriceRules();
		self::setPleasantCustomerGroupRules();
		self::setProductIds();
		$mysql  = new MySQLDBInterface();
		$client = new pleasantClient();

		$sql  = "SELECT *, CAST(GUELTIGAB AS  INT), CAST(GUELTIGBIS AS INT) FROM dbo.VERKAUFSKONDITIONEN WHERE ARTIKELNR LIKE ? AND ((GUELTIGAB <= ? AND GUELTIGBIS >= ?) OR (GUELTIGAB = ? AND GUELTIGBIS >= ?)) ORDER BY ABNAHMEMENGE ASC";
		$date = (int) date('Ymd');

		$result    = self::query($sql, [$productNumber, $date, $date, 99999999, $date]);
		$productId = @$this->productIds[$result[0]['ARTIKELNR']] ?? null;

		if (empty($productId))
		{
			return false;
		}

		$product       = $client->getProduct($productId);
		$price_default = $product->price[0]->net;

		//get rid of conflicting prices
		//$client->deleteAllExtendedPrices($productId); //api request takes too much time, and this might be slow already, not cool!
		//$mysql->deleteProductPricesHard($productId);

		foreach ($result as $item)
		{
//			var_dump($item);

			$to = '99999999';
			if ($item['GUELTIGBIS'] == '99999999')
			{
				continue;
			}

			if ($item['GUELTIGBIS'] != '99999999')
			{
				$to = date('Y-m-d', strtotime($item['GUELTIGBIS']));
			}

			$customerGroup = $item['PGRELATIONSHIP'];
			$price         = (float) $item['NETTOPREIS'];
			$quantityStart = $item['ABNAHMEMENGE'] > 0 ? $item['ABNAHMEMENGE'] : 1;
			$swId          = '';

			if ($price >= $price_default)
			{
				//skip if price is same or higher as default, since shopware won't use the cheapest price
				continue;
			}

			if (!empty($customerGroup) && $customerGroup != ' ')
			{
				//set customer group prices
				$rule = $this->pleasantCustomergroupRules[$customerGroup]['ruleId'] ?? null;
			}
			if (!empty($item['PERSONENNR']) && $item['PERSONENNR'] != ' ')
			{
				//customer individual prices
				$rule = $this->pleasantCustomerPriceRules[$item['PERSONENNR']] ?? null;
			}

			if (empty($rule))
			{
				$rule = '4f25915067b3460a91f01ba704ff809c';
				//no rule found? --> customergroup not (yet) in 'webgruppe', therefore no rule to be found
//				continue;
			}

			if ($quantityStart > 1)
			{
				$ruleID = $client->setPriceRule($rule, $productId, $price, 1, $quantityStart - 1);
				if ($to !== '99999999')
				{
					$mysql->addTemporaryRuleCondition($item['ROWID'], $ruleID, $to);
				}
			}

			$swId = $client->setPriceRule($rule, $productId, $price, $quantityStart);
			if ($to !== '99999999')
			{
				$mysql->addTemporaryRuleCondition($item['ROWID'], $swId, $to);
			}

		}
	}

	/**
	 *
	 * public function addToPriceRuleTable(string $deleteAt,  string $swRuleId, string $pleasantRowId ){
	 * $db = new MySQLDBInterface();
	 * $db->query('INSERT INTO hobait_price_rule_history VALUES ?, ?, ?, ?', [$deleteAt, $swRuleId, $pleasantRowId]);
	 * }
	 */

	 /**
	 * Set array for way quicker access
	 *
	 * @param bool|null $force
	 *
	 * @throws GuzzleException
	 */
	public function setCustomerGroupRules(?bool $force = false)
	{
		if (empty($this->customergroupRules) || $force)
		{
			$client  = new pleasantClient();
			$results = $client->getCustomerGroupRules();
			foreach ($results as $result)
			{
				$this->customergroupRules[$result->value->customerGroupIds[0]] = $result->ruleId;
			}
		}
	}

	/**
	 * Sets the pleasant customer group rules by checking and updating the rules and customer groups
	 * as necessary. If rules are missing or outdated, they are re-fetched and updated.
	 *
	 * @param bool|null $force Force re-fetching and updating of customer group rules and groups.
	 *
	 * @throws GuzzleException
	 */
	public function setPleasantCustomerGroupRules(?bool $force = false)
	{
		if (empty($this->pleasantCustomergroupRules) || $force)
		{
			self::setCustomerGroupRules();
			self::setCustomerGroups();
			foreach ($this->customergroups as $key => $customergroup)
			{
				if (empty($this->customergroupRules[$customergroup]))
				{
					//if rule does not exist for some reason --> add rule and refresh hashlists
					$client = new \hobaIT\APIClient();
					$client->addCustomergroupIndividualRuleCondition($customergroup);
					self::setCustomerGroupRules(true);
					self::setCustomerGroups(true);
				}
				$this->pleasantCustomergroupRules[$key] = [
					'groupId' => $customergroup,
					'ruleId'  => $this->customergroupRules[$customergroup]
				];
			}
		}
	}


	/**
	 * Set array for easier access to Pleasant customer price rules.
	 *
	 * @param bool|null $force Determines whether the rules should be retrieved forcibly regardless of existing data.
	 *
	 * @throws GuzzleException When the API call fails.
	 */
	public function setPleasantCustomerPriceRules(?bool $force = false)
	{
		if (empty($this->pleasantCustomerPriceRules) || $force)
		{
			$client        = new pleasantClient();
			$customerRules = $client->getAllCustomerPriceRules();

			foreach ($customerRules as $rule)
			{
				$personennr = $rule->customFields->custom_rule_personennr ?? null;
				if (!empty($personennr))
				{
					$this->pleasantCustomerPriceRules[$personennr] = $rule->id;
				}
			}
		}
	}

	/**
	 * Synchronizes product data by fetching updates and deletions from the database
	 * and performing necessary actions through an external client.
	 *
	 * The method retrieves the last synchronization date from the database and
	 * queries for updated or deleted rows. Products marked for deletion are
	 * removed via the external client, while updated products have their
	 * information refreshed. After processing, the synchronization timestamp
	 * is updated in the database.
	 *
	 * @throws Exception
	 */
	public function syncProducts()
	{
		$allUpdates = [];

		$mysql = new MySQLDBInterface();
		$date  = $mysql->getLastSync('products');
		$date  = date('d.m.Y H:i:s', strtotime($date));
		echo "Letzte Synchronisierung: $date \n";

		$sql        = 'SELECT * FROM V$hobaIT_sync_articles WHERE LASTACTIONDATE > ?';
		$updates    = self::query($sql, [$date]);
		$allUpdates = array_merge($updates, $allUpdates);

		$sql        = 'SELECT * FROM V$hobaIT_sync_article_language WHERE LASTACTIONDATE > ?';
		$updates    = self::query($sql, [$date]);
		$allUpdates = array_merge($updates, $allUpdates);

		$toUpdate = [];
		$toDelete = [];

		foreach ($allUpdates as $product)
		{
			$artNr = $product['ARTIKELNR'];
			if ($product['DELETED'] == '1')
			{
				$toDelete[$artNr] = 1;
			}
			else
			{
				$toUpdate[$artNr] = 1;
			}
		}

		$client      = new pleasantClient();
		$toDeleteIds = $client->getProductIdsByProductNumbers(array_keys($toDelete));
		echo 'Produkte zu löschen ' . count($toDelete) . "\n";

		if (!empty($toDeleteIds))
		{
			foreach ($toDeleteIds as $number => $item)
			{
				$client = new pleasantClient();
				echo "Lösche: $item ($number) \n";
				$client->deleteProduct($item);

			}
		}
		echo 'Produkte zu aktualisieren ' . count($toUpdate) . "\n";
		self::updateProducts(array_keys($toUpdate));
		self::updateProductsWithChangedDocuments();

		$mysql->setLastSync('products');
	}

	/**
	 * Retrieves orders from the database where pleasant_id is not set and inserts them as Pleasant documents.
	 * Updates the database with the generated pleasant_id after successful insertion.
	 *
	 * @throws Exception Throws an exception if there is an error inserting the document.
	 */
	public function getOrdersFromDB()
	{
		$db     = new \MySQLDBInterface();
		$query  = 'SELECT * FROM hobait_order_export WHERE pleasant_id IS NULL';
		$result = $db->query($query);
		foreach ($result as $res)
		{
			$pleasantOrdernumber = self::insertSWOrderAsPleasantDocument(json_decode($res['order_details']));
			if (!empty($pleasantOrdernumber))
			{
				$db    = new \MySQLDBInterface();
				$query = 'UPDATE hobait_order_export SET pleasant_id = ? WHERE sw_id = ? ';
				$db->query($query, [$pleasantOrdernumber, $res['sw_id']]);
			}
			else
			{
				die("Error inserting document");
				//somthing wrong, process error
			}
		}
	}

	/**
	 * Creates a pleasant document from the provided Shopware order data.
	 *
	 * This function handles the insertion of order details into a Pleasant-compatible format,
	 * converting the Shopware order data into a dossier set and document structure, which
	 * are used for further processing in external systems.
	 *
	 * @param mixed $orders An array or collection of Shopware orders containing
	 *                      details such as order number, amounts, customer information,
	 *                      and order date.
	 *
	 * @return string|null Returns the generated dossier number if the process is successful,
	 *                     or null on failure.
	 *
	 * @throws Exception If database queries fail or any other unexpected error occurs during execution.
	 */
	public function insertSWOrderAsPleasantDocument($orders): ?string
	{
		$sql         = new MySQLDBInterface();
		$salutations = $sql->getSalutations();

		foreach ($orders as $order)
		{
			$personenNr = @($order->orderCustomer->customFields->custom_customer_personennr) ?? "Gast";

			$this->scope_currentOrder = $order;
			$orderNumber              = 'SHOP-' . $order->orderNumber;
			$total                    = $order->amountNet;
			$taxStatus                = $order->taxStatus;
			$date                     = self::pleasantDate($order->orderDate);
			$deDate                   = self::deDate($order->orderDate);

			// ------------------------------------ CREATE DOSSIERSET ------------------------------------

			$sequence = self::queryValue('SELECT * FROM SEQUENCETAB WHERE SEQUENCE_NAME = ?', ['SEQUENCEDOCNRDO_1']);

			$this->query('UPDATE SEQUENCETAB SET LAST_NUMBER = ? WHERE SEQUENCE_NAME = ?', [(int) $sequence['LAST_NUMBER'] + (int) $sequence['INCREMENT_BY'], 'SEQUENCEDOCNRDO_1']);

			$sequenceSuffix   = self::queryValue('SELECT SUFFIX FROM DOCUMENTNRDEFINITION WHERE DOCUMENTTYPE=?', ['DO'])['SUFFIX'];
			$dossierNumber    = date('y') . '-' . $sequence['LAST_NUMBER'] . $sequenceSuffix; //02.08.24 Bindestrich entfernt
			$pleasantCustomer = self::queryValue('SELECT * FROM KUNDE WHERE personennr=?', [$personenNr]);

			$this->currentScopeVariables['__PERSONENNR__']     = $personenNr;
			$this->currentScopeVariables['__DOCUMENTNUMBER__'] = $orderNumber;
			$comment                                           = $order->customerComment ?? 'Ihre Onlinebestellung ' . $orderNumber . ' beim Fachgroßhandel Hans Reinhold und Sohn';
			$representative                                    = null;
			if (!empty($order->orderCustomer->customFields->custom_customer_contact))
			{
				$representative = json_decode($order->orderCustomer->customFields->custom_customer_contact);
				$representative = $representative->name . ' | ' . $representative->tel . "\n" . $representative->mail;
			}

			$DOSSIERSET =
				[
					"CREATIONDATE"                => $date,
					"DOSSIERCHARACTERIZATION"     => $comment,
					"DOSSIERNUMBER"               => $dossierNumber,
					"KZNURKOMPLETTELIEFBERECHNEN" => null,
					"MODIFICATIONDATE"            => $date,
					"PERSONENKZ"                  => "K",
					"PERSONENNR"                  => $personenNr,
					"SPRACHENCODE"                => "DEM",
					"STAFFMEMBERCREATION"         => "XY",
					"STAFFMEMBERMODIFICATION"     => "XY",
					"TEILLIEFERUNGERLAUBT"        => null,
					"U_DOSSIERCHARACTERIZATION"   => strtoupper($orderNumber),
					"U_DOSSIERNUMBER"             => strtoupper($dossierNumber),
					"U_PERSONENNR"                => strtoupper($personenNr),
					"WAEHRUNGSCODE"               => "EUR",
					"FIELD01"                     => null,
					"FIELD02"                     => null,
					"FIELD03"                     => null,
					"FIELD04"                     => null,
					"FIELD05"                     => null,
					"FIELD06"                     => null,
					"FIELD07"                     => null,
					"FIELD08"                     => null,
					"FIELD09"                     => null,
					"FIELD10"                     => null,
					"FIELD11"                     => null,
					"FIELD12"                     => null,
					"FIELD13"                     => null,
					"FIELD14"                     => null,
					"FIELD15"                     => null,
					"FIELD16"                     => null,
					"FIELD17"                     => null,
					"FIELD18"                     => null,
					"FIELD19"                     => null,
					"FIELD20"                     => null,
					"FIELD21"                     => null,
					"FIELD22"                     => null,
					"FIELD23"                     => null,
					"FIELD24"                     => null,
					"FIELD25"                     => null,
					"FIELD26"                     => null,
					"FIELD27"                     => null,
					"FIELD28"                     => null,
					"FIELD29"                     => null,
					"FIELD30"                     => null,
					"FIELD31"                     => null,
					"FIELD32"                     => null,
					"FIELD33"                     => null,
					"FIELD34"                     => null,
					"FIELD35"                     => null,
					"FIELD36"                     => null,
					"FIELD37"                     => null,
					"FIELD38"                     => null,
					"FIELD39"                     => null,
					"FIELD40"                     => null,
					"PARTINVOICESADDITIONAL"      => null,
					"NOTIZ"                       => null,
					"OFFERLOST"                   => "0",
				];


			// ---------------------- DOCUMENTHEAD ----------------------------------
			$formFile     = self::query('SELECT f.FORMULARFILE FROM FORMFORMULARS AS f LEFT JOIN FORMDOCUMENTS AS d ON d.DOCUMENTTYPE = f.DOCUMENTTYPE AND d.FORMDOMAINNUMBER = f.FORMDOMAINNUMBER WHERE d.DOCUMENTDESCRIPTION=? AND f.DEFAULTFORMULAR = 1', ['Auftragsbestätigung'])[0]['FORMULARFILE'];
			$GOODDOCUMENT = [
				"ALTERNATECURRENCYCODE"       => null,
				"CREATIONDATE"                => $date,
				"DESCRIPTION"                 => null,
				"DOCUMENTNUMBER"              => $orderNumber,
				"DOCUMENTTYPE"                => "04",
				"DOSSIERNUMBER"               => $dossierNumber,
				"LSTFILENAME"                 => $formFile,
				"MODIFICATIONDATE"            => $date,
				"PERSONENNR"                  => $personenNr,
				"PERSONENNR2"                 => $personenNr,
				"PERSONENKZ"                  => "K",
				"PRINTEDFLAG"                 => 0,
				"SPRACHENCODE"                => "DEM",
				"SIGNALLOCATIONDOCNR"         => 2,
				"STAFFMEMBERMODIFICATION"     => "XY",
				"STAFFMEMBERCREATION"         => "XY",
				"WAEHRUNGSCODE"               => "EUR",
				"CARRIADDRESS2"               => 0,
				"COMMADDRESS2"                => 0,
				"COMPLETE"                    => 0,
				"DISABLESTOCK"                => 0,
				"FIBUSAVED"                   => 0,
				"PACKAGE"                     => 1,
				"PREVDOCUMENTNUMBER"          => null,
				"PREVDOCUMENTTYPE"            => null,
				"PRICEINCLVAT"                => 0,
				"SEPFIBU"                     => 0,
				"SHIPPINGDATE"                => "99999999",
				"VERSANDARTKZ"                => "LKW",
				"VERSANDARTKZ2"               => "LKW",
				"VERSANDZONE"                 => "1 km",
				"VERSANDZONE2"                => "1 km",
				"U_DOCUMENTNUMBER"            => strtoupper($orderNumber),
				"U_DOSSIERNUMBER"             => strtoupper($dossierNumber),
				"U_PERSONENNR"                => strtoupper($personenNr),
				"BANKPLZ"                     => null,
				"BLZ"                         => null,
				"KONTONR"                     => null,
				"RENUMBER"                    => 1,
				"KZNURKOMPLETTELIEFBERECHNEN" => $pleasantCustomer['KZNURKOMPLETTELIEFBERECHNEN'],
				"TEILLIEFERUNGERLAUBT"        => $pleasantCustomer['TEILLIEFERUNGERLAUBT'],
				"FREMDDOKUMENTNR"             => null,
				"TEMPLATE"                    => 0,
				"KEINEFIBUUEBERGABE"          => 0,
				"POSCALCDECIMALS"             => 2,
				"DPFLAGSFROMUSER"             => 0,
				"PAYMENTS"                    => 0,
				"BIC"                         => null,
				"IBAN"                        => null,
				"U_SHIPPINGDATE"              => "99999999",
				"PAYCASHNAME"                 => null,
				"PAYCURRENCY"                 => null,
				"PAYCASHGIVEN"                => null,
				"PAYCASHBACK"                 => null,
				"PAYUNBAR"                    => null,
				"TSEDATA"                     => null,
				"SIGNED"                      => "99999999",
				"CREATIONTIME"                => date('His', strtotime($order->orderDate))
			];

			// ------------------------------------ CREATE DOCUMENTHEAD ------------------------------------
			$shippingAddress = $order->deliveries[0]->shippingOrderAddressId;
			$billingAddress  = $order->billingAddressId;

			foreach ($order->addresses as $address)
			{
				if ($address->id == $shippingAddress)
				{
					$shippingAddress = $address;
				}
				if ($address->id == $billingAddress)
				{
					$billingAddress = $address;
				}
			}

			$this->scope_currentOrder->bAddress = $billingAddress;
			$this->scope_currentOrder->sAddress = $shippingAddress;

			if (empty($shippingAddress))
			{
				die('KEINE ADRESSE GEFUNDEN');
			}

			$billingCountry  = self::toPleasantCountry($billingAddress->countryId);
			$shippingCountry = self::toPleasantCountry($billingAddress->countryId);

			$shippingCountryText = self::query('SELECT LANDBEZ FROM LAENDER WHERE LAND = ?', [$shippingCountry])['LANDBEZ'] ?? null;
			$billingCountryText  = self::query('SELECT LANDBEZ FROM LAENDER WHERE LAND = ?', [$billingCountry])['LANDBEZ'] ?? null;

			$salutation = $salutations[$billingAddress->salutationId] ?? 'fehler';


			$DOCUMENTHEAD = [
				"ANREDETITEL1"          => $salutation . ' ' . $shippingAddress->firstName . ' ' . $shippingAddress->lastName,
				"ARBEITSAUFTRAG"        => null,
				"ATTACHMENTS"           => null,
				"CITY01"                => $billingAddress->city,
				"CITY02"                => $shippingAddress->city,
				"CITY1"                 => $billingAddress->zipcode . ' ' . $billingAddress->city,
				"CITY2"                 => $shippingAddress->zipcode . ' ' . $shippingAddress->city,
				"CLIENTNUMBER"          => $pleasantCustomer["INTERNALNUMBER"],
				"CONTACT1"              => $salutation . ' ' . $billingAddress->firstName . ' ' . $billingAddress->lastName,
				"CONTACT2"              => null,
				"COUNTRY1"              => $billingCountry,
				"COUNTRY2"              => $shippingCountry,
				"CREATIONDATE"          => $date,
				"CREATIONSIGN"          => null,
				"DOCUMENTDATE"          => $deDate,
				"DOCUMENTDATEINT"       => $date,
				"DOCUMENTNUMBER"        => $orderNumber,
				"DOCUMENTSTATUS"        => "0",
				"DOCUMENTTYPE"          => "04",
				"DOKUMENTHERKUNFT"      => null,
				"DOSSIERNUMBER"         => $dossierNumber,
				"EMAIL1"                => $order->orderCustomer->email,
				"FAXNR1"                => null,
				"FIELD01"               => null, //$pleasantCustomer["INTERNALNUMBER"],
				"FIELD02"               => $order->orderCustomer->email, //"service@boomtec.de",
				"FIELD03"               => null, //"03733 596799-43",
				"FIELD04"               => null, //"luepke.m@fgh-reinhold.de",
				"FIELD05"               => $comment, //"Dies ist die Projektbeschreibung",
				"FIELD06"               => ' ' . $salutation . ' ' . $shippingAddress->firstName . ' ' . $shippingAddress->lastName, //"r Herr Lüpke",
				"FIELD07"               => null, //null,
				"FIELD08"               => $order->orderCustomer->customFields->custom_customer_eg_ident, //null,
				"FIELD09"               => $order->orderCustomer->customFields->custom_customer_field04, //"92407",
				"FIELD10"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\fs18 Das von Ihnen bestellte Produkt unterliegt einer Verwendungsbeschränkung, Überwachung bzw. Nachverfolgung nach EU-rechtlichen und deutschen Gesetzlichkeiten.\\i\\fs16 (Beschränkung bestimmter Chemikalien: Verordnung (EG) 1272/2008 + ChemVerbotsV  | Verwendung von Ausgangsstoffen für Explosivstoffe: Verordnung (EU) 2019/1148 + AusgStG | Überwachung von Drogenausgangsstoffen: Verordnung (EG) 273/2004 + GüG)\\i0\\fs18\\par\nDie Abgabe und Weitergabe ist somit nur unter bestimmten Voraussetzungen erlaubt. Dies prüfen wir an Hand dieser Endverbleibserklärung. Bitte beachten Sie hierzu auch die Hinweise, wie Abgabeverbote o.ä.  in der Auftragsbestätigung. \\par\n\\b Bitte \\ul vollständig \\ulnone ausfüllen und an uns zurück senden (eMail: bestellung@fgh-reinhold.de, Fax: 03733 596799-30, Post), da sonst keine Lieferung möglich ist.\\f1\\fs16\\par\n}",
				"FIELD11"               => null, //null,
				"FIELD12"               => null, //"Chemnitz (Di+Do)",
				"FIELD13"               => null, //"03733 56565655",
				"FIELD14"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 MS Shell Dlg;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\f0\\fs20 Sehr geehrte Damen und Herren,\\par\n\\f1\\par\n\\f0 wir bestellen nachfolgende Artikel zum nächstmöglichen Liefertermin. Bitte informieren Sie uns, wenn die hier angegebenen Preise abweichen bzw. die Bestellmengen nicht den vereinbarten Liefermengen entsprechen.\\f1\\par\n}",
				"FIELD15"               => null, //"Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihren Auftrag. Entsprechend berechnen wir Ihnen nachfolgende Lieferungen und Leistungen:",
				"FIELD16"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\fs18 vielen Dank für Ihre Anfrage. Entsprechend möchte ich Ihnen nachfolgendes Angebot unterbreiten:\\par\n}",
				"FIELD17"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\fs18 vielen Dank für Ihren Auftrag. Entsprechend möchte ich Ihnen nachfolgende Artikel bestätigen. Den voraussichtlichen Liefertermin entnehmen Sie bitte den Artikel-Angaben.\\f1\\fs16\\par\n}\n",
				"FIELD18"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\fs18 für nachfolgende Artikel bitte ich um ein Angebot.\\f1\\fs16\\par\n}",
				"FIELD19"               => null, //null,
				"FIELD20"               => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil MS Shell Dlg;}{\\f1\\fnil\\fcharset0 MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\f0\\fs16 am 01.01.2019 trat in Deutschland das Verpackungsgesetz (VerpackG) in Kraft.\\par\n\\par\nDieses besagt, dass Verpackungen die an den Kunden abgegeben werden, im Dualen System und bei der \\f1„Stiftung Zentralen Stelle Verpackungsregister\\ldblquote  in der „LUCID\\ldblquote -Datenbank registriert werden müssen.\\par\nSystembeteiligungspflichtige Verpackungen sind nach § 3 Abs. 8 VerpackG mit Ware befüllte Verkaufsverpackungen sowie Umverpackungen, die nach Gebrauch typischerweise beim Endverbraucher als Abfall anfallen.\\par\nBitte geben Sie uns darüber Auskunft, ob Sie Ihre Verpackungen in Deutschland bei der Registerdatenbank „LUCID\\ldblquote  und bei einem frei wählbaren System (Duales System) registriert haben.\\par\nSollte dies nicht der Fall sein, tragen Sie bitte das Gewicht der Produktverpackung beim entsprechenden Material ein. Hierzu zählen auch Füllmaterialien wie zum Beispiel Verpackungschips. Somit können wir diese ordnungsgemäß registrieren. (PPK = Papier - Pappe - Karton)\\par\n\\par\nBeispiele zur Verdeutlichung:\\par\nToilettenpapier (8 Rollen im Folie-Pack; 8 Pack im Karton):\\par\n\\tab 1. das Gewicht der Rolle auf, dass das Toilettenpapier gewickelt ist (10 g/Rolle)\\par\n\\tab 2. die Folie mit dem die einzelnen Verkaufseinheiten verpackt sind (25 g/Pack)\\par\n\\tab 3. der Karton in dem das Toilettenpapier geliefert wird (800 g)\\par\n\\par\n\\tab\\i eintragen für 1 Karton:\\par\n\\i0\\tab\\b PPK: 1,44 kg\\b0  [(10 g x 8 Rolle x 8 Pack = 640 g)+(800 g Karton) = 1440 g]\\par\n\\tab\\b Kunststoff: 0,2 kg\\b0  (25 g x 8 Pack = 200 g = 0,2 kg)\\par\n\\par\nDose gefüllt mit Imprägnier Spray (400 ml)\\par\n\\tab 1. die Verschlusskappe und der Sprühkopf (Kunststoff: 4 g)\\par\n\\tab 2. die Dose selbst (Weißblech = Eisenmetall: 90 g)\\par\n\\tab 3. der Karton in dem die Ware geliefert wird 12 Dosen/Karton (500 g)\\par\n\\tab 4. das Füllmaterial um die Ware vor Beschädigungen zu schützen wie Luftpolsterfolie, Wellpappe etc. (Luftpolster: 300 g)\\par\n\\par\n\\tab\\i eintragen für 1 Dose:\\i0\\par\n\\tab\\b PPK: 0,041 kg \\b0 (Umkarton 500 g : 12 Dosen = 41,7 g)\\par\n\\tab\\b Kunststoff: 0,029 kg\\b0  (4 g + (Luftpolster 300 g:12 Dosen) = 29 g)\\par\n\\tab\\b Eisen: 0,090 kg\\b0  (Dose)\\par\n\\par\nWir danken Ihnen für Ihre Mithilfe.\\f0\\par\n}",
				"FIELD21"               => $salutation . ' ' . $billingAddress->firstName . ' ' . $billingAddress->lastName, //"Herr Lüpke, Marioxxx",
				"FIELD22"               => $deDate, //null,
				"FIELD23"               => null, //"Privat",
				"FIELD24"               => null, //null,
				"FIELD25"               => null, //"Mail erst nach vollständiger Lieferung",
				"FIELD26"               => null, //null,
				"FIELD27"               => null, //null,
				"FIELD28"               => null, //"wir bedauern unsere fehlerhafte Lieferung. Nachfolgende Artikel wurden von uns zurückgenommen und gutgeschrieben.",
				"FIELD29"               => null, //"09235 Burkhardtsdorf",
				"FIELD30"               => null, //"09235 Burkhardtsdorf",
				"GAEBDATA"              => " ",
				"HEADTYPE"              => null,
				"KOSTENTRAEGER"         => null,
				"LANDBEZ1"              => $billingCountryText,
				"LANDBEZ2"              => $shippingCountryText,
				"MEASUREIDX"            => 1,
				"MITARBEITER"           => null,
				"MITARBEITERNAME"       => null,
				"NAME11"                => $billingAddress->company, //$billingAddress->firstName . ' ' . $billingAddress->lastName, @removed 01.08.24
				"NAME12"                => $shippingAddress->company,  //$billingAddress->firstName . ' ' . $billingAddress->lastName, @removed 01.08.24
				"NAME21"                => null,
				"NAME22"                => null,
				"OURSIGN"               => "XY",
				"PARTINVOICENUMBER"     => null,
				"PERSONENOBJECTNR"      => 0,
				"POSTALCODE1"           => $billingAddress->zipcode,
				"POSTALCODE2"           => $shippingAddress->zipcode,
				"PREVDOCUMENTDATE"      => '99999999',
				"REFERENCE"             => null,
				"SALUTATION1"           => "An",
				"SALUTATION2"           => null,
				"SHIPPINGDATE"          => $date,
				"SHIPPINGTIME"          => "999999",
				"STAFFMEMBERNAME"       => "Kundenservice",
				"STEIGERBERECHNUNGSART" => "K",
				"STEIGERMATERIALFAKTOR" => 1,
				"STREETPOSTOFFICE1"     => $billingAddress->street,
				"STREETPOSTOFFICE2"     => $shippingAddress->street,
				"TELEPHONNR1"           => $shippingAddress->phoneNumber,
				"U_DOCUMENTDATE"        => strtoupper(date('d.m.Y')),
				"U_FIELD21"             => strtoupper($salutation . ' ' . $shippingAddress->firstName . " " . $shippingAddress->lastName),
				"U_FIELD22"             => $deDate,
				"U_NAME11"              => strtoupper($billingAddress->company),
				"U_REFERENCE"           => " ",
				"U_SHIPPINGDATE"        => strtoupper($date),
				"YOURLETTERFROM"        => null,
				"YOURSIGN"              => null,
			];


			$DOCUMENTFOOT = [
				"DEFCURR"           => "EUR",
				"DISCOUNT"          => 0,
				"DISCOUNTFLAG"      => 0,
				"DOCUMENTNUMBER"    => $orderNumber,
				"DOCUMENTTYPE"      => "04",
				"DOSSIERNUMBER"     => $dossierNumber,
				"FIELD01"           => null, //"Ich würde mich freuen, wenn Sie sich, nach eingehender Prüfung des Angebotes, für unsere Artikel entscheiden. Zur Bestellung senden Sie das Angebot einfach unterschrieben per Fax oder Mail zurück oder Sie bestellen telefonisch unter unserer Service-Nummer 03733 5967990.\n\nAlle Artikel werden in der Regel innerhalb von 2 Werktagen frei Haus geliefert.",
				"FIELD02"           => null, //"Ihr Ansprechpartner für Rückfragen ist:",
				"FIELD03"           => $representative, //"Kevin Laubenstein | Telefon 03733/596799-43 | Mobil 0175/2453630\neMail laubenstein.k@fgh-reinhold.de",
				"FIELD04"           => null, //"Mit freundlichen Grüßen",
				"FIELD05"           => null, //NULL,
				"FIELD06"           => null, //"Fachgroßhandel Hans Reinhold & Sohn\nInhaber Jörg Reinhold",
				"FIELD07"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 MS Shell Dlg;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\b\\f0\\fs16 Auftragsbestätigung\\par\n\\b0 Auftrag erteilt/mit gekennzeichneten Änderungen erteilt:\\par\n\\par\n\\par\n\\par\n\\par\n\\par\n_______________________________________\\par\nDatum                           Unterschrift\\f1\\par\n\\pard\\par\n}",
				"FIELD08"           => null, //"Mario Lüpke",
				"FIELD09"           => null, //"Vielen Dank für Ihre Bemühungen!",
				"FIELD10"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fswiss\\fprq2\\fcharset0 Arial;}{\\f1\\fnil\\fprq2\\fcharset2 Wingdings;}{\\f2\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\brdrt\\brdrs\\brdrw10\\brsp20 \\sa200\\tqr\\tx9072\\f0\\fs18 Hiermit bestätigen wir/ich (Bitte zutreffendes ankreuzen!):\\par\n\\pard\\fi-357\\li714\\sa120\\tqr\\tx709\\f1¨ \\f0 Als \\i Endabnehmer/Verwender\\i0  verwenden wir die o.g. Stoffe und Gemische ausschließlich in erlaubter Weise.\\f1\\par\n¨\\tab  \\f0 Als \\i Apotheke\\i0  bedürfen wir keiner Erlaubnis nach § 6 (1) Nr. 2 ChemVerbotsV.\\par\n\\f1¨\\tab  \\f0 Als \\i Wiederverkäufer/Handelsgewerbetreibender\\i0  für gefährliche Stoffe und Gemische besitzen wir eine behördliche Erlaubnis nach § 6 (2) ChemVerbotsV.\\par\n\\pard\\fi-357\\li714\\sa120\\tqr\\tx709\\tqr\\tx9072\\f1¨\\tab  \\f0 Als \\i Wiederverkäufer/Handelsgewerbetreibender\\i0  für gefährliche Stoffe und Gemische haben wir das Inverkehrbringen gemäß § 7 ChemVerbotsV der zuständigen Behörde angezeigt und verfügen über eine verantwortliche Person gem. § 11 ChemVerbotsV. Diese ist: \\ul\\tab\\line\\ulnone Unser Kunde gibt dabei eine ähnliche Erklärung zur Verwendung ab.\\par\n\\pard\\fi-357\\li714\\sa120\\tqr\\tx709\\f1¨\\tab  \\f0 Als \\iöffentliche Forschungs-, Untersuchungs- oder Lehranstalten \\i0 wird oben genannter Stoff ausschließlich zu \\line\\f1¨\\f0  Forschungs-, \\f1¨\\f0  Analyse-, \\f1¨\\f0  Ausbildungs- oder \\f1¨\\f0  Lehrzwecken verwendet.\\par\n\\pard\\sa200 Wir wurden über die mit dem Verwenden des Stoffes oder des Gemisches verbundenen Gefahren, die notwendigen Vorsichtsmaßnahmen beim bestimmungsgemäßen Gebrauch und für den Fall des unvorhergesehenen Verschüttens oder Freisetzens sowie über die ordnungsgemäße Entsorgung unterrichtet. \\b Mir/uns ist bekannt, dass eine Abgabe an Unternehmen oder Privatpersonen nur unter den gesetzlichen Vorgaben möglich ist und dafür eine ähnliche Erklärung einzuholen ist.\\cf1\\b0\\f2\\fs16\\par\n}",
				"FIELD11"           => null, //NULL,
				"FIELD12"           => null, //"Test",
				"FIELD13"           => null, //NULL,
				"FIELD14"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fswiss\\fprq2\\fcharset0 Arial;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\ri-567\\sa200\\sl276\\slmult1\\tx3402\\tx6804\\f0\\fs18 Betrag dankend erhalten\\tab Warenausgabe Lager:\\tab Bitte quittieren Sie den Empfang der Ware:\\par\n\\par\n\\par\n\\pard\\li1\\ri-567\\sa200\\sl276\\slmult1\\tx3402\\tx6804 ............................................................\\tab ............................................................\\tab ............................................................\\line Datum/Unterschrift Kasse\\tab Datum/Unterschrift Warenausgabe\\tab Datum/Unterschrift Warenannahme\\cf1\\f1\\fs16\\par\n\\pard\\par\n}",
				"FIELD15"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil MS Shell Dlg;}{\\f1\\fnil\\fcharset0 MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\f0\\fs20 Entgeltminderungen ergeben sich aus unseren aktuellen Rahmen- und Konditionsvereinbarungen.\\par\n\\fs16\\par\n\\fs20 Auf Grund \\f1 der derzeitigen Nachfragesituation und Erhöhung von Rohstoffpreisen erfolgen täglich\\f0  Preiserh\\f1öhungen unserer Lieferanten. Daher sind auch wir aus wirtschaftlichen Gründen gezwungen, \\b\\fs24 fortlaufend Preisanpassungen \\b0\\fs20 vorzunehmen. Bereits bestätigte Aufträge werden mit den gültigen Tagespreisen geliefert und berechnet. Wir bitten um Verständnis für diese Ausnahmesituation!\\fs16\\par\n\\par\nDer Mindermengenzuschlag bei Bestellwert unter 75 EUR ändert sich auf 7,50 EUR. Für Gefahrgut wird bei Versand mit Paketdienst ein Zuschlag von 5,00 EUR erhoben.\\par\nEinzelne Preisinformationen erhalten Sie unter unserem Service-Telefon 03733 5967990 oder direkt von Ihrem Fachberater.\\par\n\\f0\\par\n\\f1 Die Datenerhebung erfolgte auf Grundlage der DSGVO. Unsere Datenschutzerklärung finden Sie unter: www.reinhold-sohn-hygiene.de/datenschutz.htm.\\f0\\par\n}",
				"FIELD16"           => null, //NULL,
				"FIELD17"           => null, //"{\\rtf1\\fbidis\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\ltrpar\\li-720\\ri-649\\sa120\\qj\\fs16 Die gelieferte Ware bleibt bis zur endgültigen Bezahlung aller bisherigen und zukünftigen Rechnungen unser Eigentum. Unsere Rechnung bitten wir unter Berücksichtigung unserer Zahlungsbedingungen zu regulieren. \\par\n\\pard\\ltrpar Druckfehler, Preisänderungen, Irrtümer und Zwischenverkauf vorbehalten. Die Angebote aus diesem Katalog sind ausschließlich für Industrie, Handel, Handwerk und die freien Berufe zur Verwendung in der selbständigen, beruflichen oder gewerblichen Tätigkeit bestimmt. Alle Preise sind in Euro zzgl. der gesetzlichen Mehrwertsteuer.\\cf1\\f1\\par\n}",
				"FIELD18"           => null, //"{\\rtf1\\fbidis\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fswiss\\fprq2\\fcharset0 Arial;}{\\f2\\fnil MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\ltrpar\\cf1\\b\\fs18 Lieferbestätigung\\par\n\\b0\\par\nBitte überprüfen Sie die Ware beim Empfang auf ihre Unversehrtheit. Offensichtliche Mängel bescheinigen Sie durch einen entsprechenden Vermerk auf dem Lieferschein.\\par\n\\par\n\\pard\\ltrpar\\ri-567\\sa200\\sl276\\slmult1\\tx3402\\tx6804\\cf0\\f1 Warenausgabe Lager:\\tab Warenlieferung:\\tab Bitte quittieren Sie den Empfang der Ware:\\par\n\\par\n\\par\n............................................................\\tab ............................................................\\tab ............................................................\\line Datum/Unterschrift Warenausgabe\\tab Datum/Unterschrift Warenlieferung\\tab Datum/Unterschrift Warenannahme\\cf1\\f2\\fs16\\par\n}",
				"FIELD19"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil\\fcharset0 Arial;}{\\f1\\fnil\\fcharset0 MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\fs20 Bitte verrechnen Sie diese Rechnungskorrektur mit der Ihnen vorliegenden oder einer der nächsten Rechnung(en). G\\f1 eben Sie dabei bitte neben Kundennummer und Rechnungsnummer (mit der verrechnet werden soll) auch die Nummer dieser Rechnungskorrektur an.\\f0\\par\n}",
				"FIELD20"           => null, //"{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1031{\\fonttbl{\\f0\\fnil MS Shell Dlg;}{\\f1\\fnil\\fcharset0 MS Shell Dlg;}}\n{\\colortbl ;\\red0\\green0\\blue0;}\n\\viewkind4\\uc1\\pard\\cf1\\b\\f0\\fs18 Vielen Dank f\\f1ür Ihren Auftrag!\\par\n\\par\n\\b0 Ich habe Ihnen die gewünschten Artikel mit Sorgfalt zusammengestellt und verpackt. \\par\nUnser Ziel ist es, Sie als Kunde zufriedenzustellen. Sollte trotzdem ein Versäumnis oder ein Defekt vorkommen, rufen Sie uns unter Telefon 03733/5967990 an  - Wir finden sicher eine akzeptable Lösung!\\par\n\\par\n\\par\n\\par\n\\par\n...............................................\\par\nUnterschrift \\f0\\par\n\\pard\\fs16\\par\n}",
				"FOOTTYPE"          => null,
				"FRACHTKOSTEN"      => $order->shippingTotal ?? null,
				"GOODVATKEY"        => "Voller USt-Satz", //@todo
				"GRANDTOTAL"        => $order->amountTotal,
				"GRANDTOTALDC"      => $order->amountTotal,
				"MWSTBEZ"           => null,
				"NETFRACHTKOSTEN"   => $order->shippingTotal ?? null,
				"PERSONVATKEY"      => $pleasantCustomer["PERSONVATKEY"],
				"SIGNATURE"         => "Kundenservice",
				"TOPNR"             => $pleasantCustomer['TOPNR'],
				"TOTALAMOUNT"       => $total,
				"TOTALAMOUNTCOMM"   => $total,
				"TOTALAMOUNTDC"     => $total,
				"TOTALAMOUNTWOC"    => $total,
				"VATAMOUNTCARRIAGE" => ($order->shippingTotal * 0.19) ?? null,
				"VATRATE"           => 19, //FRACHTKOSTEN
				"VATRATECARRIAGE"   => 19, //FRACHTKOSTEN
				"GAEBDATA"          => " ",
				"MEASUREIDX"        => 1,
			];

			$items = [];

			foreach ($order->lineItems as $lineItem)
			{

				$this->scope_productNumber = $lineItem->payload->productNumber;

				$item = [
					'ALTMENGENEINHEIT'          => null, //@fixme seems wrong: $unitsOfQuantity
					'ALTQUANTITY'               => null, //fixed value
					'ALTUNITOFQUANTITY'         => null, //artikel
					'ANSCHRIFTENNR'             => null, //fixed value
					'ARTIKELNR'                 => $this->scope_productNumber,
					'BESTANDSKONTO'             => null, //artikel
					'BREITE'                    => null, //$artikelVerpackZuordn
					'CALCULATEQUANTITY'         => $lineItem->quantity,
					'DISCOUNTA1'                => null, //@
					'DISCOUNTA2'                => 0, //fixed value
					'DISCOUNTP1'                => null, //@
					'DISCOUNTP2'                => 0, //fixed value
					'DOCUMENTNUMBER'            => $orderNumber,
					'DOCUMENTTYPE'              => '04', //fixed value
					'DOSSIERNUMBER'             => $dossierNumber,
					'DSNUMBER'                  => 0, //fixed value
					'EKDISCOUNTA1'              => null, //$ekDiscountFields
					'EKDISCOUNTA2'              => null, //$ekDiscountFields
					'EKDISCOUNTP1'              => null, //$ekDiscountFields
					'EKDISCOUNTP2'              => null, //$ekDiscountFields
					'EKKONDUNIQUENUMBER'        => null, //fixed value
					'EKMODIFICATIONDATE'        => $date,
					'EKPRICEPERUNIT'            => null, //@hier.EKPRICEPERUNIT1-hier.EKDISCOUNTA1-hier.EKDISCOUNTA2
					'EKPRICEPERUNIT1'           => null, //$ekPricePerUnit1
					'EKPRICEPERUNIT2'           => null, //@hier.EKPRICEPERUNIT1-hier.EKDISCOUNTA1
					'EKSELLINGPRICE'            => null, //@hier.EKPRICEPERUNIT*shopMenge
					'EKSELLINGPRICEDC'          => null, //@hier.EKPRICEPERUNIT*shopMenge
					'EKSTAFFMEMBERCREATION'     => 'XY', //fixed value
					'EKSURCHARGE'               => 0, //fixed value
					'FIBUKONTO'                 => null, //$fibukonto
					'FIELD01A'                  => null, //artikel
					'FIELD01E'                  => null, //artikel_ek
					'FIELD02A'                  => null, //artikel
					'FIELD02E'                  => null, //artikel_ek
					'FIELD03A'                  => null, //artikel
					'FIELD03E'                  => null, //artikel_ek
					'FIELD04A'                  => null, //artikel
					'FIELD04E'                  => null, //artikel_ek
					'FIELD05A'                  => null, //artikel
					'FIELD05E'                  => null, //artikel_ek
					'FIELD06A'                  => null, //artikel
					'FIELD06E'                  => null, //artikel_ek
					'FIELD07A'                  => null, //artikel
					'FIELD07E'                  => null, //artikel_ek
					'FIELD08A'                  => null, //artikel
					'FIELD08E'                  => null, //artikel_ek
					'FIELD09A'                  => null, //artikel
					'FIELD09E'                  => null, //artikel_ek
					'FIELD10A'                  => null, //artikel
					'FIELD10E'                  => null, //artikel_ek
					'FIELD11A'                  => null, //artikel
					'FIELD11E'                  => null, //artikel_ek
					'FIELD12A'                  => null, //artikel
					'FIELD12E'                  => null, //artikel_ek
					'FIELD13A'                  => null, //artikel
					'FIELD13E'                  => null, //artikel_ek
					'FIELD14A'                  => null, //artikel
					'FIELD14E'                  => null, //artikel_ek
					'FIELD15A'                  => null, //artikel
					'FIELD15E'                  => null, //artikel_ek
					'FIELD16A'                  => null, //artikel
					'FIELD16E'                  => null, //artikel_ek
					'FIELD17A'                  => null, //artikel
					'FIELD17E'                  => null, //artikel_ek
					'FIELD18A'                  => null, //artikel
					'FIELD18E'                  => null, //artikel_ek
					'FIELD19A'                  => null, //artikel
					'FIELD19E'                  => null, //artikel_ek
					'FIELD20A'                  => null, //artikel
					'FIELD20E'                  => null, //
					'FIELDEXPIRYDATE'           => null, //fixed value
					'FORMULARESEDITABLE'        => 0, //fixed value
					'GEWICHT'                   => null, //$artikelVerpackZuordn
					'GGEWICHT'                  => null, //@GEWICHT*QUANTITY
					'GOODVATKEY'                => null, //artikel
					'HOEHE'                     => null,  //$artikelVerpackZuordn
					'ITEMIDENTNR'               => $lineItem->position,
					'ITEMKEY'                   => null, //$itemKey
					'ITEMNUMBER'                => $lineItem->position,
					'ITEMTEXT'                  => null, //$itemText
					'KOSTENSTELLE'              => null, //artikel
					'KURZTEXT'                  => null, //artikel
					'KZSKONTIERFAEHIG'          => null, //artikel
					'LOTNUANCENRS'              => null,  //fixed value
					'LOTNUANCENRS2'             => null,  //fixed value
					'LOTNUANCENRS3'             => null,  //fixed value
					'MENGENEINHEIT'             => null, //$mengeneinheit
					'MODIFICATIONDATE'          => $date,
					'NEKORREKTUR'               => 0,  //fixed value
					'NETDISCOUNTA1'             => null, //@(für Einzelpreis) =Listenpreis-Verkaufspreis
					'NETDISCOUNTA2'             => 0, //fixed value
					'NETPRICEPERUNIT'           => null, //@für Einzelpreis) =Listenpreis-Verkaufspreis
					'NETPRICEPERUNIT1'          => null, //@Listenpreis
					'NETPRICEPERUNIT2'          => null, //@(für Einzelpreis) =Listenpreis-Verkaufspreis
					'NETSELLINGPRICE'           => $lineItem->totalPrice,
					'NETSURCHARGE'              => 0, //fixed value
					'NUMPACKAGES'               => 0, //fixed value
					'PACKAGE'                   => null, //fixed value
					'PERSONENARTIKELNR'         => null, //$personenArtikelNr
					'PERSONENNR'                => null, //changed 07.11.24 $pleasantCustomer['PERSONENNR'], //fixed value
					'PREISEINHEIT'              => null, //artikel
					'PREVDOCUMENTNUMBER'        => null, //fixed value
					'PREVDOCUMENTTYPE'          => null, //fixed value
					'PREVUNIQUENUMBER'          => 0, //fixed value
					'PRICEPERUNIT'              => $lineItem->unitPrice,
					'PRICEPERUNIT1'             => null, //$pricePerunit1
					'PRICEPERUNIT2'             => $lineItem->unitPrice,
					'PRINTSUMFLAG'              => 0, //fixed value
					'QUANTITY'                  => $lineItem->quantity,
					'QUANTITYDECIMAL'           => 2, //fixed value
					'QUANTITYFORMULA'           => null, //fixed value
					'SELLINGPRICE'              => $lineItem->totalPrice, //@fixme --brutto
					'SERIALNRS'                 => null, //fixed value
					'SERIALNSPL'                => null, //fixed value
					'SERIALNUMBERS'             => null, //fixed value
					'SHIPPINGDATE'              => $date,
					'SIGNALTERNATIVEGOOD'       => 0, //fixed value
					'SIGNSET_AMOUNT'            => '11001', //fixed value
					'SIGNTYPE'                  => 1, //fixed value
					'STAFFMEMBERCREATION'       => 'XY', //fixed value
					'STEIGERMATERIALFAKTORCODE' => null, //fixed value
					'STOCKAVAILABLE'            => null, //$stockAvailable
					'STOCKAVAILABLEDOCUMENT'    => $lineItem->quantity,
					'STOCKQUANTITY'             => null, //$stockQuantity
					'STOREPLACELIST'            => null, //fixed value
					'STOREPLACENR'              => null, //fixed value
					'STOREPLACES'               => null, //fixed value
					'SURCHARGE'                 => 0,  //fixed value
					'TIEFE'                     => null, //$artikelVerpackZuordn
					'UNIQUENUMBER'              => $lineItem->position,
					'UNITSOFQUANTITY'           => null, //@todo ?? == 1 Flasche + 3 Kanister
					'U_ARTIKELNR'               => strtoupper($this->scope_productNumber),
					'U_DOCUMENTNUMBER'          => strtoupper($orderNumber),
					'U_STAFFMEMBERCREATION'     => 'XY',  //fixed value
					'VATRATE'                   => null, //vatrate
					'VKKONDUNIQUENUMBER'        => null, //fixed value
					'WAEHRUNGSCODE'             => null, //artikel_ek
					'WEIGHT'                    => null, //fixed value
				];


				$articleEk = self::queryValue('SELECT * FROM ARTIKEL_EK WHERE ARTIKELNR = ?');
				$article   = self::queryValue('SELECT * FROM ARTIKEL WHERE ARTIKELNR = ?');

				if (!empty($articleEk))
				{
					$item = self::magicFill($item, $articleEk, 'E');
				}
				if (!empty($article))
				{
					$item = self::magicFill($item, $article, 'A');
				}

				//ITEMKEY
				$itemKey         = self::queryValue('SELECT LAST_NUMBER, INCREMENT_BY FROM SEQUENCETAB WHERE SEQUENCE_NAME=?', ['SEQ$ITEMKEY']);
				$item['ITEMKEY'] = $itemKey['LAST_NUMBER'];
				$this->query('UPDATE SEQUENCETAB SET LAST_NUMBER = ? WHERE SEQUENCE_NAME=?', [(int) $itemKey['LAST_NUMBER'] + (int) $itemKey['INCREMENT_BY'], 'SEQ$ITEMKEY']);

				//SELECT FILLS
				$unitsOfQuantity = self::queryValue('select BESCHREIBUNG from UNITSOFQUANTITY, ARTIKEL where UNITSOFQUANTITY.MENGENEINHEIT=ARTIKEL.ALTUNITOFQUANTITY and ARTIKELNR=?', [$item['ARTIKELNR']])['BESCHREIBUNG'] ?? null;

				$item['ALTMENGENEINHEIT'] = $unitsOfQuantity;
				$item['MENGENEINHEIT']    = $unitsOfQuantity;

				switch ($article['KZEKPREISPFLEGE'])
				{
					case 1:
						$preisPflege = 'SELECT EK_LAST AS EKPRICEPERUNIT1 FROM ARTIKEL_EK WHERE ARTIKELNR=?';
						break;
					case 2:
						$preisPflege = 'SELECT EK_MANUAL AS EKPRICEPERUNIT1 FROM ARTIKEL_EK WHERE ARTIKELNR=?';
						break;
					default:
						$preisPflege = 'SELECT EK_AVG AS EKPRICEPERUNIT1 FROM ARTIKEL_EK WHERE ARTIKELNR=?';
				}

				$ekPricePerUnit1      = self::queryValue($preisPflege);
				$ekDiscountFields     = self::queryValue('SELECT LISTENPREIS*RABATT/100 AS EKDISCOUNTA1, LISTENPREIS*RABATT2/100 AS EKDISCOUNTA2, RABATT AS EKDISCOUNTP1, RABATT2 AS EKDISCOUNTP2 FROM EINKAUFSKONDITIONEN WHERE STANDARTCONDITIONEN=1 AND ARTIKELNR=?');
				$artikelVerpackZuordn = self::queryValue('SELECT * FROM ARTIKELVERPACKUNGSZUORD WHERE NAME = (SELECT MENGENEINHEIT FROM ARTIKEL WHERE ARTIKELNR = ?) AND ARTIKELNR =?', [$this->scope_productNumber, $this->scope_productNumber]);
				$fibuKonto            = self::queryValue('SELECT ERLOESKONTOHANDEL AS FIBUKONTO FROM FIBUKONTEN,KUNDE WHERE KUNDE.PERSONVATKEY=FIBUKONTEN.PERSONVATKEY AND PERSONENNR=? AND NR=?', [$personenNr, $this->scope_productNumber]);//@todo mwst-tabelle
				$itemText             = self::queryValue('SELECT BESCHREIBUNG AS ITEMTEXT FROM ARTIKELSPRACHENZUORDNUNG WHERE SPRACHENCODE=? AND ARTIKELNR=?', ['DEM', $this->scope_productNumber]);
				$mengeneinheit        = self::queryValue('SELECT BESCHREIBUNG AS MENGENEINHEIT FROM UNITSOFQUANTITY, ARTIKEL WHERE UNITSOFQUANTITY.MENGENEINHEIT=ARTIKEL.MENGENEINHEIT and ARTIKELNR=?');
				$personenArtikelNr    = self::queryValue('SELECT PERSONENARTIKELNR FROM PERSONGOODNR WHERE PERSONENKZ=? AND PERSONENNR=? AND ARTIKELNR=?', ['K', $personenNr, $this->scope_productNumber]);
				$stockAvailable       = self::queryValue('SELECT sum(QUANTITY)-sum(RESERVEQUANTITY) AS STOCKAVAILABLE FROM STOCKADMINISTRATION WHERE ARTIKELNR=?');
				$stockQuantity        = self::queryValue('SELECT Gesamt AS STOCKQUANTITY from V$Lager_verfuegbar WHERE ARTIKELNR=?');
				$pricePerUnit1        = self::queryValue('SELECT LISTENPREIS AS PRICEPERUNIT1 FROM dbo.VERKAUFSKONDITIONEN WHERE STANDARTCONDITIONEN=1 AND ARTIKELNR=?');
				$kurztext             = self::queryValue('SELECT KURZTEXT AS KURZTEXT FROM dbo.ARTIKELSPRACHENZUORDNUNG WHERE  ARTIKELNR=?');
				$vatRate              = self::queryValue('SELECT VATRATE FROM MWST WHERE PERSONVATKEY = ? AND GOODVATKEY = ?', [$pleasantCustomer['PERSONVATKEY'], $article['GOODVATKEY']]);

				$item = self::magicFill($item,
					array_merge(
						$ekPricePerUnit1,
						$ekDiscountFields,
						$artikelVerpackZuordn,
						$fibuKonto,
						$itemText,
						$mengeneinheit,
						$personenArtikelNr,
						$stockAvailable,
						$stockQuantity,
						$pricePerUnit1,
						$kurztext,
						$vatRate
					)
				);

				$item['GGEWICHT']         = $item['GEWICHT'] * $item['QUANTITY'];
				$item['EKPRICEPERUNIT']   = $item['EKPRICEPERUNIT1'] - $item['EKDISCOUNTA1'] - $item['EKDISCOUNTA2'];
				$item['EKPRICEPERUNIT2']  = $item['EKPRICEPERUNIT1'] - $item['EKDISCOUNTA1'];
				$item['EKSELLINGPRICE']   = $item['EKPRICEPERUNIT'] * $item['QUANTITY'];
				$item['EKSELLINGPRICEDC'] = $item['EKPRICEPERUNIT'] * $item['QUANTITY'];

				$item['NETDISCOUNTA1']    = $item['PRICEPERUNIT1'] - $item['PRICEPERUNIT'];
				$item['NETPRICEPERUNIT']  = $item['PRICEPERUNIT1'] - $item['PRICEPERUNIT'];
				$item['NETPRICEPERUNIT1'] = $item['PRICEPERUNIT1'];
				$item['NETPRICEPERUNIT2'] = $item['PRICEPERUNIT1'] - $item['PRICEPERUNIT'];
				$item['DISCOUNTP1']       = ($item['PRICEPERUNIT1'] - $item['PRICEPERUNIT']) * 100 / $item['PRICEPERUNIT1'];
				$item['FIELD20E']         = $stockQuantity['STOCKQUANTITY'];


				if ($taxStatus == 'net')
				{
					$item['DISCOUNTA1'] = ($item['PRICEPERUNIT1'] - $item['PRICEPERUNIT']);
				}
				else
				{
					$item['DISCOUNTA1']   = round(($item['PRICEPERUNIT1'] - $item['PRICEPERUNIT']) * $item['VATRATE'], 2);
					$item['SELLINGPRICE'] = round($item['SELLINGPRICE'] * $item['VATRATE'], 2);
				}

				if ($item['PERSONENARTIKELNR'] == null)
				{
					$item['PERSONENARTIKELNR'] = $item['ARTIKELNR'];
				}

				ksort($item);
				$DOCUMENTHEAD = self::magicFill($DOCUMENTHEAD, $this->getDocHeadFieldsFromDB());
				$DOCUMENTFOOT = self::magicFill($DOCUMENTFOOT, $this->getDocFootFieldsFromDB());

				$items[] = $item;
			}
			$fullquery = "--------------" . $orderNumber . "--------------\n";
			$fullquery .= self::dumpInsertQuery('DOSSIERSET', $DOSSIERSET) . "\n";
			$fullquery .= self::dumpInsertQuery('GOODDOCUMENT', $GOODDOCUMENT) . "\n";
			$fullquery .= self::dumpInsertQuery('DOCUMENTHEAD', $DOCUMENTHEAD) . "\n";
			$fullquery .= self::dumpInsertQuery('DOCUMENTFOOT', $DOCUMENTFOOT) . "\n";

			foreach ($items as $item)
			{
				$fullquery .= self::dumpInsertQuery('GOODDOCUMENTITEM', $item) . "\n";;
			}

			file_put_contents('Z:\order_export.txt', $fullquery . "\n", FILE_APPEND);

//			self::query('DOSSIERSET', $DOSSIERSET);
//			self::query('GOODDOCUMENT', $GOODDOCUMENT);
//			self::query('DOCUMENTHEAD', $DOCUMENTHEAD);
//			self::query('DOCUMENTFOOT', $DOCUMENTFOOT);
//			foreach ($items as $item)
//			{
//				self::query('GOODDOCUMENTITEM', $item);
//			}
//			echo $incrementQuery . "\n";
		}

		return $orderNumber;
	}

	private function getDocHeadFieldsFromDB(): array
	{
		$sql     = "SELECT * FROM HOBAIT_SYNC_ORDER_FIELDS WHERE DOCPART = 'DOCUMENTHEAD'";
		$results = $this->query($sql, []);

		return $this->processDynamicFieldRows($results);
	}

	private function getDocFootFieldsFromDB(): array
	{
		$sql     = "SELECT * FROM HOBAIT_SYNC_ORDER_FIELDS WHERE DOCPART = 'DOCUMENTFOOT'";
		$results = $this->query($sql, []);

		return $this->processDynamicFieldRows($results);
	}

	/**
	 * @param $results
	 *
	 * @return array
	 */
	private function processDynamicFieldRows($results): array
	{
		$processed = [];


		foreach ($results as $result)
		{
			$text = '';
			if (!empty($result['CONTENT']))
			{
				$text = $result['CONTENT'];
				switch ($text)
				{
					case "shop.Projektbeschreibung":
						$text = 'Ihre Onlinebestellung bei Fachgroßhandel Reinhold und Sohn';
						break;
					case "shop.Bestelldatum":
						$text = date('d.m.Y', strtotime($this->scope_currentOrder->orderDate));
						break;
					case "shop.PLZ2 shop.Ort2":
						$text = $this->scope_currentOrder->sAddress->zipcode . ' ' . $this->scope_currentOrder->sAddress->city;
						break;
					case "shop.PLZ1 shop.Ort1":
						$text = $this->scope_currentOrder->bAddress->zipcode . ' ' . $this->scope_currentOrder->bAddress->city;
						break;
					case "shop.Anredetitel":
						$text = '/r ' . $this->scope_currentOrder->orderCustomer->firstName . ' ' . $this->scope_currentOrder->orderCustomer->lastName;
						break;
					case "shop.Emailadresse":
						$text = $this->scope_currentOrder->orderCustomer->lastName;
						break;
					case "shop.Faxnummer":
						$text = '';
						break;
					default:
						break;
				}
			}
			else if (!empty($result['CONTENT_SQL']))
			{
				$sql       = $result['CONTENT_SQL'];
				$sqlResult = $this->query($this->prepareQueryVariables($sql), [], PDO::FETCH_BOTH);
				if (!empty($sqlResult))
				{
					$text = $sqlResult[0][0];
				}
			}
			else if (!empty($result['CONTENT_FIELDTEXT']))
			{
				$sql       = "SELECT PASSAGETEXT FROM FIELDTEXT WHERE DOMNAME ='" . $result['CONTENT_FIELDTEXT'] . "' AND DEFAULTFLAG = 1";
				$sqlResult = $this->query($this->prepareQueryVariables($sql), []);
				if (!empty($sqlResult))
				{
					$text = $sqlResult[0]['PASSAGETEXT'];
				}
			}
			else if (!empty($result['CONTENT_KUNDE']))
			{
				$sql       = "SELECT " . $result['CONTENT_KUNDE'] . " FROM KUNDE WHERE PERSONENNR = '__PERSONENNR__'";
				$sqlResult = $this->query($this->prepareQueryVariables($sql), []);
				if (!empty($sqlResult))
				{
					$text = $sqlResult[0][$result['CONTENT_KUNDE']];
				}
			}

			if (!empty($result['CONTENT_PREFIX']))
			{
				$text = $result['CONTENT_PREFIX'] . $text;
			}
			if (!empty($result['CONTENT_SUFFIX']))
			{
				$text .= $result['CONTENT_PREFIX'];
			}

			$processed[$result['FIELD']] = $text;
		}

		return $processed;
	}

	/**
	 * Prepares the query by replacing placeholders with their corresponding values from the current scope variables.
	 *
	 * @param string $sql The SQL query containing placeholders.
	 *
	 * @return string The SQL query with placeholders replaced by their respective values.
	 */
	public function prepareQueryVariables(string $sql)
	{
		return str_replace(array_keys($this->currentScopeVariables), array_values($this->currentScopeVariables), $sql);
	}

	/**
	 * Overwrites elements in the first array with values from the second array.
	 *
	 * @param array $from The base array to be overwritten.
	 * @param array $to   The array containing values to overwrite the base array.
	 *
	 * @return array The modified array after overwriting.
	 */
	private static function overwriteArray(array $from, array $to): array
	{
		foreach ($to as $key => $value)
		{
			$from[$key] = $value;
		}

		return $from;
	}

	/**
	 * Convert a given identifier to its corresponding Pleasant country code.
	 *
	 * @param string $id Identifier for the Pleasant country.
	 *
	 * @return string Corresponding country code ('A', 'I', or 'D').
	 */
	public static function toPleasantCountry(string $id): string
	{
		switch ($id)
		{
			case 'b606df6c7ff644a7a545e991dff13782':
				return 'A';
			case '2ff909879362484396662ff38f7cdfdf':
				return 'I';
			default:
				return 'D';
		}
	}

	/**
	 * Inserts a new record into the specified table with given values.
	 *
	 * @param string $tableName Name of the table.
	 * @param array  $values    Associative array of column-value pairs to be inserted.
	 *
	 * @throws Exception
	 */
	public function insertQuery(string $tableName, array $values)
	{
		$values = array_fill(0, count($values), '?');
		$this->query('INSERT INTO ' . $tableName . '(' . implode(',', array_keys($values)) . ') VALUES (' . implode(', ', $values) . ')', $values);
	}

	/**
	 * Generates an SQL INSERT query string for the given table and values.
	 *
	 * @param string $tableName The name of the database table.
	 * @param array  $values    An associative array of column-value pairs to be inserted.
	 *
	 * @return string The generated SQL INSERT query.
	 */
	public function dumpInsertQuery(string $tableName, array $values)
	{
		return 'INSERT INTO ' . $tableName . '(' . implode(',', array_keys($values)) . ') VALUES (' . implode(', ', self::prepareValues($values)) . ')' . ";\n\n";
	}

	/**
	 * Prepare an array of values for further processing by converting
	 * specific data types into their string representations suitable for usage in queries.
	 *
	 * @param array $values Array of values to be prepared.
	 *
	 * @return array Processed array with converted values.
	 */
	public static function prepareValues(array $values)
	{
		$count = 0;
		foreach ($values as &$value)
		{
			$count++;
			if (is_string($value))
			{
				$value = "'" . $value . "'";
			}
			else if (is_bool($value))
			{
				if ($value === true)
				{
					$value = 'true';
				}
				else
				{
					$value = 'false';
				}
			}
			else if ($value === null || $value == '')
			{
				$value = 'null';
			}
			else if (is_float($value) || is_int($value) || is_numeric($value))
			{
				//do nothing
			}
			if (is_array($value))
			{
				echo $count . "\n";
			}
		}

		return $values;
	}

	/**
	 * Fills the given array with values from another array, optionally applying a suffix to keys.
	 *
	 * @param array  $toFill The array to be filled with new values.
	 * @param array  $values The array containing the values to insert into `$toFill`.
	 * @param string $suffix Optional suffix to append to keys when matching.
	 *
	 * @return array The modified array after values have been filled.
	 */
	public static function magicFill(array $toFill, array $values, string $suffix = '')
	{

		foreach ($values as $key => $value)
		{
			if (array_key_exists($key, $toFill))
			{
				$toFill[$key] = $value;
			}

			if (!empty($suffix))
			{
				if (array_key_exists($key . $suffix, $toFill))
				{
					$toFill[$key . $suffix] = $value;
				}
			}
		}

		return $toFill;
	}

	/**
	 * Executes a query and retrieves the first value from the result set.
	 *
	 * @param string $query The SQL query to be executed.
	 * @param array  $args  Parameters to bind to the query.
	 *
	 * @return array The first result of the query or an empty array if no results are found.
	 *
	 * @throws RuntimeException If the query execution fails.
	 */
	protected function queryValue(string $query, array $args = []): array
	{
		if (empty($args))
		{
			$args[] = $this->scope_productNumber;

		}
		$val = $this->query($query, $args);

		return $val[0] ?? [];
	}

	/**
	 * Converts a given date into the format 'Ymd'.
	 *
	 * @param string $date Input date in a recognized format.
	 *
	 * @return string Formatted date as 'Ymd'.
	 */
	public static function pleasantDate(string $date): string
	{
		return date('Ymd', strtotime($date));
	}

	/**
	 * Converts a given date string into German date format (DD.MM.YYYY).
	 *
	 * @param string $date The date string to convert.
	 *
	 * @return string The formatted date string.
	 */
	public static function deDate(string $date): string
	{
		return date('d.m.Y', strtotime($date));
	}

	/**
	 * Updates the Vertreter database table by fetching and restructuring data,
	 * and then inserting the processed data back into the hobaIT_vertreter table.
	 *
	 * @throws Exception If there is an issue with querying the database.
	 */
	public function updateVertreter()
	{
		$mysql = new MySQLDBInterface();

		$sql = 'TRUNCATE TABLE hobaIT_vertreter';
		$mysql->query($sql, []);

		$scope  = '';
		$sql    = 'SELECT * FROM V$hobaIT_vertreter_komplett ORDER BY PERSONENNR, BEZEICHNUNG ASC';
		$values = $this->query($sql, []);

		$contact = [
			'name'   => null,
			'mail'   => null,
			'phone'  => null,
			'notice' => null,
			'image'  => null
		];

		foreach ($values as $item)
		{
			if ($item['PERSONENNR'] != $scope)
			{
				if ($contact['name'] != null)
				{
					$sql   = 'SELECT PERSONENNR FROM MITARBEITER WHERE ANREDE = ?';
					$image = $this->query($sql . $contact['name']);

					$sql = 'INSERT INTO  hobaIT_vertreter VALUES (?,?,?,?,?,?,?)';
					$mysql->query($sql, [null, $scope, $contact['name'], $contact['mail'], $contact['phone'], $contact['notice']], $image);
				}

				$contact = [
					'name'   => null,
					'mail'   => null,
					'phone'  => null,
					'notice' => null,
					'image'  => null
				];
				$scope   = $item['PERSONENNR'];
			}

			if ($item['BEZEICHNUNG'] == "Vertrete")
			{
				$contact['name'] = $item['GRUPPENZUORDNUNG'];
			}
			else if ($item['BEZEICHNUNG'] == "Vertret1")
			{
				$contact['phone'] = $item['GRUPPENZUORDNUNG'];
			}
			else if ($item['BEZEICHNUNG'] == "Vertret2")
			{
				$contact['mail'] = $item['GRUPPENZUORDNUNG'];
			}
			else if ($item['BEZEICHNUNG'] == "Vertret3")
			{
				$contact['notice'] = $item['GRUPPENZUORDNUNG'];
			}
		}
	}

	/**
	 * Retrieves an array of folders and their path info based on the specified path.
	 *
	 * @param string $path Path to search for files, defaults to 'Z:/data/'.
	 *
	 * @return void
	 */
	public function getFolderArray(string $path = 'Z:/data/')
	{
		$files    = glob($path);
		$allFiles = [];
		foreach ($files as $file)
		{
			$allFiles[$file] = pathinfo($file);
		}
//		var_dump($allFiles);
	}

	/**
	 * Remove products from the shop that are no longer present in the Pleasant database
	 *
	 * This method compares product numbers in the shop against product numbers
	 * in the Pleasant database and removes those that no longer exist in the database.
	 *
	 * @throws \Exception If there is an issue with database queries or API calls
	 */
	public function removeDeletedProductsFromShop()
	{
		$productsShop = ((new MySQLDBInterface())->getAllProductNumbers());
		asort($productsShop);
		$sql              = 'SELECT ARTIKELNR FROM ARTIKEL';
		$productsPleasant = array_column(self::query($sql), 'ARTIKELNR');
		asort($productsPleasant);
		$diff     = array_diff($productsShop, $productsPleasant);
		$toDelete = [];
		foreach ($diff as $item)
		{
			if (!in_array($item, $productsPleasant))
			{
				$toDelete[] = $item;
			}
		}
		if (!empty($toDelete))
		{
			$client = new \hobaIT\APIClient();
			foreach ($toDelete as $product)
			{
				$client->deleteProductByProductNumber($product);
			}
		}
	}

	/**
	 * Update products that do not have associated images in the database.
	 *
	 * Retrieves product numbers for products without images and updates them.
	 *
	 * @throws MySQLDBException
	 */
	public function updateProductsWithoutImages()
	{
		$products = ((new MySQLDBInterface())->getProductNumbersOfProductsWithoutImage());
		self::updateProducts($products);
	}

	/**
	 * Update price rules based on the last synchronization date or a specific date.
	 *
	 * @param string|null $date The date to check for updates. If null or empty, the last sync date is used.
	 *
	 * @throws Exception
	 */
	public function updatePriceRules(?string $date = '')
	{
		$mysql = new MySQLDBInterface();
		if (empty($date))
		{
			$date = $mysql->getLastSync('custom_prices');
			$date = date('d.m.Y H:i:s', strtotime($date));
		}

		$sql        = 'SELECT DISTINCT (ARTIKELNR) FROM V$hobaIT_sync_article_conditions WHERE LASTACTIONDATE > ?';
		$updates    = self::query($sql, [$date]);
		$allUpdates = array_column($updates, 'ARTIKELNR');

		$sql        = 'SELECT * FROM Preisanpassung_Skript_Sync WHERE gespeichert_am > ?';
		$updates    = self::query($sql, [$date]);
		$allUpdates = array_merge($allUpdates, array_column($updates, 'Artikelnr'));
		$allUpdates = array_unique($allUpdates);

		echo 'Preisregeln für folgende Artikel wurden geändert' . implode(",", $allUpdates);

		self::setAdvancedPricesPerProduct($allUpdates);

		$mysql->setLastSync('custom_prices');
	}

	/**
	 * Updates products that have associated changed documents.
	 *
	 * This method copies the document table to create a "cached" version
	 * and then updates products based on the changes detected in the associated documents.
	 *
	 * @return void
	 */
	public function updateProductsWithChangedDocuments()
	{
		self::copyDocumentTable(); // set new "cached" table after update finisheds
		self::updateProducts(self::getProductsWithUpdatedDocuments());
	}

	/**
	 * Updates the delivery table by executing a query to fetch data
	 * and inserting it into the `hobait_pleasant_deliveries` MySQL table.
	 *
	 * The method performs the following steps:
	 * - Executes a query to fetch delivery-related data based on specific conditions.
	 * - Truncates the `hobait_pleasant_deliveries` table in the MySQL database.
	 * - Inserts data in batches to the `hobait_pleasant_deliveries` table.
	 *
	 * @throws Exception
	 */
	public function updateDeliveryTable()
	{
		$query = 'SELECT GOODDOCUMENTITEM.ARTIKELNR
					,DOCUMENTHEAD.SHIPPINGDATE
					,DOCUMENTHEAD.DOCUMENTDATEINT
					,GOODDOCUMENTITEM.KURZTEXT
					,GOODDOCUMENTITEM.ITEMTEXT
					,GOODDOCUMENTITEM.LOTNUANCENRS
					,DOCUMENTHEAD.CLIENTNUMBER
					,GOODDOCUMENTITEM.NETPRICEPERUNIT
					,GOODDOCUMENTITEM.DISCOUNTP1
					,GOODDOCUMENTITEM.NETPRICEPERUNIT2
					,GOODDOCUMENTITEM.NETSURCHARGE
					,GOODDOCUMENTITEM.QUANTITY
					,GOODDOCUMENTITEM.MENGENEINHEIT
					,GOODDOCUMENTITEM.NETSELLINGPRICE
					,GOODDOCUMENTITEM.DOCUMENTNUMBER
					,GOODDOCUMENTITEM.FIELD17A
					,DOCUMENTHEAD.NAME12
					,DOCUMENTHEAD.NAME22
					,DOCUMENTHEAD.CITY2
					,DOCUMENTHEAD.STREETPOSTOFFICE2
					,GOODDOCUMENTITEM.ITEMNUMBER
					from GOODDOCUMENTITEM,DOCUMENTHEAD
					where GOODDOCUMENTITEM.DOCUMENTTYPE=08
					  AND (SELECT TOP 1 PRINTEDFLAG FROM GOODDOCUMENT WHERE DOCUMENTNUMBER = DOCUMENTHEAD.DOCUMENTNUMBER) = 1
					and GOODDOCUMENTITEM.DOCUMENTNUMBER=DOCUMENTHEAD.DOCUMENTNUMBER
					order by GOODDOCUMENTITEM.U_ARTIKELNR'; //@todo check
//		var_dump('MSSQL ' . date('H:i:s'));
		$result = self::query($query);
		$mysql  = new MySQLDBInterface();
//		var_dump('TRUNCATE ' . date('H:i:s'));
		$mysql->query('TRUNCATE TABLE hobait_pleasant_deliveries');
//		var_dump('INSERT START ' . date('H:i:s'));
		$stack = [];
		foreach ($result as $row)
		{
			$stack[] = $row;
			if (count($stack) > 2999)
			{
				self::mysqlPDOMassInsert('hobait_pleasant_deliveries', $stack);
				$stack = [];
			}
		}
		self::mysqlPDOMassInsert('hobait_pleasant_deliveries', $stack);
//		var_dump('INSERT END ' . date('H:i:s'));
	}

	/**
	 * Perform a mass insert operation into a specified MySQL table using PDO.
	 *
	 * @param string $tableName The name of the table to insert data into.
	 * @param array  $data      Multidimensional array containing the rows of data to insert.
	 *
	 * @throws PDOException If the database query fails.
	 */
	protected function mysqlPDOMassInsert(string $tableName, array $data)
	{
		$insert       = 'INSERT INTO ' . $tableName . '  VALUES ';
		$mysql        = new MySQLDBInterface();
		$dataToInsert = array();
		foreach ($data as $row)
		{
			foreach ($row as $val)
			{
				$dataToInsert[] = $val;
			}
		}
		$rowPlaces = '(' . implode(', ', array_fill(0, count(array_values($data[0])), '?')) . ')';
		$allPlaces = implode(', ', array_fill(0, count($data), $rowPlaces));
		$mysql->query($insert . $allPlaces, $dataToInsert);
	}


	/**
	 * Retrieve a list of products with updated documents.
	 *
	 * @return array A list of product identifiers.
	 *
	 * @throws Exception
	 */
	public function getProductsWithUpdatedDocuments(): array
	{
		$products = [];
		$mysql    = new \MySQLDBInterface();
		$lastSync = (new DateTime($mysql->getLastSync()))->format('Ymd');

		$query = 'SELECT ARTIKELNR FROM HOBAIT_FOREIGNARTDOCS_COPY
		WHERE ROWID NOT IN (SELECT ROWID FROM FOREIGNARTDOCS)
		UNION
		SELECT ARTIKELNR FROM FOREIGNARTDOCS
		WHERE ROWID NOT IN (SELECT ROWID FROM HOBAIT_FOREIGNARTDOCS_COPY)';
		$res   = $this->query($query);

		foreach ($res as $r)
		{
			$products[$r] = true;
		}

		$query = 'SELECT DISTINCT(ARTIKELNR) FROM dbo.FOREIGNARTDOCS WHERE FILETIMEMODIFICATION >= ? OR FILETIMECREATION >= ? ';
		$res   = $this->query($query, [$lastSync, $lastSync]);
		foreach ($res as $r)
		{
			$products[$r['ARTIKELNR']] = true;
		}

		return array_keys($products);
	}

	/**
	 * Copies the FOREIGNARTDOCS table to a new table HOBAIT_FOREIGNARTDOCS_COPY.
	 * The method drops the target table if it already exists before copying.
	 *
	 * @throws Exception If the query execution fails.
	 */
	public function copyDocumentTable()
	{
		$query = 'DROP TABLE HOBAIT_FOREIGNARTDOCS_COPY; SELECT * INTO HOBAIT_FOREIGNARTDOCS_COPY FROM FOREIGNARTDOCS;';
		$this->query($query);
	}

}

class article
{
	public string $t;
	public float $q;
	public float $p;
	public string $u;
	public string $n;
	public float $d;
	public float $d1;
	public float $s;

	public function __construct($articleNumber, $quantity, $unit, $priceSingle, $discount1, $discount2, $surcharge, $text = '')
	{
		$this->n  = (string) $articleNumber;
		$this->q  = (float) $quantity;
		$this->u  = (string) $unit;
		$this->p  = (float) $priceSingle;
		$this->d  = (float) $discount1;
		$this->d1 = (float) $discount2;
		$this->s  = (float) $surcharge;
		$this->t  = (string) $text;
		if (empty($this->t))
		{
			unset($this->t);
		}
	}
}

//SELECT DISTINCT a.ARTIKELNR,
//				 	 spr.KURZTEXT,
//	             spr.BESCHREIBUNG,
//                a.FIELD01,
//                a.FIELD02,
//                a.FIELD03,
//                a.FIELD04,
//                a.FIELD05,
//                a.FIELD06,
//                a.FIELD07,
//                a.FIELD08,
//                a.FIELD09,
//                a.FIELD10,
//                a.FIELD11,
//                a.FIELD12,
//                a.FIELD13,
//                a.FIELD14,
//                a.FIELD15,
//                a.FIELD16,
//                a.FIELD17,
//                a.FIELD18,
//                a.FIELD19,
//                a.FIELD20,
//                a.NE_BASIS,
//                a.NE_BASIS_WAEHRUNGSCODE,
//                a.NE_CODE,
//                a.NE_GEWICHT,
//                a.PICTURE,
//                a.PREISEINHEIT,
//                a.U_FIELD01,
//                a.U_ARTIKELNR,
//                a.QUANTITYFORMULA,
//                a.ANGELEGTAM,
//                a.VKKLEINSTEEINHEIT,
//                a.VKKLEINSTEEINHEITME,
//                dbo.VERKAUFSKONDITIONEN.LISTENPREIS,
//                dbo.VERKAUFSKONDITIONEN.RABATT,
//                dbo.VERKAUFSKONDITIONEN.RABATT2,
//                dbo.VERKAUFSKONDITIONEN.GUELTIGAB,
//                dbo.VERKAUFSKONDITIONEN.GUELTIGBIS,
//                a.BASICPRICEUOQ,
//
//  (SELECT STRING_AGG(g.ZUORDNUNGSNR, ', ')
//   FROM dbo.gruppen AS g
//   WHERE g.ZUORDNUNGSNR IN
//(SELECT ZUORDNUNGSNR
//        FROM dbo.ARTIKELGRUPPENZUORDNUNG
//        WHERE ARTIKELNR = a.ARTIKELNR)) AS all_groups
//FROM dbo.ARTIKEL AS a
//INNER JOIN dbo.VERKAUFSKONDITIONEN ON a.ARTIKELNR = dbo.VERKAUFSKONDITIONEN.ARTIKELNR
//LEFT OUTER JOIN dbo.ARTIKELGRUPPENZUORDNUNG AS agz ON agz.ARTIKELNR = a.ARTIKELNR
//LEFT OUTER JOIN dbo.GRUPPEN AS g ON g.ZUORDNUNGSNR = agz.ZUORDNUNGSNR
//AND g.GRUPPENZUORDNUNG <> ''
//LEFT OUTER JOIN dbo.ARTIKELSPRACHENZUORDNUNG AS spr ON spr.ARTIKELNR = a.ARTIKELNR
//WHERE ((spr.SPRACHENCODE LIKE 'DEM')
//       AND (g.BEZEICHNUNG LIKE 'WEB % ')
//       AND (a.ARTIKELNR IS NOT null)
//       AND (dbo.VERKAUFSKONDITIONEN.STANDARTCONDITIONEN = 1)
//OR (spr.SPRACHENCODE LIKE 'DEM')
//       AND (g.BEZEICHNUNG LIKE 'WEB % ')
//       AND (a.ARTIKELNR IS NOT null)
//       AND (dbo.VERKAUFSKONDITIONEN.STANDARTCONDITIONEN = 1)
//OR (a.ARTIKELNR IN
//(SELECT DISTINCT articlenr
//              FROM dbo.ws_productassign))) -- AND spr.KURZTEXT LIKE ' >>%' ; ;
//
