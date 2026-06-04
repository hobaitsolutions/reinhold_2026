<?php declare(strict_types=1);

namespace ReinholdPfand\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DepositCartProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    private const DATA_KEY_PREFIX = 'reinhold-pfand-';
    private const DATA_KEY_DS_PRODUCT_PREFIX = 'reinhold-ds-product-';
    private const DATA_KEY_DS_PRICE_PREFIX = 'reinhold-ds-price-';
    private const DS_FEE_NOTICE_KEY = 'reinhold-pfand.dsFeeAdded';

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

        $dsProductIds = [];
        $sourceToDsProduct = [];

        foreach ($products as $product) {
            $customFields = $product->getCustomFields() ?? [];
            $rawDeposit = $customFields['custom_product_deposit'] ?? null;
            $rawDsFeeProductId = $customFields['custom_product_ds_fee'] ?? null;

            if (!is_numeric($rawDeposit)) {
                $data->set(self::DATA_KEY_PREFIX . $product->getId(), 0.0);
            } else {
                $deposit = max((float) $rawDeposit, 0.0);
                $data->set(self::DATA_KEY_PREFIX . $product->getId(), $deposit);
            }

            if (!is_string($rawDsFeeProductId) || trim($rawDsFeeProductId) == '') {
                $data->set(self::DATA_KEY_DS_PRODUCT_PREFIX . $product->getId(), null);
                $data->set(self::DATA_KEY_DS_PRICE_PREFIX . $product->getId(), 0.0);
                continue;
            }

            $dsProductId = trim($rawDsFeeProductId);
            $sourceToDsProduct[$product->getId()] = $dsProductId;
            $dsProductIds[$dsProductId] = $dsProductId;
        }

        if ($dsProductIds === []) {
            return;
        }

        $dsCriteria = new Criteria(array_values($dsProductIds));
        /** @var ProductCollection $dsProducts */
        $dsProducts = $this->productRepository->search($dsCriteria, $context->getContext())->getEntities();

        foreach ($sourceToDsProduct as $sourceProductId => $dsProductId) {
            $dsProduct = $dsProducts->get($dsProductId);
            if (!$dsProduct instanceof ProductEntity) {
                $data->set(self::DATA_KEY_DS_PRODUCT_PREFIX . $sourceProductId, null);
                $data->set(self::DATA_KEY_DS_PRICE_PREFIX . $sourceProductId, 0.0);
                continue;
            }

            $dsPrice = $this->resolveProductUnitPrice($dsProduct, $context);
            if ($dsPrice === null) {
                $data->set(self::DATA_KEY_DS_PRODUCT_PREFIX . $sourceProductId, null);
                $data->set(self::DATA_KEY_DS_PRICE_PREFIX . $sourceProductId, 0.0);
                continue;
            }

            $data->set(self::DATA_KEY_DS_PRODUCT_PREFIX . $sourceProductId, $dsProductId);
            $data->set(self::DATA_KEY_DS_PRICE_PREFIX . $sourceProductId, max($dsPrice, 0.0));
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

            if ($this->isDsFeeLineItem($lineItem)) {
                $original->remove($lineItem->getId());
            }
        }

        foreach ($toCalculate->getLineItems()->filterFlatByType(LineItem::CUSTOM_LINE_ITEM_TYPE) as $lineItem) {
            if ($this->isDepositLineItem($lineItem)) {
                $toCalculate->remove($lineItem->getId());
            }

            if ($this->isDsFeeLineItem($lineItem)) {
                $toCalculate->remove($lineItem->getId());
            }
        }

        $productLineItems = $original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        foreach ($productLineItems as $productLineItem) {
            $productId = $productLineItem->getReferencedId();

            if ($productId === null) {
                continue;
            }

            $taxRules = $productLineItem->getPrice()?->getTaxRules() ?? new TaxRuleCollection();

            $dataKey = self::DATA_KEY_PREFIX . $productId;
            $deposit = $data->has($dataKey) ? (float) $data->get($dataKey) : 0.0;

            if ($deposit > 0.0) {
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

            $dsProductKey = self::DATA_KEY_DS_PRODUCT_PREFIX . $productId;
            $dsPriceKey = self::DATA_KEY_DS_PRICE_PREFIX . $productId;

            if (!$data->has($dsProductKey) || !$data->has($dsPriceKey)) {
                continue;
            }

            $dsProductId = $data->get($dsProductKey);
            $dsPrice = (float) $data->get($dsPriceKey);

            if (!is_string($dsProductId) || $dsProductId === '' || $dsPrice <= 0.0) {
                continue;
            }

            $dsFeeLineItem = new LineItem(
                'ds-fee-' . $productLineItem->getId(),
                LineItem::CUSTOM_LINE_ITEM_TYPE,
                null,
                $productLineItem->getQuantity()
            );
            $dsFeeLineItem->setLabel(sprintf('DS Gebühr (%s)', $productLineItem->getLabel() ?? 'Artikel'));
            $dsFeeLineItem->setPayloadValue('isDsFee', true);
            $dsFeeLineItem->setPayloadValue('sourceProductId', $productId);
            $dsFeeLineItem->setPayloadValue('dsFeeProductId', $dsProductId);
            $dsFeeLineItem->setStackable(true);
            $dsFeeLineItem->setRemovable(true);
            $dsFeeLineItem->setGood(false);

            $dsPriceDefinition = new QuantityPriceDefinition(
                $dsPrice,
                $taxRules,
                $productLineItem->getQuantity()
            );

            $dsFeeLineItem->setPriceDefinition($dsPriceDefinition);
            $dsFeeLineItem->setPrice(
                $this->quantityPriceCalculator->calculate($dsPriceDefinition, $context)
            );

            $toCalculate->add($dsFeeLineItem);
            $toCalculate->addErrors(new GenericCartError(
                'ds-fee-notice-' . $productLineItem->getId(),
                self::DS_FEE_NOTICE_KEY,
                [
                    'productName' => $productLineItem->getLabel() ?? 'Artikel',
                ],
                Error::LEVEL_NOTICE,
                false,
                true,
                false
            ));
        }
    }

    private function resolveProductUnitPrice(ProductEntity $product, SalesChannelContext $context): ?float
    {
        $currencyPrice = $product->getCurrencyPrice($context->getCurrencyId());
        if ($currencyPrice !== null) {
            return (float) $currencyPrice->getGross();
        }

        $defaultCurrencyPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
        if ($defaultCurrencyPrice !== null) {
            return (float) $defaultCurrencyPrice->getGross();
        }

        return null;
    }

    private function isDepositLineItem(LineItem $lineItem): bool
    {
        if ((bool) $lineItem->getPayloadValue('isDeposit')) {
            return true;
        }

        return str_starts_with($lineItem->getId(), 'deposit-');
    }

    private function isDsFeeLineItem(LineItem $lineItem): bool
    {
        if ((bool) $lineItem->getPayloadValue('isDsFee')) {
            return true;
        }

        return str_starts_with($lineItem->getId(), 'ds-fee-');
    }
}
