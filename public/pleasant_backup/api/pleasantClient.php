<?php

namespace hobaIT;

use GuzzleHttp\Exception\GuzzleException;
use Opis\JsonSchema\Formats\Date;
use PleasantSync\PleasantSync;


require_once('client.php');

class pleasantClient extends APIClient
{
	private $db;
	private $rules = [];

	public function __construct()
	{
		parent::__construct();
		require_once(__DIR__ . '/../mysql/dbHandler.php');

		$this->db = new \MySQLDBInterface();
	}

	/**
	 * Retrieves a category object based on a specified pleasant internal ID.
	 *
	 * @param string $id The pleasant internal ID used to filter categories.
	 *
	 * @return object Returns the category object matching the provided ID.
	 * @throws GuzzleException
	 */
	public function getCategoryByPleasantInternalId(string $id): object
	{
		return $this->getCategoryByFilter(
			new filter(
				[
					new filterCriteria('customFields.category_pleasant_internal_id', $id)
				]
			)
		);
	}

	/**
	 * Retrieves a category object based on its pleasant ID.
	 *
	 * @param string $id The pleasant ID of the category to retrieve.
	 *
	 * @return object Returns the category object matching the provided pleasant ID.
	 * @throws GuzzleException
	 */
	public function getCategoryByPleasantId(string $id): object
	{
		return $this->getCategoryByFilter(
			new filter(
				[
					new filterCriteria('customFields.category_pleasant_id', $id)
				]
			)
		);
	}

	/**
	 * Retrieves and organizes category data into a structured array.
	 *
	 * This method processes the output of the `getCategories` method
	 * (assumed to return JSON data), extracting relevant category details
	 * and organizing them into an associative array indexed by the
	 * category's internal pleasant ID.
	 *
	 * @return array Returns an array of categories, where each entry contains:
	 *               - 'pleasant_id': The pleasant ID of the category.
	 *               - 'id': The unique identifier of the category.
	 *               - 'name': The name of the category.
	 *               - 'breadcrumb': A string representing the hierarchical
	 *                 breadcrumb path for the category.
	 * @throws GuzzleException
	 */
	public function getCategoriesArray(): array
	{
		$items = json_decode($this->getCategories());
		$list  = [];
		if ($items->total > 0)
		{
			foreach ($items->data as $i)
			{
				if (!empty($i->customFields->category_pleasant_internal_id))
				{
					$list[$i->customFields->category_pleasant_internal_id] = [
						'pleasant_id' => $i->customFields->category_pleasant_id,
						'id'          => $i->id,
						'name'        => $i->name,
						'breadcrumb'  => implode(' > ', $i->breadcrumb)
					];
				}
			}
		}
//		var_dump($list);
//		die();

		return $list;
	}

	/**
	 * Retrieves and organizes datasheet information for a specific order.
	 *
	 * @param string $id The ID of the order to retrieve datasheets for.
	 *
	 * @return array|null Returns an array of datasheets indexed by product ID,
	 *                    containing product details if datasheets exist; otherwise, null.
	 * @throws GuzzleException
	 */
	public function getDatasheetsForOrder(string $id): ?array
	{
		$result = self::getOrderItems($id);
		$ids    = [];
		foreach ($result as $r)
		{
			$ids[] = $r->productId;
		}
		$products = self::getMultipleProducts($ids);
		if ($products->total > 0)
		{
			return self::getDatasheetsFromProductArray($products->data);
		}

		return null;
	}

	/**
	 * Retrieves datasheet information for a historic order based on its ID.
	 *
	 * @param string $id The ID of the historic order.
	 *
	 * @return array|null Returns an array of datasheets if products are found for the order,
	 *                    or null if no products are associated with the order.
	 * @throws GuzzleException
	 */
	public function getDatasheetsForHistoricOrder(string $id): ?array
	{

		$result   = $this->db->getHistoricOrderProductIds($id);
		$products = self::getMultipleProducts($result);
		if ($products->total > 0)
		{
			return self::getDatasheetsFromProductArray($products->data);
		}

		return null;
	}

