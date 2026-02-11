<?php
require_once(__DIR__ . '/lib/PDO.class.php');
require_once(__DIR__ . '/../db/dbHandler.php');

class MySQLDBInterface extends MysqlDB
{
	public function __construct(string $db = 'sw6')
	{
		parent::__construct('shop.reinhold-sohn-hygiene.de', 3306, $db, 'sw6', 'qoN26r_84rW49n59y');
	}

	public function updateHistoricOrders()
	{
		$productsIds = self::getAllProductIds();
		$lastSync    = new DateTime(self::query('SELECT lastsync FROM  hobait_sync WHERE synctype = "order_history"')[0]['lastsync']);
		$lastSync->sub(DateInterval::createFromDateString('2 days'));
		$mssqldb = new DBInterface();
		$orders  = $mssqldb->getHistoricOrders($lastSync->format('Ymd'));
		//self::query('UPDATE hobait_sync SET lastsync = ? WHERE synctype = "order_history"', [date('Y-m-d H:i:s')]);
		$sql = 'INSERT INTO hobait_order_history (`documentnumber`, `products`, `customer`, `date`) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE documentnumber=documentnumber;';


		foreach ($orders as &$o)
		{
			$products = json_decode($o['ARTICLES']);
			foreach ($products as &$product)
			{
				$articleNumber = $product->n;
				if (!empty($productsIds[$articleNumber]))
				{
					$product->id = $productsIds[$articleNumber];
				}
				else
				{
					$product->id = null;
				}

			}
			$o['ARTICLES'] = json_encode($products);
			self::query($sql, array_values($o));
			unset($o);
		}
	}

	/**
	 * Get historic orders per customer
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	public function getHistoricOrdersByCustomerId(string $id): array
	{
		$sql = 'SELECT * FROM hobait_order_history WHERE customer = ? order by DATE DESC';

		return self::query($sql, [$id]);
	}


	/**
	 * Get historic orders per customer
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	public function getHistoricDeliveriesByCustomerId(string $id): array
	{
		$sql = 'SELECT * FROM hobait_pleasant_deliveries WHERE CLIENTNUMBER = ? order by  SHIPPINGDATE DESC, ITEMNUMBER ASC';

		return self::query($sql, [$id]);
	}

	/**
	 * Get all product id
	 * @return array
	 */
	public function getAllProductIds(): array
	{
		$sql = "SELECT LOWER(HEX(id)) as product_id, product_number FROM  product";;

		$results     = self::query($sql);
		$allProducts = [];

		foreach ($results as $result)
		{
			$allProducts[$result['product_number']] = $result['product_id'];
		}

		return $allProducts;
	}


