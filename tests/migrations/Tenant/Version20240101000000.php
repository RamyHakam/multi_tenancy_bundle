<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tenant;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_product table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('tenant_product');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tenant_product');
    }
}
