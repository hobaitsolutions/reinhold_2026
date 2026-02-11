<?php declare(strict_types=1);

namespace QuickOrder\Storefront\Controller;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Test\Product\Repository\ProductRepositoryTest;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Doctrine\DBAL\Connection;

/**
 * @RouteScope(scopes={"storefront"})
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class QuickOrderController extends StorefrontController
{
	private AccountOverviewPageLoader $overviewPageLoader;
	private NavigationPageLoader $pageLoader;
	private SalesChannelRepository $productRepo;
	private EntityRepository $fullProductRepo;
	private EntityRepository $orderRepo;
	private EntityRepository $ruleRepo;
	private EntityRepository $priceRepo;
	private EntityRepository $ruleConditionRepo;

	public function __construct(
		AccountOverviewPageLoader $overviewPageLoader,
		NavigationPageLoader      $pageLoader,
		EntityRepository          $orderRepo,
		SalesChannelRepository    $productRepo,
		EntityRepository          $fullProductRepo,
		EntityRepository          $ruleRepo,
		EntityRepository          $ruleConditionRepo,
		EntityRepository          $priceRepo,
		Connection                $connection)
	{
		$this->overviewPageLoader = $overviewPageLoader;
		$this->pageLoader         = $pageLoader;
		$this->productRepo        = $productRepo;
		$this->fullProductRepo    = $fullProductRepo;
		$this->orderRepo          = $orderRepo;
		$this->connection         = $connection;
		$this->ruleRepo           = $ruleRepo;
		$this->priceRepo          = $priceRepo;
		$this->ruleConditionRepo  = $ruleConditionRepo;
	}

	#[Route(path: '/quickorder', name: 'frontend.quickorder.quickorder', defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true], methods: ['GET'])]
	public function showQuickorder(Request $request, SalesChannelContext $context, ?CustomerEntity $customer = null): Response
	{
//		$page = $this->overviewPageLoader->load($request, $context, $customer);
//
//		$products = $this->getOrderProducts($page->getCustomer(), $context);
//
//		return $this->renderStorefront('@QuickOrder/storefront/page/quickorder.html.twig', [
//				'page' => $page, 'products' => $products
//			]
//		);

		return self::showQuickorderDeliveries($request, $context, $customer);
	}

	/**
	 * @LoginRequired()
	 * @Route("/quickorder/deliveries", name="frontend.quickorder.deliveries", methods={"GET"})
	 */
	#[Route(path: '/quickorder/deliveries', name: 'frontend.quickorder.deliveries', methods: ['GET'], defaults: ['_routeScope' => 'storefront'])]
	public function showQuickorderDeliveries(Request $request, SalesChannelContext $context, ?CustomerEntity $customer = null): Response
	{
		$page = $this->overviewPageLoader->load($request, $context, $customer);
		$res  = $this->getProductsByDelivery($customer);


		return $this->renderStorefront('@QuickOrder/storefront/page/quickorder_deliveries.html.twig', [
				'page'          => $page,
				'products'      => $res['products'],
				'adresslist'    => $res['adresslist'],
				'undeliverable' => $res['undeliverable']
			]
		);
	}


	/**
	 * @LoginRequired()
	 * @Route("/quickorder/conditions", name="frontend.quickorder.conditions", methods={"GET"})
	 */
	#[Route(path: '/quickorder/conditions', name: 'frontend.quickorder.conditions', methods: ['GET'], defaults: ['_routeScope' => 'storefront'])]
	public function showQuickorderConditions(Request $request, SalesChannelContext $context, ?CustomerEntity $customer = null): Response
	{
		$page = $this->overviewPageLoader->load($request, $context, $customer);

		$products = $this->getProductsByCustomerRules($customer, $context);

		return $this->renderStorefront('@QuickOrder/storefront/page/quickorder_conditions.html.twig', [
				'page' => $page, 'products' => $products
			]
		);
	}

	/**
	 * @LoginRequired()
	 * @Route("/quickorder/table", name="frontend.quickorder.table", methods={"GET"})
	 */
	#[Route(path: '/quickorder/table', name: 'frontend.quickorder.table', methods: ['GET'], defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
	public function showQuickorderTable(Request $request, SalesChannelContext $context, ?CustomerEntity $customer = null): Response
	{
		$page  = $this->overviewPageLoader->load($request, $context, $customer);
		$title = $page->getMetaInformation()->getMetaTitle();
		$page->getMetaInformation()->setMetaTitle('Verkaufstagebuch - ' . $title);
		$result = $this->getProductsByOrders($customer, $context);


		return $this->renderStorefront('@QuickOrder/storefront/page/quickorder_table.html.twig', [
				'page' => $page, 'products' => $result['products'], 'allorders' => $result['orders']
			]
		);
	}

	/**
	 * @Route("/customsearch", name="frontend.quickorder.table", methods={"GET"})
	 */
	#[Route(path: '/customsearch', name: 'frontend.quickorder.search', methods: ['GET'], defaults: ['_routeScope' => ['storefront']])]
	public function customsearch(Request $request, SalesChannelContext $context): Response
	{
		$page  = $this->pageLoader->load($request, $context);
		$title = $page->getMetaInformation()->getMetaTitle();
		$page->getMetaInformation()->setMetaTitle('Suche - ' . $title);

		$query        = $_GET['query'] ?? '';
		$queryResults = $this->getProductsByQuery($query);

		// Extract IDs while preserving order
		$orderedIds = [];
		foreach ($queryResults as $item)
		{
			if (!in_array($item['id'], $orderedIds))
			{
				$orderedIds[] = $item['id'];
			}
		}

		// Get full product data
		$products = $this->getProductsByIds($orderedIds, $context);

		// Reorder products to match the original query results order
		$orderedProducts = [];
		foreach ($orderedIds as $id)
		{
			foreach ($products as $product)
			{
				if ($product->getId() === $id)
				{
					$orderedProducts[] = $product;
					break;
				}
			}
		}

		$result = $orderedProducts;

		if (isset($_GET['json']) && $_GET['json'] == 'true')
		{
			return $this->json(array('success' => true, 'data' => $result));
		}

		return $this->renderStorefront('@QuickOrder/storefront/page/searchresults.html.twig', [
				'page' => $page, 'products' => $result, 'queryResults' => $queryResults
			]
		);
	}

	protected static function interpolateQuery($query, $params)
	{
		foreach ($params as $param)
		{
			$value = is_numeric($param) ? $param : "'" . addslashes($param) . "'";
			$query = preg_replace('/\?/', $value, $query, 1);
		}

		return $query;
	}


	/**
	 * @param string $query
	 *
	 * @return array
	 * @throws \Doctrine\DBAL\Exception
	 */
	protected function getProductsByQuery(string $query): array
	{
		// Split the query by spaces to get individual words
		$queryWords = explode(' ', $query);

		// Filter out words that are less than 4 characters
		$queryWords = array_filter($queryWords, function ($word) {
			return strlen($word) >= 4;
		});

		// If no valid words remain, return empty array
		if (empty($queryWords))
		{
			return [];
		}



		// Build the SQL query with weighted search
		$sql = 'SELECT
				    p.product_number,
				    lower(hex(p.id)) AS id,
				    pt.name AS name,
				    (';

		// Calculate relevance score based on weighted matches
		$relevanceCalc = [];
		$params        = [];

		foreach ($queryWords as $index => $word)
		{
			// Weight 10 for description
			$relevanceCalc[] = 'IF(pt.description LIKE CONCAT("%", ?, "%"), (SELECT ranking FROM product_search_config_field WHERE field = "description" AND product_search_config_id = 0x32e66a6b761f4781a6103fc9456457bc), 0)';
			$params[]        = $word;

			// Weight 20 for name
			$relevanceCalc[] = 'IF(pt.name LIKE CONCAT("%", ?, "%"), (SELECT ranking FROM product_search_config_field WHERE field = "name" AND product_search_config_id = 0x32e66a6b761f4781a6103fc9456457bc), 0)';
			$params[]        = $word;

			// Weight 15 for custom_fields
			$relevanceCalc[] = 'IF(pt.custom_fields LIKE CONCAT("%", ?, "%"), (SELECT ranking FROM product_search_config_field WHERE field = "categories.customFields" AND product_search_config_id = 0x32e66a6b761f4781a6103fc9456457bc), 0)';
			$params[]        = $word;

			// Weight 50 for product_number
			$relevanceCalc[] = 'IF(p.product_number LIKE CONCAT("%", ?, "%"), (SELECT ranking FROM product_search_config_field WHERE field = "productNumber" AND product_search_config_id = 0x32e66a6b761f4781a6103fc9456457bc) , 0)';
			$params[]        = $word;
		}

		// Sum all relevance scores
		$sql .= implode(' + ', $relevanceCalc);
		$sql .= ') AS relevance_score
				FROM
				    product p
				INNER JOIN
				    product_translation pt ON p.id = pt.product_id
				LEFT JOIN
				    product_media pm ON p.id = pm.product_id AND pm.position = 0
				WHERE
				    (';

		$conditions = [];

		// Create conditions for each word
		foreach ($queryWords as $word)
		{
			$conditions[] = '(pt.description LIKE CONCAT("%", ? , "%")
				        OR pt.name LIKE CONCAT("%", ? , "%")
				        OR pt.custom_fields LIKE CONCAT("%", ? , "%")
				        OR p.product_number LIKE CONCAT("%", ? , "%"))';
			$params[]     = $word;
			$params[]     = $word;
			$params[]     = $word;
			$params[]     = $word;
		}

		// Join conditions with AND to find products matching all of the words
		$sql .= implode(' AND ', $conditions);
		$sql .= ' AND pt.language_id = (SELECT id FROM language WHERE name = "Deutsch" LIMIT 1))
				AND product_number NOT LIKE "#%"
				HAVING relevance_score > 0
				ORDER BY relevance_score DESC
				LIMIT 100';
//
//		$interpolatedQuery = self::interpolateQuery($sql, $params);
//		echo($interpolatedQuery);


		$sqlResult = $this->connection->executeQuery($sql, $params);

		return $sqlResult->fetchAllAssociative();
	}

	/**
	 * Get product ids of historic orders
	 *
	 * @param string $customernumber
	 * @param string $until how long to go back in time
	 *
	 * @return array
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function getHistoricOrderProductIds(string $customernumber, string $until = '-10 years'): array
	{
//		$customernumber = '20095';
		$time       = strtotime($until, time());
		$date       = date('Y-m-d', $time);
		$sqlQuery   = 'SELECT * FROM `hobait_order_history` as h WHERE (h.customer =' . $customernumber . ') AND (h.date > "' . $date . '")';
		$sqlResult  = $this->connection->executeQuery($sqlQuery);
		$productIds = [];

		foreach ($sqlResult->fetchAllAssociative() as $result)
		{
			$products = json_decode($result['products']);
			foreach ($products as $product)
			{
				if (!empty($product->id))
				{
					$productIds[$product->id] = true;
				}
			}
		}

		return array_keys($productIds);
	}

	/**
	 * Get products ordered by a customer
	 *
	 * @param CustomerEntity $customer
	 *
	 * @return object
	 *
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function getOrderProducts(CustomerEntity $customer, $context): object
	{
		$productIds = [];

		$orderCriteria = (new Criteria())->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()))->addAssociation('lineItems');
		$orders        = $this->orderRepo->search($orderCriteria, Context::createDefaultContext())->getEntities();

		foreach ($orders as $order)
		{
			foreach ($order->lineItems as $lineItem)
			{
				$productIds[$lineItem->getProductId()] = true;
			}
		}
		$productIds = array_merge(array_keys($productIds), $this->getHistoricOrderProductIds($customer->getCustomerNumber()));

		return $this->getProductsByIds($productIds, $context);
	}

	/**
	 * Get prduct entities by id
	 *
	 * @param array $productIds
	 *
	 * @return object
	 */
	public function getProductsByIds(array $productIds, $context): object
	{
		if (empty($productIds))
		{
			$productIds[] = '00000000000000000000000000000000'; //just throw an empty id, null means all products!
		}
		$productsCriteria = (new Criteria($productIds))
			->addAssociation('cover')
			->addAssociation('media')
			->addAssociation('mediaThumbnailSizes')
			->addAssociation('thumbnails')
			->addAssociation('prices')
			->addFilter(new EqualsAnyFilter('active', [true, false]))
			->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));

		$products = $this->productRepo->search($productsCriteria, $context)->getEntities();

		return $products;
	}

	/**
	 * get all products including inactive
	 *
	 * @param array $productIds
	 *
	 * @return object|\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
	 */
	public function getFullProductsByIds(array $productIds): object
	{

		if (empty($productIds))
		{
			$productIds[] = '00000000000000000000000000000000'; //just throw an empty id, null means all products!
		}
		$productsCriteria = (new Criteria($productIds))
			->addAssociation('cover')
			->addAssociation('media')
			->addAssociation('mediaThumbnailSizes')
			->addAssociation('thumbnails')
			->addAssociation('prices')
			->addSorting(new FieldSorting('product.name', FieldSorting::ASCENDING));

		return $this->fullProductRepo->search($productsCriteria, Context::createDefaultContext())->getEntities();
	}

	/**
	 * @param Criteria $criteria
	 *
	 * @return array
	 */
	private function getRuleConditionsByCriteria(Criteria $criteria): array
	{
		$ids            = [];
		$ruleConditions = $this->ruleConditionRepo->search($criteria, Context::createDefaultContext())->getEntities();

		foreach ($ruleConditions as $ruleCondition)
		{
			$ids[] = $ruleCondition->ruleId;
		}

		return $ids;
	}

	/**
	 * Get product ids by customer rules
	 *
	 * @param CustomerEntity $customer
	 *
	 * @return array
	 */
	private function getProductIdsByCustomerRules(CustomerEntity $customer): array
	{
		$defaultContext = Context::createDefaultContext();
		$ids            = [];

		$individual = (new Criteria())->addFilter(
			new AndFilter([
				new EqualsFilter('type', 'customerCustomerNumber'),
				new ContainsFilter('value', '"' . $customer->getCustomerNumber() . '"') //equals filter on value.numbers with array returns strange error
			]));

		$group = (new Criteria())->addFilter(
			new AndFilter([
				new EqualsFilter('type', 'customerCustomerGroup'),
				new ContainsFilter('value', '"' . $customer->getGroupId() . '"') //equals filter on value.numbers with array returns strange error
			]));

		$ids = array_merge($this->getRuleConditionsByCriteria($individual), $this->getRuleConditionsByCriteria($group));

		$rules = $this->ruleRepo->search(new Criteria($ids), $defaultContext)->getEntities();
		$ids   = [];
		foreach ($rules as $rule)
		{
			$ids[] = $rule->id;
		}

		$criteria = (new Criteria())->addFilter(new EqualsAnyFilter('ruleId', $ids));
		$prices   = $this->priceRepo->search($criteria, $defaultContext)->getEntities();
		$ids      = [];
		foreach ($prices as $price)
		{
			$ids[] = $price->productId;
		}

		return $ids;
	}

	/**
	 * Get products by customer rules
	 *
	 * @param CustomerEntity $customer
	 *
	 * @return object
	 */
	private function getProductsByCustomerRules(CustomerEntity $customer, $context): object
	{
		$products = $this->getProductIdsByCustomerRules($customer);

		return $this->getProductsByIds($products, $context);
	}

	/**
	 * Get products by customer rules
	 *
	 * @param CustomerEntity $customer
	 *
	 * @return object
	 */
	private function getProductsByOrders(CustomerEntity $customer, $context): array
	{

		$productIds = $allOrders = $notInShop = [];

//		$orderCriteria = (new Criteria())->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()))->addAssociation('lineItems');
//		$orders        = $this->orderRepo->search($orderCriteria, Context::createDefaultContext())->getEntities();
//
//		foreach ($orders as $order)
//		{
//			$orderNumber                            = $order->getOrderNumber();
//			$allOrders[$orderNumber]['orderNumber'] = $orderNumber;
//			$allOrders[$orderNumber]['date']        = $order->getOrderDate();
//			$allOrders[$orderNumber]['items']       = [];
//			foreach ($order->lineItems as $lineItem)
//			{
//				$allOrders[$orderNumber]['items'][$lineItem->getProductId()] = ['count' => $lineItem->getQuantity(), 'id' => $lineItem->getProductId()];
//				$productIds[$lineItem->getProductId()]                       = true;
//			}
//		}

		$customernumber = $customer->getCustomerNumber();
		$sqlQuery       = 'SELECT * FROM `hobait_order_history` as h WHERE (h.customer =' . $customernumber . ')';
		$sqlResult      = $this->connection->executeQuery($sqlQuery);
		foreach ($sqlResult->fetchAllAssociative() as $result)
		{
			$orderNumber                            = $result['documentnumber'];
			$allOrders[$orderNumber]['orderNumber'] = $orderNumber;
			$allOrders[$orderNumber]['date']        = $result['date'];
			$allOrders[$orderNumber]['items']       = [];
			$products                               = json_decode($result['products']);

			foreach ($products as $product)
			{
				if (empty($product->id))
				{
					$notInShop[$product->n] = $product;
				}
				else
				{
					$allOrders[$orderNumber]['items'][$product->id] = ['count' => $product->q, 'id' => $product->id, 'price' => $product->p, 'unit' => $product->u, 'discount1' => $product->d, 'discoount2' => $product->d1, 'name' => $product->t];
					$productIds[$product->id]                       = true;
				}
			}
		}
		//@todo later -- products not in shop cant be displayed correctly, due to hazards etc.
		$products      = $this->getFullProductsByIds(array_keys($productIds));
		$productsTable = [];
		foreach ($products as $p)
		{
			$p          = (object) $p;
			$p->hazards = 'keine';
			if (!empty($p->customFields['custom_product_hazards']))
			{
				$p->hazards = json_decode($p->customFields['custom_product_hazards']);
				if (is_array($p->hazards))
				{
					$p->hazards = implode(', ', $p->hazards);
				}
				else
				{
					$p->hazards = 'keine';
				}
			}
			$productsTable[$p->getId()] = $p;
		}
		ksort($allOrders);

		return ['orders' => $allOrders, 'products' => $productsTable];
	}

	/**
	 * @param CustomerEntity $customer
	 * @param                $context
	 *
	 * @return array|object
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function getProductsByDelivery(CustomerEntity $customer): array
	{
		$productIds = [];

		$customernumber = $customer->getCustomerNumber();
		$sqlQuery       = 'SELECT *, (SELECT lower(hex(id)) FROM product WHERE product_number = h.ARTIKELNR) as id FROM `hobait_pleasant_deliveries` as h WHERE (h.CLIENTNUMBER =' . $customernumber . ')';
		$sqlResult      = $this->connection->executeQuery($sqlQuery);
		$addresslist    = [];
		$undeliverable  = [];
		foreach ($sqlResult->fetchAllAssociative() as $result)
		{
			$address = $result['NAME12'] . ' ' . $result['NAME22'] . ', ' . $result['STREETPOSTOFFICE2'] . ', ' . $result['CITY2'];
			if (empty($addresslist[$address]))
			{
				$addresslist[$address] = [];
			}
			$addresslist[$address][] = $result['ARTIKELNR'];
			if (!empty($result['id']))
			{
				$productIds[$result['id']] = 1;
			}
			else
			{
				$undeliverable[$result['ARTIKELNR']] = [
					'title'          => explode("\n", $result['KURZTEXT'])[0],
					'price'          => (float) $result['NETPRICEPERUNIT'],
					'product_number' => $result['ARTIKELNR']
				];
			}
		}
		$products = $this->getFullProductsByIds(array_keys($productIds));

		return ['products' => $products, 'adresslist' => $addresslist, 'undeliverable' => $undeliverable];
	}


	/**
	 * @LoginRequired()
	 * @Route("/pleasant/orders", name="frontend.quickorder.table", methods={"GET"})
	 */
	#[Route(path: '/pleasant/orders', name: 'frontend.quickorder.pleasantorders', methods: ['GET'], defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
	public function pleasantOrdres(Request $request, SalesChannelContext $context, ?CustomerEntity $customer = null): Response
	{
		return $this->renderStorefront('@Reinhold/storefront/page/account/historic-orders.html.twig', [
			'customer' => $customer->getCustomerNumber(),
		]);
	}

}