	/**
	 * Get product ids of historic orders
	 *
	 * @param $order
	 *
	 * @return array
	 */
	public function getHistoricOrderProductIds($order)
	{
		$sql    = 'SELECT * FROM hobait_order_history WHERE documentnumber = ?';
		$result = self::query($sql, [$order]);
		$ids    = [];
		foreach ($result as $res)
		{
			$products = json_decode($res['products']);
			foreach ($products as $product)
			{
				if (!empty($product->id))
				{
					$ids[] = $product->id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Delete product prices the hard way (sorry)
	 *
	 * @param string $productId
	 */
	public function deleteProductPricesHard(string $productId)
	{
		$sql = 'DELETE FROM product_price WHERE lower(hex(product_id)) = ?';
		self::query($sql, [$productId]);
	}

	/**
	 * @param string $customerNumber
	 *
	 * @return array
	 */
	public function getAllHistoricOrderProductIdsByCustomerNumber(string $customerNumber): array
	{
		$sql    = 'SELECT * FROM hobait_order_history WHERE customer = ?';
		$result = self::query($sql, [$customerNumber]);
		$ids    = [];
		foreach ($result as $res)
		{
			$products = json_decode($res['products']);
			foreach ($products as $product)
			{
				if (!empty($product->id))
				{
					$ids[] = $product->id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Add a temporary rule condition
	 *
	 * @param string $pleasantId
	 * @param string $swRuleId
	 * @param string $validUntil
	 */
	public function addTemporaryRuleCondition(string $pleasantId, string $swRuleId, string $validUntil): void
	{
		echo "adding condition \n";
		$sql = 'INSERT INTO hobait_temporary_price_rules (`pleasant_row_id`, `sw_rule_id`, `valid_until`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE pleasant_row_id=pleasant_row_id;';
		self::query($sql, [$pleasantId, $swRuleId, $validUntil]);
	}

	/**
	 * Get price rule ids which are no longer valid
	 * @return array
	 */
	public function getInvalidTemoraryPriceRules(): array
	{
		$sql  = 'SELECT `sw_rule_id` FROM hobait_temporary_price_rules WHERE `valid_until` < ?';
		$rows = self::query($sql, [date('Y-m-d')]);
		$ids  = [];
		foreach ($rows as $row)
		{
			$ids[] = $row['sw_rule_id'];
		}

		return $ids;
	}

	/**
	 * Delete invalid price rules;
	 */
	public function deleteInvalidPriceRules(): void
	{
		$sql = 'DELETE FROM hobait_temporary_price_rules WHERE `valid_until` < ?';
		self::query($sql, [date('Y-m-d')]);
	}


	/**
	 * Get last sync dates
	 *
	 * @param string $type enum('products','customers','orders','addresses','custom_prices','groups','categories','customer_groups')
	 *
	 * @return string
	 */
	public function getLastSync(string $type = 'products'): string
	{
		$sql = 'SELECT lastsync FROM hobait_sync WHERE synctype = ?';

		return self::single($sql, [$type]);
	}

	/**
	 * Set last sync dates
	 *
	 * @param string $type enum('products','customers','orders','addresses','custom_prices','groups','categories','customer_groups')
	 * @param ?int   $date
	 */
	public function setLastSync(string $type = 'products', ?int $date = null): void
	{
		$sql = 'UPDATE hobait_sync SET lastsync = ? WHERE synctype = ?';
		if (empty($date))
		{
			$date = time();
		}
		$date = date('Y-m-d H:i:s', $date);
		self::query($sql, [$date, $type]);
	}

	/**
	 * @param string $date
	 *
	 * @return array|int|null
	 */
	public function getOutdatedProducts(string $date)
	{

		$sql = 'SELECT HEX(t.product_id), JSON_VALUE(t.custom_fields, "$.custom_product_lastupdate") AS updated_at, p.product_number AS product_number
				FROM product_translation AS t
				LEFT JOIN product AS p 
				ON p.id = t.product_id
				WHERE  
				JSON_VALUE(custom_fields, "$.custom_product_lastupdate") < ?
				OR  JSON_VALUE(custom_fields, "$.custom_product_lastupdate") IS NULL
				order BY product_number desc';

		return self::query($sql, [$date]);
	}

	/**
	 * Get all product numbers
	 * @return array
	 */
	public function getAllProductNumbers()
	{
		$sql = 'SELECT product_number FROM product';

		return array_column($this->query($sql), 'product_number');
	}

	/**
	 * Get product numbers of products w/o image
	 * @return array
	 */
	public function getProductNumbersOfProductsWithoutImage()
	{
		return array_column(self::query('SELECT product_number FROM product WHERE product_media_id IS NULL AND product_number NOT RLIKE "^[#]"'), 'product_number'); //skip one time products
	}

	/**
	 * @return array
	 */
	public function getSalutations()
	{
		$salutations = [];
		$sql         = "SELECT lower(hex(salutation_id)) as id, display_name FROM salutation_translation WHERE language_id = 0x2fbb5fe2e29a4d70aa5854ce7ce3e20b";
		$result      = self::query($sql);
		foreach ($result as $res)
		{
			$salutations[$res['id']] = $res['display_name'];
		}

		return $salutations;
	}
}

