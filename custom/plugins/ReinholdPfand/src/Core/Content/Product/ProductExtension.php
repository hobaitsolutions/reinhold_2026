<?php declare(strict_types=1);

namespace ReinholdPfand\Core\Content\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new FloatField('pfand_price', 'pfand_price')
        );
    }

    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
} 