	/**
	 * Retrieves datasheet information for all orders made by the customer.
	 *
	 * This method gathers all ordered product IDs by the customer and provides the associated datasheets.
	 *
	 * @return array Returns an array containing the datasheets for all ordered products.
	 * @throws GuzzleException
	 */
	public function getDatasheetsForAllOrders()
	{
		return $this->getAllOrderedProductIdsByCustomer();
	}

	/**
	 * Extracts and organizes datasheet information from an array of products.
	 *
	 * @param array $products Array of product objects to process.
	 *
	 * @return array Returns an array of datasheets indexed by product ID,
	 *               containing product name, product number, and datasheets.
	 */
	public function getDatasheetsFromProductArray(array $products): array
	{
		$datasheets = [];
		foreach ($products as $product)
		{
			if (!empty($product->customFields))
			{
				if (!empty($product->customFields->custom_product_datasheets) && $product->customFields->custom_product_datasheets != '[]')
				{
					$datasheets[$product->id] = [
						'name'       => $product->name,
						'number'     => $product->productNumber,
						'datasheets' => $product->customFields->custom_product_datasheets
					];
				}
			}
		}

		return $datasheets;
	}

	/**
	 * Create a ZIP archive from an array of datasheets.
	 *
	 * @param string $filename    The name of the ZIP file to create.
	 * @param array  $productList An array of products containing datasheet information.
	 *
	 * @return string|null Returns the filename of the created ZIP archive, or null if the product list is empty.
	 */
	public function createZipFromDatasheetArray(string $filename, array $productList): ?string
	{
		if (empty($productList)) return null;

		if (!file_exists($filename) || time() - filemtime($filename) < 7200)
		{
			@unlink($filename); //delete old archive

			$zip     = new \ZipArchive();
			$basedir = '/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/';
			$types   = [
				'Produktinformation',
				'Betriebsanweisung',
				'Sicherheitsdatenblatt',
				'RKI',
				'VAH',
				'ÖKO',
				'Öko'
			];

			if ($zip->open($filename, \ZipArchive::CREATE) !== true)
			{
				exit('cannot open <' . $filename . '>\n');
			}
			foreach ($productList as $product)
			{
				$number = $product['number'];
				$name   = preg_replace("/[^A-Za-z0-9 ]/", '', self::replaceUmlaute($product['name']));
				$files  = json_decode($product['datasheets']);

				$foldername  = $number . '_' . $name;
				$folderAdded = false;

				foreach ($files as $path => $file)
				{
					if ($file != str_replace($types, '', $file)) //skip if not of a given type
					{
						if ($folderAdded == false)
						{
							$zip->addEmptyDir($foldername);
							$folderAdded = true;
						}
						$currentFile = $basedir . $path;
						$fileInfo    = pathinfo($currentFile);
						$newname     = $foldername . '/' . $fileInfo['basename'];
						$zip->addFile($currentFile, $newname);
					}
				}
			}
			$zip->close();
		}

		return $filename;
	}

	/**
	 * Replace German umlauts in a given string with their corresponding ASCII representation.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public function replaceUmlaute(string $name)
	{
		$search  = ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß'];
		$replace = ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss'];

		return str_replace($search, $replace, $name);
	}

	/**
	 * Create a datasheet ZIP file for a specific order
	 *
	 * @param string $order The identifier of the order
	 *
	 * @return string|null The path to the created ZIP file or null on failure
	 * @throws GuzzleException
	 */
	public function createDatasheetZipForOrder(string $order): ?string
	{
		return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/downloads/order-' . $order . '.zip', self::getDatasheetsForOrder($order));
	}

