<?php declare(strict_types=1);

namespace PleasantSync\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Product\ProductEvents;


class ProductLoadSubscriber implements EventSubscriberInterface
{
	private EntityRepository $orderRepo;

	public function __construct(
		EntityRepository $orderRepo,
		Connection                $connection
	)
	{
		$this->orderRepo = $orderRepo;
		$this->connection         = $connection;

	}

	public static function getSubscribedEvents(): array
	{
		// Return the events to listen to as array like this:  <event to listen to> => <method to execute>
		return [
			ProductEvents::PRODUCT_LOADED_EVENT            => 'onProductsLoaded',
			OrderEvents::ORDER_WRITTEN_EVENT               => 'onOrderWritten',
			OrderEvents::ORDER_LOADED_EVENT                => 'onOrderLoaded',
			CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'onAddressWritten',
		];
	}

	public function onOrderLoaded(EntityLoadedEvent $event)
	{
//		foreach ( $event->getEntities() as $e)
//		{
//			$e->addExtension('string', []);
//		}
	}

	public function onOrderWritten(EntityWrittenEvent $event)
	{
		$ids    = $event->getIds();

		foreach ($ids as $id)
		{
			$criteria = new Criteria([$id]);
			$criteria->addAssociation('deliveries.shippingMethod')
				->addAssociation('deliveries.shippingOrderAddress.country')
				->addAssociation('transactions.paymentMethod')
				->addAssociation('lineItems')
				->addAssociation('currency')
				->addAssociation('products')
				->addAssociation('addresses');
			$currentOrder = $this->orderRepo->search($criteria, Context::createDefaultContext())->getEntities();

			$sqlQuery   = 'INSERT INTO `hobait_order_export` VALUES(?,?,?,?,?,?)';
			$sqlResult  = $this->connection->executeStatement($sqlQuery, [NULL, $id, NULL, NULL, json_encode($currentOrder), date('Y-m-d H:i:s') ]);
		}

	}

	public function onProductsLoaded(EntityLoadedEvent $event)
	{

	}

	public function onAddressWritten(EntityWrittenEvent $event)
	{

	}

	public function onAddressWrittten(EntityWrittenEvent $event)
	{
		//@todo mail changed address to support
		file_put_contents("/var/www/vhosts/reinhold-sohn-hygiene.de/httpdocs/customer_details.txt", json_encode($event));
	}

}
