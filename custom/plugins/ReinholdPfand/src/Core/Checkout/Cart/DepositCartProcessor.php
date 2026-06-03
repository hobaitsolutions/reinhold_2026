<?php declare(strict_types=1);

namespace ReinholdPfand\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DepositCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    private const DATA_KEY_PREFIX = 'reinhold-pfand-';

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly QuantityPriceCalculator $quantityPriceCalculator
    ) {
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $productLineItems = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $productIds = [];
        foreach ($productLineItems as $productLineItem) {
            $productId = $productLineItem->getReferencedId();
            if ($productId === null) {
                continue;
            }

            $dataKey = self::DATA_KEY_PREFIX . $productId;
            if ($data->has($dataKey)) {
                continue;
            }

            $productIds[$productId] = $productId;
        }

        if ($productIds === []) {
            return;
        }

        $criteria = new Criteria(array_values($productIds));
        /** @var ProductCollection $products */
        $products = $this->productRepository->search($criteria, $context->getContext())->getEntities();

        foreach ($products as $product) {
            $customFields = $product->getCustomFields() ?? [];
            $rawDeposit = $customFields['custom_product_deposit'] ?? null;

            if (!is_numeric($rawDeposit)) {
                $data->set(self::DATA_KEY_PREFIX . $product->getId(), 0.0);
                continue;
            }

            $deposit = max((float) $rawDeposit, 0.0);
            $data->set(self::DATA_KEY_PREFIX . $product->getId(), $deposit);
        }
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        foreach ($original->getLineItems()->filterFlatByType(LineItem::CUSTOM_LINE_ITEM_TYPE) as $lineItem) {
            if ($this->isDepositLineItem($lineItem)) {
                $original->remove($lineItem->getId());
            }
        }

        foreach ($toCalculate->getLineItems()->filterFlatByType(LineItem::CUSTOM_LINE_ITEM_TYPE) as $lineItem) {
            if ($this->isDepositLineItem($lineItem)) {
                $toCalculate->remove($lineItem->getId());
            }
        }

        $productLineItems = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($productLineItems as $productLineItem) {
            $productId = $productLineItem->getReferencedId();

            if ($productId === null) {
                continue;
            }

            $dataKey = self::DATA_KEY_PREFIX . $productId;

            if (!$data->has($dataKey)) {
                continue;
            }

            $deposit = (float) $data->get($dataKey);
            if ($deposit <= 0.0) {
                continue;
            }

            $taxRules = $productLineItem->getPrice()?->getTaxRules() ?? new TaxRuleCollection();

            $depositLineItem = new LineItem(
                'deposit-' . $productLineItem->getId(),
                LineItem::CUSTOM_LINE_ITEM_TYPE,
                null,
                $productLineItem->getQuantity()
            );
            $depositLineItem->setLabel(sprintf('Pfand (%s)', $productLineItem->getLabel() ?? 'Artikel'));
            $depositLineItem->setPayloadValue('isDeposit', true);
            $depositLineItem->setPayloadValue('productId', $productId);
            $depositLineItem->setStackable(true);
            $depositLineItem->setRemovable(true);
            $depositLineItem->setGood(false);

            $priceDefinition = new QuantityPriceDefinition(
                $deposit,
                $taxRules,
                $productLineItem->getQuantity()
            );

            $depositLineItem->setPriceDefinition($priceDefinition);
            $depositLineItem->setPrice(
                $this->quantityPriceCalculator->calculate($priceDefinition, $context)
            );

            $toCalculate->add($depositLineItem);
        }
    }

    private function isDepositLineItem(LineItem $lineItem): bool
    {
        if ((bool) $lineItem->getPayloadValue('isDeposit')) {
            return true;
        }

        return str_starts_with($lineItem->getId(), 'deposit-');
    }
}
