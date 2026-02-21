<?php

/**
 * Example 4: Database Lifecycle — Create, Migrate, Switch, CRUD
 *
 * Shows the full lifecycle of a tenant database:
 * 1. Create the database
 * 2. Run migrations
 * 3. Switch connection
 * 4. CRUD operations
 */

namespace App\Controller;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class TenantLifecycleController extends AbstractController
{
    public function __construct(
        private readonly TenantDatabaseManagerInterface $tenantManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TenantEntityManager $tenantEntityManager,
    ) {}

    // ──────────────────────────────────────────────
    // Step 1: Register a new tenant and create its database
    // ──────────────────────────────────────────────
    public function onboardTenant(): JsonResponse
    {
        // Add tenant config to the main database
        $tenantDto = $this->tenantManager->addNewTenantDbConfig(
            TenantConnectionConfigDTO::fromArgs(
                identifier: null,                              // auto-generated
                driver: DriverTypeEnum::MYSQL,
                dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
                host: '127.0.0.1',
                port: 3306,
                dbname: 'tenant_acme_corp',
                user: 'tenant_user',
                password: 'secret',
            )
        );

        // Create the actual database on the server
        $this->tenantManager->createTenantDatabase($tenantDto);

        // Update status to reflect the database now exists
        $this->tenantManager->updateTenantDatabaseStatus(
            $tenantDto->identifier,
            DatabaseStatusEnum::DATABASE_CREATED
        );

        return new JsonResponse([
            'tenant_id' => $tenantDto->identifier,
            'database' => $tenantDto->dbname,
            'status' => 'created',
        ]);
    }

    // ──────────────────────────────────────────────
    // Step 2: Switch to a tenant database and work with it
    // ──────────────────────────────────────────────
    public function switchAndQuery(string $tenantId): JsonResponse
    {
        // Dispatch SwitchDbEvent — this triggers the connection switch
        $this->dispatcher->dispatch(new SwitchDbEvent($tenantId));

        // Now the tenant entity manager points to the tenant's database.
        // All queries go to the tenant's DB automatically.
        $products = $this->tenantEntityManager
            ->getRepository(\App\Entity\Tenant\Product::class)
            ->findAll();

        return new JsonResponse([
            'tenant' => $tenantId,
            'product_count' => count($products),
        ]);
    }

    // ──────────────────────────────────────────────
    // Step 3: CRUD operations on tenant data
    // ──────────────────────────────────────────────
    public function createProduct(string $tenantId): JsonResponse
    {
        // Switch to tenant
        $this->dispatcher->dispatch(new SwitchDbEvent($tenantId));

        // Create
        $product = new \App\Entity\Tenant\Product();
        $product->setName('Widget Pro');
        $product->setPrice('29.99');
        $this->tenantEntityManager->persist($product);
        $this->tenantEntityManager->flush();

        // Read
        $this->tenantEntityManager->clear();
        $found = $this->tenantEntityManager->find(
            \App\Entity\Tenant\Product::class,
            $product->getId()
        );

        // Update
        $found->setPrice('34.99');
        $this->tenantEntityManager->flush();

        // Delete
        // $this->tenantEntityManager->remove($found);
        // $this->tenantEntityManager->flush();

        return new JsonResponse([
            'id' => $found->getId(),
            'name' => $found->getName(),
            'price' => $found->getPrice(),
        ]);
    }

    // ──────────────────────────────────────────────
    // List & filter tenant databases by status
    // ──────────────────────────────────────────────
    public function listTenants(): JsonResponse
    {
        // Get all migrated (ready) databases
        $readyDatabases = $this->tenantManager->listDatabases();

        // Get databases waiting for migration
        $pendingDatabases = $this->tenantManager->getTenantDbListByDatabaseStatus(
            DatabaseStatusEnum::DATABASE_CREATED
        );

        // Get a specific tenant's config
        $tenantConfig = $this->tenantManager->getTenantDatabaseById(42);

        return new JsonResponse([
            'ready' => count($readyDatabases),
            'pending_migration' => count($pendingDatabases),
            'tenant_42_db' => $tenantConfig->dbname,
        ]);
    }
}

// ──────────────────────────────────────────────
// Console commands for database lifecycle
// ──────────────────────────────────────────────

/*
# Create a single tenant database:
php bin/console tenant:database:create --dbid=42

# Create ALL missing databases at once:
php bin/console tenant:database:create --all

# Run initial migrations on a newly created database:
php bin/console tenant:migrations:migrate init 42

# Run initial migrations on ALL databases with DATABASE_CREATED status:
php bin/console tenant:migrations:migrate init

# Run update migrations on already-migrated databases:
php bin/console tenant:migrations:migrate update

# Generate a migration diff for tenant schema changes:
php bin/console tenant:migrations:diff

# Dry-run a migration:
php bin/console tenant:migrations:migrate init 42 --dry-run
*/
