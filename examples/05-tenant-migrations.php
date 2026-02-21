<?php

/**
 * Example 5: Tenant Migrations
 *
 * Tenant migrations live in a separate directory from your main migrations.
 * They use the same Doctrine Migrations API but run against each tenant's
 * database individually.
 *
 * Place these in the directory configured by tenant_migration.tenant_migration_path
 * (e.g., migrations/Tenant/).
 *
 * Use Doctrine's Schema API for platform-agnostic migrations that work
 * on both MySQL and PostgreSQL.
 */

// File: migrations/Tenant/Version20240101000000.php

namespace DoctrineMigrations\Tenant;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial tenant schema — creates the core tables for each tenant.
 */
final class Version20240101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial tenant schema (products and orders)';
    }

    public function up(Schema $schema): void
    {
        // Use Schema API instead of raw SQL for platform independence
        $product = $schema->createTable('product');
        $product->addColumn('id', 'integer', ['autoincrement' => true]);
        $product->addColumn('name', 'string', ['length' => 255]);
        $product->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2]);
        $product->addColumn('description', 'text', ['notnull' => false]);
        $product->addColumn('created_at', 'datetime_immutable');
        $product->setPrimaryKey(['id']);

        $order = $schema->createTable('customer_order');
        $order->addColumn('id', 'integer', ['autoincrement' => true]);
        $order->addColumn('status', 'string', ['length' => 50, 'default' => 'pending']);
        $order->addColumn('total', 'decimal', ['precision' => 10, 'scale' => 2]);
        $order->addColumn('product_id', 'integer');
        $order->setPrimaryKey(['id']);
        $order->addForeignKeyConstraint('product', ['product_id'], ['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('customer_order');
        $schema->dropTable('product');
    }
}

/*
# Generate a new migration after changing tenant entities:
php bin/console tenant:migrations:diff

# Run initial migration on a specific tenant:
php bin/console tenant:migrations:migrate init 42

# Batch-migrate ALL newly created databases:
php bin/console tenant:migrations:migrate init

# Update ALL already-migrated databases with new migrations:
php bin/console tenant:migrations:migrate update

# Dry-run to see what SQL would execute:
php bin/console tenant:migrations:migrate init 42 --dry-run
*/
