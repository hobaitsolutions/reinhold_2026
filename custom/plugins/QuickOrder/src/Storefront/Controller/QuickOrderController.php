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
	#[Route(path: '/quickorder/deliveries', name: 'frontend.quickorder.deliveries', methods: ['GET'], defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
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
	#[Route(path: '/quickorder/conditions', name: 'frontend.quickorder.conditions', methods: ['GET'], defaults: ['_routeScope' => ['storefront'], '_loginRequired' => true])]
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

		$limit = (int) $request->query->get('limit', 50);
		$p = (int) $request->query->get('p', 1);
		$offset = ($p - 1) * $limit;

		$sort = $request->query->get('sort', 'date');
		$order = $request->query->get('order', 'desc');

		$result = $this->getProductsByOrders($customer, $context, $limit, $offset, $sort, $order);

		return $this->renderStorefront('@QuickOrder/storefront/page/quickorder_table.html.twig', [
				'page' => $page,
				'products' => $result['products'],
				'allorders' => $result['orders'],
				'limit' => $limit,
				'p' => $p,
				'total' => $result['total'],
				'sort' => $sort,
				'order' => $order
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

        $query = $request->query->get('query', '');
        $limit = 50;
        $p = max(1, (int) $request->query->get('p', 1));
        $offset = ($p - 1) * $limit;

        $search = $this->getProductsByQuery($query, $limit, $offset, $context);
        $queryResults = $search['rows'];
        $total = (int) $search['total'];

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
        $productsMap = [];
        foreach ($products as $product) {
            $productsMap[$product->getId()] = $product;
        }

        $orderedProducts = [];
        foreach ($orderedIds as $id) {
            if (isset($productsMap[$id])) {
                $orderedProducts[] = $productsMap[$id];
            }
        }

        $result = $orderedProducts;

        if ($request->query->get('json') === 'true')
        {
            return $this->json(array('success' => true, 'data' => $result, 'total' => $total, 'limit' => $limit, 'p' => $p));
        }

        return $this->renderStorefront('@QuickOrder/storefront/page/searchresults.html.twig', [
                'page' => $page,
                'products' => $result,
                'queryResults' => $queryResults,
                'total' => $total,
                'limit' => $limit,
                'p' => $p,
                'query' => $query
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
     * @param int $limit
     * @param int $offset
     * @param SalesChannelContext $context
     *
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getProductsByQuery(string $query, int $limit = 50, int $offset = 0, ?SalesChannelContext $context = null): array
    {
        // Split the query by spaces to get individual words
        $queryWords = explode(' ', $query);

		// Filter out words that are less than 4 characters
		$queryWords = array_filter($queryWords, function ($word) {
			return strlen($word) >= 4;
		});

  // If no valid words remain, return an empty result set with zero total
  if (empty($queryWords))
  {
      return ['rows' => [], 'total' => 0];
  }

		// Pre-fetch ranking values to avoid N+1 subqueries in the SQL
		$rankingSql = 'SELECT field, ranking FROM product_search_config_field WHERE product_search_config_id = 0x32e66a6b761f4781a6103fc9456457bc';
		$rankings = $this->connection->fetchAllKeyValue($rankingSql);

		$rankingDescription = (int) ($rankings['description'] ?? 0);
		$rankingName = (int) ($rankings['name'] ?? 0);
		$rankingCustomFields = (int) ($rankings['categories.customFields'] ?? 0);
		$rankingProductNumber = (int) ($rankings['productNumber'] ?? 0);

		// Build the SQL query with weighted search
		$sql = 'SELECT
				    p.product_number,
				    lower(hex(p.id)) AS id,
				    pt.name AS name,
				    (';

		// Optimize relevance calculation and filtering
		$relevanceCalc = [];
		$params        = [];

		foreach ($queryWords as $word) {
			$relevanceCalc[] = '(CASE 
				WHEN pt.name LIKE CONCAT("%", ?, "%") THEN ' . $rankingName . '
				WHEN p.product_number LIKE CONCAT("%", ?, "%") THEN ' . $rankingProductNumber . '
				WHEN pt.description LIKE CONCAT("%", ?, "%") THEN ' . $rankingDescription . '
				WHEN pt.custom_fields LIKE CONCAT("%", ?, "%") THEN ' . $rankingCustomFields . '
				ELSE 0 
			END)';
			$params[] = $word;
			$params[] = $word;
			$params[] = $word;
			$params[] = $word;
		}

		$sql .= implode(' + ', $relevanceCalc);
		$sql .= ') AS relevance_score
				FROM
				    product p
				INNER JOIN
				    product_translation pt ON p.id = pt.product_id
				WHERE
				    (';

		$conditions = [];

		foreach ($queryWords as $word) {
			$conditions[] = '(pt.name LIKE CONCAT("%", ?, "%")
				        OR p.product_number LIKE CONCAT("%", ?, "%")
				        OR pt.description LIKE CONCAT("%", ?, "%")
				        OR pt.custom_fields LIKE CONCAT("%", ?, "%"))';
			$params[] = $word;
			$params[] = $word;
			$params[] = $word;
			$params[] = $word;
		}

		$sql .= implode(' AND ', $conditions);

        $languageId = $context ? $context->getContext()->getLanguageId() : null;
        if ($languageId) {
            $sql .= ' AND pt.language_id = UNHEX(?)';
            $params[] = $languageId;
        } else {
            $sql .= ' AND pt.language_id = (SELECT id FROM language WHERE name = "Deutsch" LIMIT 1)';
        }

        $sql .= ')
                AND p.product_number NOT LIKE "#%"
                AND p.active = 1
                GROUP BY p.id
                HAVING relevance_score > 0';

        // Build total count query
        $countSql = 'SELECT COUNT(*) as cnt FROM (' . $sql . ') as subq';
        $countResult = $this->connection->executeQuery($countSql, $params)->fetchAssociative();
        $total = (int) ($countResult['cnt'] ?? 0);

        // Apply ordering and pagination for rows
        $pagedSql = $sql . ' ORDER BY relevance_score DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $sqlResult = $this->connection->executeQuery($pagedSql, $params);

        return ['rows' => $sqlResult->fetchAllAssociative(), 'total' => $total];
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
		$sqlQuery   = 'SELECT products FROM `hobait_order_history` WHERE customer = :customer AND date > :date';
		$sqlResult  = $this->connection->executeQuery($sqlQuery, [
			'customer' => $customernumber,
			'date' => $date
		]);
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

		$orderCriteria = (new Criteria())
			->addFilter(new EqualsFilter('orderCustomer.customerId', $customer->getId()))
			->addAssociation('lineItems');

		$orders = $this->orderRepo->search($orderCriteria, Context::createDefaultContext())->getEntities();

		foreach ($orders as $order)
		{
			if ($order->lineItems === null) {
				continue;
			}

			foreach ($order->lineItems as $lineItem)
			{
				$productId = $lineItem->getProductId();
				if ($productId !== null) {
					$productIds[$productId] = true;
				}
			}
		}

		$historicProductIds = $this->getHistoricOrderProductIds($customer->getCustomerNumber());
		foreach ($historicProductIds as $id) {
			$productIds[$id] = true;
		}

		return $this->getProductsByIds(array_keys($productIds), $context);
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

		// Optimize rule detection using JSON_CONTAINS to avoid Full-Table-Scans with LIKE
		$sql = '
			SELECT DISTINCT rule_id 
			FROM rule_condition 
			WHERE (type = "customerCustomerNumber" AND JSON_CONTAINS(value, JSON_QUOTE(:customerNumber), "$.numbers"))
			   OR (type = "customerCustomerGroup" AND JSON_CONTAINS(value, JSON_QUOTE(:groupId), "$.customerGroupIds"))
		';

		$ruleIds = $this->connection->fetchFirstColumn($sql, [
			'customerNumber' => $customer->getCustomerNumber(),
			'groupId' => $customer->getGroupId()
		]);

		if (empty($ruleIds)) {
			return [];
		}

		// Convert binary IDs to hex strings for the PriceRepository
		$hexRuleIds = array_map(function($id) {
			return bin2hex($id);
		}, $ruleIds);

		$criteria = (new Criteria())->addFilter(new EqualsAnyFilter('ruleId', $hexRuleIds));
		$prices   = $this->priceRepo->search($criteria, $defaultContext)->getEntities();

		$productIds = [];
		foreach ($prices as $price)
		{
			$productIds[] = $price->productId;
		}

		return array_unique($productIds);
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
	 * @return array
	 */
	private function getProductsByOrders(CustomerEntity $customer, $context, int $limit = 100, int $offset = 0, string $sort = 'date', string $order = 'desc'): array
	{
		$productIds = $allOrders = $notInShop = [];
		$customernumber = $customer->getCustomerNumber();

		// Fetch all orders for the customer to flatten products and paginate them
		$sqlQuery = 'SELECT * FROM `hobait_order_history` WHERE customer = :customer ORDER BY date DESC';
		$sqlResult = $this->connection->executeQuery($sqlQuery, [
			'customer' => $customernumber
		]);

		$allFlattenedProducts = [];
		while ($result = $sqlResult->fetchAssociative())
		{
			$products = json_decode($result['products']);
			if (!is_array($products)) {
				continue;
			}

			foreach ($products as $product) {
				$allFlattenedProducts[] = [
					'order' => [
						'orderNumber' => $result['documentnumber'],
						'date' => $result['date']
					],
					'product' => $product
				];
			}
		}

		// Persistent sorting
		usort($allFlattenedProducts, function ($a, $b) use ($sort, $order) {
			$valA = null;
			$valB = null;

			switch ($sort) {
				case 'orderNumber':
					$valA = $a['order']['orderNumber'];
					$valB = $b['order']['orderNumber'];
					break;
				case 'date':
					$valA = $a['order']['date'];
					$valB = $b['order']['date'];
					break;
				case 'productNumber':
					$valA = $a['product']->n ?? '';
					$valB = $b['product']->n ?? '';
					break;
				case 'name':
					$valA = $a['product']->t ?? '';
					$valB = $b['product']->t ?? '';
					break;
				case 'count':
					$valA = (float)($a['product']->q ?? 0);
					$valB = (float)($b['product']->q ?? 0);
					break;
				case 'price':
					$valA = (float)($a['product']->p ?? 0);
					$valB = (float)($b['product']->p ?? 0);
					break;
				default:
					$valA = $a['order']['date'];
					$valB = $b['order']['date'];
			}

			if ($valA == $valB) {
				return 0;
			}

			if ($order === 'asc') {
				return ($valA < $valB) ? -1 : 1;
			} else {
				return ($valA > $valB) ? -1 : 1;
			}
		});

		$total = count($allFlattenedProducts);
		$paginatedProducts = array_slice($allFlattenedProducts, $offset, $limit);

		$finalOrders = [];
		$productIds = [];

		foreach ($paginatedProducts as $item) {
			$orderNumber = $item['order']['orderNumber'];
			if (!isset($finalOrders[$orderNumber])) {
				$finalOrders[$orderNumber] = [
					'orderNumber' => $orderNumber,
					'date' => $item['order']['date'],
					'items' => []
				];
			}

			$product = $item['product'];
			if (empty($product->id)) {
				$notInShop[$product->n] = $product;
			} else {
				$finalOrders[$orderNumber]['items'][$product->id] = [
					'count' => $product->q,
					'id' => $product->id,
					'price' => $product->p,
					'unit' => $product->u,
					'discount1' => $product->d,
					'discoount2' => $product->d1,
					'name' => $product->t
				];
				$productIds[$product->id] = true;
			}
		}

		//@todo later -- products not in shop cant be displayed correctly, due to hazards etc.
		$products      = $this->getFullProductsByIds(array_keys($productIds));
		$productsTable = [];
		foreach ($products as $p)
		{
			$p->hazards = 'keine';
			if (!empty($p->customFields['custom_product_hazards']))
			{
				$hazards = json_decode($p->customFields['custom_product_hazards']);
				if (is_array($hazards))
				{
					$p->hazards = implode(', ', $hazards);
				}
				else
				{
					$p->hazards = 'keine';
				}
			}
			$productsTable[$p->getId()] = $p;
		}

		return ['orders' => $finalOrders, 'products' => $productsTable, 'total' => $total];
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
		$sqlQuery       = 'SELECT *, (SELECT lower(hex(id)) FROM product WHERE product_number = h.ARTIKELNR LIMIT 1) as id FROM `hobait_pleasant_deliveries` as h WHERE (h.CLIENTNUMBER = :customerNumber)';
		$sqlResult      = $this->connection->executeQuery($sqlQuery, ['customerNumber' => $customernumber]);
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
