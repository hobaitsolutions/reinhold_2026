<?php

namespace Reinhold\Twig;

use Doctrine\DBAL\Connection;
use hobaIT\filter;
use hobaIT\filterCriteria;
use hobaIT\pleasantClient;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HistoricOrders extends AbstractExtension
{


	private EntityRepository $productRepo;
	private Connection $connection;
	private EntityCollection $products;

	public function __construct(EntityRepository $productRepo, Connection $connection)
	{
		$this->productRepo = $productRepo;
		$this->connection  = $connection;
	}

	public function getFilters()
	{
		return [
			new TwigFilter('historicOrders', [$this, 'historicOrders']),
			new TwigFilter('historicDeliveries', [$this, 'historicDeliveries']),
		];
	}

	/**
	 * Print hazards from json encoded string
	 *
	 *
	 * @return string
	 */
	public function historicOrders($filters)
	{
		$customer = '';

		foreach ($filters as $filter)
		{
			if ($filter->getField() == "order.orderCustomer.customerId")
			{
				$customer = $filter->getValue();
			}
		}
//		return '';

		require_once getcwd() . '/pleasant/mysql/dbHandler.php';

		$db             = new \MySQLDBInterface();
		$customerNumber = $db->query('SELECT customer_number FROM customer WHERE LOWER(HEX(id)) = ?', [$customer])[0]['customer_number'];
		$orders         = $db->getHistoricOrdersByCustomerId($customerNumber);
		//get products via API
		require_once getcwd() . '/pleasant/api/pleasantClient.php';
		$client = new \hobaIT\pleasantClient();

		return self::getHTML($orders);
	}

	/**
	 * @param $filters
	 *
	 * @return string
	 */
	public function historicDeliveries($filters)
	{
//		return '';

		require_once getcwd() . '/pleasant/mysql/dbHandler.php';

		$db             = new \MySQLDBInterface();

		$allorders = ($db->getHistoricDeliveriesByCustomerId($filters['customer']));

		$groups = [];

		$offset =  $filters['offset'] ?? 0;
		$pagesize= 100;

		foreach ($allorders as $order)
		{
			$date = $order['SHIPPINGDATE'];
			$doc  = $order['DOCUMENTNUMBER'];

			$article = new \article($order['ARTIKELNR'], $order['QUANTITY'], $order['MENGENEINHEIT'], $order['NETPRICEPERUNIT'], $order['DISCOUNTP1'], '', $order['NETSURCHARGE'], $order['ITEMTEXT']);;

			if (empty($groups[$doc]))
			{
				$groups[$doc]                                     = [];
				$groups[$doc]['date']                             = $date;
				$groups[$doc]['deliveries']                       = [];
				$groups[$doc]['doc']                              = $doc;
				$groups[$doc]['address']                          = $order['NAME12'] . ' ' . $order['NAME22'] . ', ' . $order['STREETPOSTOFFICE2'] . ', ' . $order['CITY2'];
				$groups[$doc]['deliveries'][$order['ITEMNUMBER']] = $article;
			}
			else
			{
				$groups[$doc]['deliveries'][] = $article;
			}
		}

		return self::getDeliveryHtml(array_slice($groups, $offset * $pagesize, $pagesize , true));
	}

	/**
	 * @param array $ids
	 *
	 * @return \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
	 */
	public function getProductsByIds(array $productIds): object
	{
		return $this->productRepo->search((new Criteria($productIds))->addAssociation('cover')->addAssociation('media')->addAssociation('thumbnails'), Context::createDefaultContext())->getEntities();
	}

	/**
	 * @param array $ids
	 *
	 * @return array
	 */
	public function getProductIdNameArray(array $ids): array
	{
		$products = self::getProductsByIds($ids);
		$array    = [];

		foreach ($products as $product)
		{
			$thumb = '';
			$cover = $product->getCover();
			if ($cover)
			{
				$thumb = $cover->getMedia()->getUrl();
			}
			$array[$product->id] = ['name' => $product->name, 'thumb' => $thumb];
		}

		return $array;
	}

	/**
	 * @param $orders
	 *
	 * @return string
	 */
	public function getHTML($orders)
	{
		$html = '<table style="width: 100%" class="order-table-wrapper"><thead><tr><th></th></tr></thead><tbody>';

		$ids = [];
		foreach ($orders as $order)
		{
			$products = json_decode($order['products']);
			foreach ($products as $product)
			{
				$ids[$product->id] = 1;
			}
		}

		$nameArray = self::getProductIdNameArray(array_keys($ids));


		foreach ($orders as $order):

			$ordernumber = preg_replace("/[^A-Za-z0-9]/", '', $order['documentnumber']);
			$date        = date('d.m.Y', strtotime($order['date']));
			$products    = json_decode($order['products']);
			$html        .= '<tr><td>
			<div class="table order-table" data-order-detail-loader="true">
				<div class="order-wrapper">
					<div class="order-item-header">
						<div class="row flex-wrap">
							<h5 class="col-12 order-table-header-heading">Bestellung: ' . $date . '</h5>
							<div class="col-12 order-table-header-order-number">
								<span class="order-table-header-label">Bestellnummer:</span>
								<span class="order-table-body-value">' . $ordernumber . '</span>
							</div>
							<div class="col-12 order-table-header-order-status">
								<h5>
									Status:
									abgeschlossen
								</h5>
							</div>
						</div>
						<div class="row">
							<div class="col p-0">
								<a href="#" data-order-id="' . $ordernumber . '"
								   class="btn btn-sm btn-primary download-order-datasheets">Datenblätter herunterladen</a>
							</div>
							<div class="col p-0 text-right">
								<button class="btn btn-light btn-sm order-hide-btn collapsed" type="submit"
								        data-toggle="collapse"
								        data-target="#hist-' . $ordernumber . '" aria-expanded="false" aria-controls="collapseExample">
									<span class="order-view-btn-text">Anzeigen</span>
									<span class="order-hide-btn-text">Verbergen</span>
								</button>
							</div>
						</div>
					</div>
				</div>
				<div class="order-item-detail">
					<div class="collapse" id="hist-' . $ordernumber . '">
						<div class="order-detail-content">
								<div class="p-4">
								<div class="row font-weight-bold">
									<div class="col-12 col-md-3 order-detail-content-header-cell order-header-image">
										Bild
									</div>
									<div class="col-12 col-md-2 order-detail-content-header-cell order-header-product-number">
										Artikelnummer
									</div>
									<div class="col-12 col-md-4 order-detail-content-header-cell order-header-name">
										Produkt
									</div>
									<div class="col-12 col-md-1 order-detail-content-header-cell order-header-quantity">
										Anzahl
									</div>
									<div class="col-12 col-md-1 order-detail-content-header-cell order-header-quantity">
										Einzelpreis*
									</div>
								</div>';
			$total       = 0;
			foreach ($products as $product):
				$curProd = @$nameArray[$product->id] ?? [];

				$html .= '<div class="row">';
				$html .= '<div class="col-12 col-md-3 product-image">';
				if (!empty($curProd['thumb']))
				{
					$html .= '<img style="max-width: 150px;" loading="lazy" src="' . $curProd['thumb'] . '" />';
				};
				$html .= '</div>';
				$html .= '<div class="col-12 col-md-2 order-item order-item-number">' . $product->n . '</div>';
				$html .= '<div class="col-12 col-md-4 order-item order-item-name">';
				if (!empty($product->id))
				{
					$name = @$curProd['name'] ?? 'nicht mehr verfügbar';
					$html .= '<a href=" ' . "{{ seoUrl('frontend.detail.page', {'productId':'" . $product->id . "' }) }}" . '"> <strong class="name-value">' . $name . '</strong></a>';
				}
				else
				{
					$html .= '<strong class="name-value">nicht mehr bestellbar</strong>';

				}
				$html  .= '</div>
							<div class="col-12 col-md-1 order-item order-item-quantity">
								' . $product->q . '
							</div>
							<div class="col-12 col-md-1 order-item order-item-price">
								' . $product->p . '
							</div>
						</div>';
				$total += $product->q * $product->p;
			endforeach;

			$html .= '</div>
						<div class="order-detail-content-footer">
							<div class="order-item-detail-footer">
								<div class="row no-gutters">
									<div class="col-12 col-md-7 col-xl-6">
										<dl class="row no-gutters order-item-detail-labels">
											<dt class="col-6 col-md-5">Bestelldatum:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . $date . '</dd>
											<dt class="col-6 col-md-5">Bestellnummer:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . $ordernumber . '</dd>
										</dl>
									</div>
									<div class="col-12 col-md-5 col-xl-6">
										<dl class="row no-gutters order-item-detail-labels">
											<dt class="col-6 col-md-5">Gesamtsumme:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . number_format($total, 2) . ' EUR *</dd>
										</dl>
									</div>
								</div>
										<em class="muted text-small">* zzgl. USt. | Abweichung von der Rechnung möglich, individuelle Rabatte, Lieferkosten und Aufschläge können fehlen</em>
							</div>
						</div>
					</div>
				</div>
			</div>
</div>
</td></tr>';
		endforeach;

		return $html . '</tbody></table><br><br><br>';
	}

	public function getDeliveryHtml($deliveries)
	{
		$html    = '<table style="width: 100%" class="order-table-wrapper"><thead><tr><th></th></tr></thead><tbody>';
		$client  = new pleasantClient();
		$idArray = $client->getProductIdArray();


		$ids = [];
		foreach ($deliveries as $delivery)
		{
			$products = $delivery['deliveries'];
			foreach ($products as $product)
			{
				$val = @$idArray[$product->n] ?? false;
				if ($val !== false)
				{
					$ids[$val] = 1;
				}
			}
		}

		$nameArray = self::getProductIdNameArray(array_keys($ids));

		foreach ($deliveries as $order):
			$ordernumber = $order['doc'];
			$date        = date('d.m.Y', strtotime($order['date']));
			$products    = $order['deliveries'];
			$state = 'geliefert';
			if (strtotime($order['date']) > strtotime('now')){
				$state = 'in Auslieferung';
			}
			$html  .= '<tr><td>
			<div class="table order-table" data-order-detail-loader="true">
				<div class="order-wrapper">
					<div class="order-item-header">
						<div class="row flex-wrap">
							<div class="col-12 p-0 pt-4"><h4 class="d-block">Lieferung: '. $ordernumber. ' vom ' . $date . '</h4></div>
							<div class="col-12 order-table-header-order-number">
								<span class="order-table-header-label">Adresse:</span>
								<span class="order-table-body-value">' . $order['address'] . '</span>
							</div>
							<div class="col-12 order-table-header-order-status">
								<h5>
									Status:
									'.$state.'
								</h5>
							</div>
						</div>
						<div class="row">
							<div class="col p-0">
								<!--<a href="#" data-order-id="' . $ordernumber . '"
								   class="btn btn-sm btn-primary download-order-datasheets">Datenblätter herunterladen</a>-->
							</div>
							<div class="col p-0 text-right">
								<button class="btn btn-light btn-sm order-hide-btn collapsed" type="submit"
								        data-bs-toggle="collapse"
								        data-bs-target="#hist-' . $ordernumber . '" aria-expanded="false" aria-controls="collapseExample">
									<span class="order-view-btn-text">Anzeigen</span>
									<span class="order-hide-btn-text">Verbergen</span>
								</button>
							</div>
						</div>
					</div>
				</div>
				<div class="order-item-detail">
					<div class="collapse" id="hist-' . $ordernumber . '">
						<div class="order-detail-content">
								<div class="p-4">
								<div class="row font-weight-bold">
									<div class="col-12 col-md-3 order-detail-content-header-cell order-header-image">
										Bild
									</div>
									<div class="col-12 col-md-2 order-detail-content-header-cell order-header-product-number">
										Artikelnummer
									</div>
									<div class="col-12 col-md-4 order-detail-content-header-cell order-header-name">
										Produkt
									</div>
									<div class="col-12 col-md-1 order-detail-content-header-cell order-header-quantity">
										Anzahl
									</div>
									<div class="col-12 col-md-1 order-detail-content-header-cell order-header-quantity">
										Einzelpreis*
									</div>
								</div>';
			$total = 0;
			foreach ($products as $product):
				$product->id = @$idArray[$product->n];
//				if (empty($product->id))
//				{
//					continue;
//				}
				$curProd = @$nameArray[$product->id] ?? [];

				$html .= '<div class="row" style="align-items:center ">';
				$html .= '<div class="col-12 col-md-3 product-image">';
				if (!empty($curProd['thumb']))
				{
					$html .= '<img style="max-width: 150px;" loading="lazy" src="' . $curProd['thumb'] . '" />';
				};
				$html .= '</div>';
				$html .= '<div class="col-12 col-md-2 order-item order-item-number">' . $product->n . '</div>';
				$html .= '<div class="col-12 col-md-4 order-item order-item-name">';
				if (!empty($product->id))
				{
					$name = @$curProd['name'] ?? 'nicht mehr verfügbar';
					$html .= '<a href=" ' . "{{ seoUrl('frontend.detail.page', {'productId':'" . $product->id . "' }) }}" . '"> <strong class="name-value">' . $name . '</strong></a>';
				}
				else
				{
					$html .= '<strong class="name-value">' . ((explode("\n", $product->t)[0]) ?? 'aktuell nicht lieferbar') . '</strong>';
				}
				$html .= '</div>
							<div class="col-12 col-md-1 order-item order-item-quantity">
								' . $product->q . '
							</div>
							<div class="col-12 col-md-1 order-item order-item-price d-inline-flex align-content-center align-items-center">
								' . str_replace('.', ',', number_format($product->p, 2)) . '€  ';
				if (!empty($product->id))
				{
					$html .= '<form action="/checkout/line-item/add" method="post" class="ml-3 buy-widget d-inline-block" data-add-to-cart="true">
                                <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas"  data-form-csrf-handler="false">
                                <input type="hidden"
                                       name="lineItems[' . $product->id . '][id]"
                                       value="' . $product->id . '">
                                <input type="hidden"
                                       name="lineItems[' . $product->id . '][referencedId]"
                                       value="' . $product->id . '">
                                <input type="hidden"
                                       name="lineItems[' . $product->id . '][type]"
                                       value="product">
                                <input type="hidden"
                                       name="lineItems[' . $product->id . '][stackable]"
                                       value="1">
                                <input type="hidden"
                                       name="lineItems[' . $product->id . '][removable]"
                                       value="1">
                                <button class="btn btn-buy"
                                        title="hinzufuegen">
                                    {% sw_icon"bag" %}
                                </button>
                            </form>';
				}
				$html .= '
							</div>
						</div>';

				$total += $product->q * $product->p;
			endforeach;

			$html .= '</div>
						<div class="order-detail-content-footer">
							<div class="order-item-detail-footer">
								<div class="row no-gutters">
									<div class="col-12 col-md-7 col-xl-6">
										<dl class="row no-gutters order-item-detail-labels">
											<dt class="col-6 col-md-5">Bestelldatum:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . $date . '</dd>
											<dt class="col-6 col-md-5">Bestellnummer:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . $ordernumber . '</dd>
										</dl>
									</div>
									<div class="col-12 col-md-5 col-xl-6">
										<dl class="row no-gutters order-item-detail-labels">
											<dt class="col-6 col-md-5">Gesamtsumme:</dt>
											<dd class="col-6 col-md-7 order-item-detail-labels-value">' . number_format($total, 2) . ' EUR *</dd>
										</dl>
									</div>
								</div>
										<em class="muted text-small">* zzgl. USt. | Abweichung von der Rechnung möglich, individuelle Rabatte, Lieferkosten und Aufschläge können fehlen</em>
							</div>
						</div>
					</div>
				</div>
			</div>
</div>
</td></tr>';
		endforeach;

		return $html . '</tbody></table><br><br><br>';
	}

}