	/**
	 * Create a datasheet zip file for a historic order
	 *
	 * @param string $order
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function createDatasheetZipForHistoricOrder(string $order): ?string
	{
		return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/downloads/order-' . $order . '.zip', self::getDatasheetsForHistoricOrder($order));
	}

	/**
	 * Fetches a customer group by a given Pleasant ID
	 *
	 * @param string $id
	 *
	 * @return mixed|null
	 * @throws GuzzleException
	 */
	public function getCustomerGroupByPleasantId(string $id)
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('customFields.custom_group_zuordnungsnr', $id)
				]
			);
			$response = json_decode(self::post('search/customer-group', $filter));
		}
		catch (\Exception $e)
		{
			die('Request failed ' . $e->getMessage());
		}

		return $response->data[0] ?? null;
	}

	/**
	 * Add a customer group
	 *
	 * @param customergroup $customergroup
	 *
	 * @return mixed
	 * @throws GuzzleException
	 */
	public function addCustomerGroup(customergroup $customergroup)
	{
		$check = self::getCustomerGroupByPleasantId($customergroup->getZurodnungsNr());
		if (empty($check))
		{
			return parent::addCustomerGroup($customergroup);
		}

		return $check->id;
	}

	/**
	 * Retrieve customer by personal number
	 *
	 * @param string $personennr
	 *
	 * @return object
	 * @throws GuzzleException
	 */
	public function getCustomerByPersonennr(string $personennr): object
	{
		return $this->getCustomerByFilter(
			new filter(
				[
					new filterCriteria('customFields.custom_customer_personennr', $personennr)
				]
			)
		);
	}

	/**
	 * Add a rule with a specific condition
	 *
	 * @param string      $type       The type of the condition
	 * @param string      $value      The value for the condition
	 * @param string      $name       The name of the rule
	 * @param string|null $property   The property associated with the value (optional)
	 * @param string      $personennr The identifier for the rule (default is empty)
	 *
	 * @return mixed The decoded response from the rule creation request
	 * @throws GuzzleException
	 */
	public function addRuleWithCondition(string $type, string $value, string $name, ?string $property = null, string $personennr = '')
	{
		try
		{
			$rc = new ruleCondition();
			$rc->setType($type);
			$rc->setValue(
				new value('=', [$value], $property)
			);

			$rule = new rule($personennr);
			$rule->setName($name);
			$rule->setConditions([$rc]);

			$response = json_decode(self::post('rule?_response=basic', $rule));

			return $response;
		}
		catch (\Exception $e)
		{
			die('Could not add rule ' . $e->getMessage());
		}

	}

	/**
	 * Add a customer individual rule condition
	 *
	 * @param string $customerNumber The customer's number to apply the rule to
	 * @param string $ruleName       The name of the rule, defaulting to 'Kundennummer: '
	 * @param string $personennr     Optional personal number
	 *
	 * @return string|null The ID of the created rule or null if no new rule was added
	 * @throws GuzzleException
	 */
	public function addCustomerIndividualRuleCondition(string $customerNumber, string $ruleName = 'Kundennummer: ', string $personennr = ''): ?string
	{
		$check = self::getCustomerIndividualRuleCondition($customerNumber);
		if (empty($check))
		{
			try
			{
				$response = self::addRuleWithCondition('customerCustomerNumber', $customerNumber, $ruleName . $customerNumber, null, $personennr);

				return $response->data->id ?? null;
			}
			catch (\Exception $e)
			{
				die('Could not add rule ' . $e->getMessage());
			}
		}

		return $check;
	}


	/**
	 * Prepare a rule based on the provided parameters.
	 *
	 * @param string      $type       The type of the rule.
	 * @param string      $value      The value associated with the rule.
	 * @param string      $ruleName   The name of the rule, default is 'Kundennummer: '.
	 * @param string|null $personennr The personal number for the rule, default is an empty string.
	 * @param string|null $id         The ID of the rule, default is null.
	 * @param string|null $property   The property associated with the value, default is null.
	 *
	 * @return rule|null Returns the prepared rule if it does not already exist, null otherwise.
	 */
	public function prepareRule(string $type, string $value, string $ruleName = 'Kundennummer: ', ?string $personennr = '', string $id = null, ?string $property = null): ?rule
	{
//		$client->prepareRule(
//			'customerCustomerNumber',
//			$customer->getCustomerNumber(),
//			'Kunde: (' . $customer->getPersonennr() . ') :' . $customer->getCustomerNumber(),
//			$customer->getPersonennr(),
//			$customerRuleId);
//		$client->prepareRule(
//			'customerCustomerGroup',
//			$groupId,
//			$gName,
//			'',
//			$groupId,
//			'customerGroupIds');

		if (empty(self::checkRuleExists($type, $ruleName)))
		{
			$rc = new ruleCondition();
			$rc->setType($type);
			$rc->setValue(
				new value('=', [$value], $property)
			);

			$rule = new rule($personennr);
			$rule->setName($ruleName);
			$rule->setConditions([$rc]);

			if (!empty($id))
			{
				$rule->setIdFromPleasant($id);
			}

			return $rule;
		}

		return null;

	}

	/**
	 * Check if a specific rule exists based on type and name
	 *
	 * @param string $type The type of the rule to check
	 * @param string $name The name of the rule to check
	 *
	 * @return string|null Returns the rule if it exists, otherwise null
	 */
	public function checkRuleExists(string $type, string $name): ?string
	{
		self::getExistingRules();

		return @$this->rules[$type][$name]['rule'] ?? null;
	}

	/**
	 * Retrieve existing rules and populate the rules array
	 *
	 * If the force parameter is true or the rules array is empty, fetches the rules
	 * from the database and processes them into a structured format.
	 *
	 * @param bool $force Option to force fetching the rules even if they are already loaded
	 *
	 * @return void
	 */
	public function getExistingRules(bool $force = false): void
	{
		if (empty($this->rules) || $force)
		{
			$query = 'SELECT lower(HEX(rc.id)) AS id, 
       				LOWER(HEX(rc.rule_id)) AS rule_id, rc.`type`,  
       				JSON_EXTRACT(rc.`value`, "$.customerGroupIds") AS customer_group,
				    JSON_EXTRACT(rc.`value`, "$.numbers") AS customer_number, r.name
				 FROM rule_condition rc
				 LEFT JOIN rule r ON r.id = rc.rule_id';

			$result = $this->db->query($query);
			foreach ($result as $item)
			{
				$type = $item['type'];
				$name = $item['name'];

				if (empty($this->rules[$type]))
				{
					$this->rules[$type] = [];
				}
				$val = 0;
				if ($type == 'customerCustomerNumber')
				{
					$val = json_decode($item['customer_number'])[0];
				}
				else if ($type == 'customerCustomerGroup')
				{
					$val = json_decode($item['customer_group'])[0];
				}
				$this->rules[$type][$name] = [
					'rule'  => $item['rule_id'],
					'id'    => $item['id'],
					'name'  => $name,
					'value' => $val
				];
			}
		}
	}

	/**
	 * Retrieve all ordered product IDs associated with a specific customer ID
	 *
	 * @param string $customerId
	 *
	 * @return array
	 * @throws GuzzleException
	 */
	public function getAllOrderedProductIdsByCustomer(string $customerId): array
	{
		$customerNumber = $this->getCustomerNumberById($customerId);
		$ids            = $this->db->getAllHistoricOrderProductIdsByCustomerNumber($customerNumber);

		return array_merge(parent::getAllOrderedProductIdsByCustomer($customerId), $ids);
	}

	/**
	 * Get all product IDs associated with a customer group
	 *
	 * @param string $customerGroupId
	 *
	 * @return array
	 * @throws GuzzleException
	 */
	public function getAllCustomerGroupProductIds(string $customerGroupId)
	{
		$products  = [];
		$client    = new pleasantClient();
		$customers = $client->getCustomerGroupMembers($customerGroupId);

		foreach ($customers as $customerId)
		{
			$products = array_merge(self::getAllOrderedProductIdsByCustomer($customerId), $products);
		}

		return array_unique($products);
	}

	/**
	 * Create a datasheet ZIP file for a specific customer group.
	 *
	 * @param string $customerGroupId The ID of the customer group.
	 *
	 * @return string|null The path of the created ZIP file or null if no products are found.
	 * @throws GuzzleException
	 */
	public function createDatasheetZipForCustomerGroup(string $customerGroupId): ?string
	{
		$ids      = self::getAllCustomerGroupProductIds($customerGroupId);
		$products = (self::getMultipleProducts($ids));
		if ($products->total > 0)
		{
			return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/downloads/' . $customerGroupId . '.zip', self::getDatasheetsFromProductArray($products->data));
		}

		return null;
	}

	/**
	 * Create a datasheet ZIP file for all products purchased by a customer
	 *
	 * @param string $customerId The ID of the customer
	 *
	 * @return string|null The path to the created ZIP file or null if no products are found
	 * @throws GuzzleException
	 */
	public function createDatasheetZipForAllBoughtProducts(string $customerId): ?string
	{
		$ids      = self::getAllOrderedProductIdsByCustomer($customerId);
		$products = (self::getMultipleProducts($ids));
		if ($products->total > 0)
		{
			return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/downloads/cust-' . $customerId . '.zip', self::getDatasheetsFromProductArray($products->data));
		}

		return null;
	}

	/**
	 * Create a ZIP file for the specified product
	 *
	 * @param string $productId
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function createProductZip(string $productId): ?string
	{
		$products = (self::getMultipleProducts([$productId]));

		if ($products->total > 0)
		{
			return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/pleasant/downloads/product-' . $productId . '.zip', self::getDatasheetsFromProductArray($products->data));
		}

		return null;
	}

	/**
	 * Create a datasheet zip file for all customer conditions
	 *
	 * @param string $customerId The ID of the customer
	 *
	 * @return string|null Path of the created zip file or null if no datasheet exists
	 * @throws GuzzleException
	 */
	public function createDatasheetZipForAllCustomerConditions(string $customerId): ?string
	{
		$customer = self::getCustomer($customerId);
		$rules    = [];
		$rules[]  = self::getCustomerIndividualRuleCondition($customer->customerNumber) ?? 1; //suppress errors if null
		$rules[]  = self::getCustomergroupIndividualRuleCondition($customer->groupId) ?? 1; //suppress errors
		$prices   = self::getPricesByRules($rules);

		$ids = [];
		foreach ($prices as $price)
		{
			$ids[] = $price->productId;
		}
		$products = (self::getMultipleProducts($ids));
		if ($products->total > 0)
		{
			return self::createZipFromDatasheetArray('/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/downloads/cond-' . $customerId . '.zip', self::getDatasheetsFromProductArray($products->data));
		}

		return null;
	}

	/**
	 * Get an associative array of product IDs indexed by product numbers
	 *
	 * @return array
	 */
	public function getProductIdArray()
	{
		$result   = $this->db->query('SELECT lower(hex(id)) as id, product_number FROM product', []);
		$products = [];
		foreach ($result as $res)
		{
			$products[$res['product_number']] = $res['id'];
		}

		return $products;
	}

	/**
	 * Get an array of product IDs with their associated prices
	 *
	 * @param array $productIds
	 *
	 * @return array
	 *
	 * @throws \Exception If an error occurs during the process
	 */
	public function getProductIdArrayWithPrices(array $productIds)
	{
		try
		{
			$filter     = '{
			"filter": [
		            { 
		                "type": "multi",
		                "operator": "and",
		                "queries":[
		                   { "type": "equals", "field": "active", "value": true },
		                    {"type": "equalsAny", "field": "productNumber", "value": ' . json_encode($productIds) . ' }
		                ]
		            }
	            ],
	               "includes": {
			        "product": ["id", "name", "price", "productNumber", "cheapestPrice"]
			    }
	        }';
			$filter     = json_decode($filter);
			$response   = json_decode(self::post('search/product', $filter));
			$priceArray = [];

			foreach ($response->data as $product)
			{
				$price                               = @$product->cheapestPrice->price[0]->net ?? $product->price[0]->net ?? -100000;
				$priceArray[$product->productNumber] = [
					'price' => $price,
					'id'    => $product->id
				];
			}

			return $priceArray;
		}
		catch (\Exception $e)
		{
			die('Could not get prices');
		}
	}

	/**
	 * Retrieve an associative array of customer IDs indexed by customer numbers
	 *
	 * @return array
	 */
	public function getCustomerIdArray()
	{
		$result    = $this->db->query('SELECT lower(hex(id)) as id, customer_number FROM customer', []);
		$customers = [];
		foreach ($result as $res)
		{
			$customers[$res['customer_number']] = $res['id'];
		}

		return $customers;
	}

	/**
	 * Sanitize the database by removing unused customer group entries.
	 *
	 * @return void
	 */
	public function sanitizeDB()
	{
		$this->db->query('DELETE FROM customer_group WHERE id NOT IN (SELECT customer_group_id FROM  customer)'); //remove
	}
}
