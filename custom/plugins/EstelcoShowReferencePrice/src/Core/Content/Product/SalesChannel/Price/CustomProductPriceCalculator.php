<?php declare(strict_types=1);
/*
 * © Estelco <info@estelco.de>
 */

namespace Estelco\ShowReferencePrice\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

class CustomProductPriceCalculator extends AbstractProductPriceCalculator
{
    /**
     * @var AbstractProductPriceCalculator
     */
    private AbstractProductPriceCalculator $productPriceCalculator;

    public function __construct(AbstractProductPriceCalculator $productPriceCalculator)
    {
        $this->productPriceCalculator = $productPriceCalculator;
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        return $this->productPriceCalculator;
    }

    public function calculate(iterable $products, SalesChannelContext $context): void {
        // parent
        $this->getDecorated()->calculate($products, $context);
        /** @var Entity $product */
        foreach ($products as $product) {
            $unit = $product->get('unit');
            $purchaseUnit = $product->get('purchaseUnit');
            $referenceUnit = $product->get('referenceUnit');
            if ($unit && $purchaseUnit > 0 && $referenceUnit > 0 && $purchaseUnit === $referenceUnit) {
                $price = $product->get('calculatedPrice');
                $referencePrice = new ReferencePrice($price->getUnitPrice(), $purchaseUnit, $referenceUnit, $unit->name);
                $price->assign(['referencePrice' => $referencePrice]);
            }
        }
    }
}
