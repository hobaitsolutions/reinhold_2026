<?php declare(strict_types=1);

namespace ReinholdPfand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1709123456AddPfandField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1709123456;
    }

    public function update(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('product');

        if (isset($columns['pfand_price'])) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `product`
            ADD COLUMN `pfand_price` DECIMAL(10,2) NULL DEFAULT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('product');

        if (!isset($columns['pfand_price'])) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `product`
            DROP COLUMN `pfand_price`;
        ');
    }
} 
