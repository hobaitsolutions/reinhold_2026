<?php declare(strict_types=1);

namespace ReinholdPfand;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use ReinholdPfand\Migration\Migration1709123456AddPfandField;
use ReinholdPfand\Core\Content\Product\ProductExtension;

class ReinholdPfand extends Plugin
{
    public function install(InstallContext $context): void
    {
        parent::install($context);
        
        $migration = new Migration1709123456AddPfandField();
        $migration->update($this->container->get(Connection::class));
    }

    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            return;
        }

        $migration = new Migration1709123456AddPfandField();
        $migration->updateDestructive($this->container->get(Connection::class));

        parent::uninstall($context);
    }

    public function getServiceDefinitionPaths(): array
    {
        return [
            $this->getPath() . '/Resources/config/services.xml',
        ];
    }

    public function boot(): void
    {
        parent::boot();

        $this->container->get('product.repository')
            ->addExtension(new ProductExtension());
    }
} 