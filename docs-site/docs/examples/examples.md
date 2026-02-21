---
title: "Code Examples"
sidebar_label: Examples
description: Runnable code examples for every feature of the Multi-Tenancy Bundle
---

# Code Examples

The [`examples/`](https://github.com/RamyHakam/multi_tenancy_bundle/tree/master/examples) directory contains **15 self-contained PHP files** covering every feature of the bundle. Each file includes inline comments, configuration snippets, and ready-to-use code.

---

## Quick Reference

| # | File | Feature |
|---|------|---------|
| 01 | [`entity-setup.php`](#1-tenant-entity-setup) | Tenant config entity with `TenantDbConfigTrait` |
| 02 | [`bundle-configuration.php`](#2-full-bundle-configuration) | Complete YAML config reference |
| 03 | [`tenant-entities.php`](#3-tenant-entities) | Tenant-scoped entities (Product, Order) |
| 04 | [`database-lifecycle.php`](#4-database-lifecycle) | Create DB, switch, CRUD, list/filter |
| 05 | [`tenant-migrations.php`](#5-tenant-migrations) | Platform-agnostic migrations |
| 06 | [`resolvers.php`](#6-tenant-resolvers) | All 5 resolver strategies |
| 07 | [`events.php`](#7-lifecycle-events) | All 6 lifecycle event subscribers |
| 08 | [`custom-config-provider.php`](#8-custom-config-provider) | Redis, static, in-memory providers |
| 09 | [`tenant-fixtures.php`](#9-tenant-fixtures) | `#[TenantFixture]` attribute + CLI |
| 10 | [`tenant-aware-cache.php`](#10-tenant-aware-cache) | Cache isolation |
| 11 | [`tenant-context.php`](#11-tenant-context) | `TenantContextInterface` usage |
| 12 | [`testing.php`](#12-testing) | `TenantTestTrait` patterns |
| 13 | [`shared-entities.php`](#13-shared-entities) | `#[TenantShared]` attribute |
| 14 | [`custom-resolver.php`](#14-custom-resolver) | JWT, query param, API key resolvers |
| 15 | [`full-onboarding-flow.php`](#15-full-onboarding-flow) | End-to-end tenant onboarding service |

---

## 1. Tenant Entity Setup

Your application needs an entity that stores the connection details for each tenant database. Use `TenantDbConfigTrait` for the standard fields and implement `TenantDbConfigurationInterface`.

```php
use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\Traits\TenantDbConfigTrait;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_db_config')]
class TenantDbConfig implements TenantDbConfigurationInterface
{
    use TenantDbConfigTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // IMPORTANT: PHP method names are case-insensitive.
    // The trait defines getDbUserName() and the interface defines getDbUsername().
    // Access the property directly to avoid infinite recursion.
    public function getDbUsername(): ?string
    {
        return $this->dbUserName;
    }

    public function getIdentifierValue(): mixed
    {
        return $this->id;
    }
}
```

> See full example: [`examples/01-entity-setup.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/01-entity-setup.php)

---

## 2. Full Bundle Configuration

The complete YAML configuration with every available option:

```yaml
hakam_multi_tenancy:
    tenant_database_className: App\Entity\TenantDbConfig
    tenant_database_identifier: id

    tenant_connection:
        url: '%env(DATABASE_URL)%'
        host: '127.0.0.1'
        port: '3306'
        driver: pdo_mysql
        charset: utf8
        server_version: '8.0'

    tenant_migration:
        tenant_migration_namespace: DoctrineMigrations\Tenant
        tenant_migration_path: '%kernel.project_dir%/migrations/Tenant'

    tenant_entity_manager:
        tenant_naming_strategy: doctrine.orm.naming_strategy.default
        mapping:
            type: attribute
            dir: '%kernel.project_dir%/src/Entity/Tenant'
            prefix: App\Entity\Tenant
            alias: Tenant
            is_bundle: false

    resolver:
        enabled: true
        strategy: header
        throw_on_missing: false
        excluded_paths: ['/health', '/api/public', '/_profiler']
        options:
            header_name: X-Tenant-ID

    cache:
        enabled: true
        prefix_separator: '__'

    # Optional: custom provider override
    # tenant_config_provider: app.my_custom_tenant_provider
```

> See full example: [`examples/02-bundle-configuration.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/02-bundle-configuration.php)

---

## 3. Tenant Entities

Tenant entities live in a separate directory (e.g., `src/Entity/Tenant/`) and are managed by the tenant entity manager:

```php
namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    // ...getters/setters
}
```

> See full example: [`examples/03-tenant-entities.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/03-tenant-entities.php)

---

## 4. Database Lifecycle

The full lifecycle: register tenant, create database, switch connection, CRUD operations.

```php
// Register a new tenant
$tenantDto = $this->tenantManager->addNewTenantDbConfig(
    TenantConnectionConfigDTO::fromArgs(
        identifier: null,
        driver: DriverTypeEnum::MYSQL,
        dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
        host: '127.0.0.1', port: 3306,
        dbname: 'tenant_acme', user: 'root', password: 'secret',
    )
);

// Create the database
$this->tenantManager->createTenantDatabase($tenantDto);

// Switch to it
$this->dispatcher->dispatch(new SwitchDbEvent((string) $tenantDto->identifier));

// CRUD with the tenant entity manager
$product = new Product();
$product->setName('Widget Pro');
$this->tenantEntityManager->persist($product);
$this->tenantEntityManager->flush();
```

**CLI commands:**

```bash
php bin/console tenant:database:create --dbid=42     # Single tenant
php bin/console tenant:database:create --all          # All missing
php bin/console tenant:migrations:migrate init 42     # Migrate one
php bin/console tenant:migrations:migrate init        # Migrate all new
php bin/console tenant:migrations:migrate update      # Update existing
```

> See full example: [`examples/04-database-lifecycle.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/04-database-lifecycle.php)

---

## 5. Tenant Migrations

Use Doctrine's Schema API for platform-agnostic migrations (works on MySQL and PostgreSQL):

```php
namespace DoctrineMigrations\Tenant;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('product');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2]);
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('product');
    }
}
```

> See full example: [`examples/05-tenant-migrations.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/05-tenant-migrations.php)

---

## 6. Tenant Resolvers

Five built-in strategies for automatic tenant resolution from HTTP requests:

```yaml
# Header (APIs)
resolver: { enabled: true, strategy: header }

# Subdomain (SaaS)
resolver: { enabled: true, strategy: subdomain, options: { base_domain: example.com } }

# Path (/tenant-id/page)
resolver: { enabled: true, strategy: path }

# Host mapping (custom domains)
resolver: { enabled: true, strategy: host, options: { host_map: { client.com: tenant1 } } }

# Chain (fallback)
resolver: { enabled: true, strategy: chain, options: { chain_order: [header, path] } }
```

With resolvers enabled, controllers need no manual switching:

```php
class ProductController extends AbstractController
{
    public function list(TenantContextInterface $tenantContext): JsonResponse
    {
        // Resolver already switched the DB! Just query.
        $tenantId = $tenantContext->getTenantId();
        $products = $this->tenantEntityManager->getRepository(Product::class)->findAll();
        return new JsonResponse(['tenant' => $tenantId, 'count' => count($products)]);
    }
}
```

> See full example: [`examples/06-resolvers.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/06-resolvers.php)

---

## 7. Lifecycle Events

Subscribe to events fired during tenant operations:

```php
class TenantLifecycleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TenantCreatedEvent::class     => 'onCreated',   // DB created
            TenantMigratedEvent::class    => 'onMigrated',  // Migrations applied
            TenantBootstrappedEvent::class=> 'onBootstrapped', // Fixtures loaded
            TenantSwitchedEvent::class    => 'onSwitched',  // Connection switched
            TenantDeletedEvent::class     => 'onDeleted',   // DB dropped
        ];
    }

    public function onCreated(TenantCreatedEvent $event): void
    {
        // $event->getDatabaseName(), $event->getTenantIdentifier()
    }

    public function onMigrated(TenantMigratedEvent $event): void
    {
        // $event->getMigrationType() — 'init' or 'update'
        // $event->isInitialMigration(), $event->getToVersion()
    }

    public function onSwitched(TenantSwitchedEvent $event): void
    {
        // $event->getPreviousTenantIdentifier(), $event->hadPreviousTenant()
    }
}
```

> See full example: [`examples/07-events.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/07-events.php)

---

## 8. Custom Config Provider

Replace the default Doctrine-based provider with your own:

```php
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;

class RedisTenantConfigProvider implements TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        $data = $this->redis->hGetAll("tenant:{$identifier}");
        return TenantConnectionConfigDTO::fromArgs(
            identifier: $identifier,
            driver: DriverTypeEnum::from($data['driver']),
            dbStatus: DatabaseStatusEnum::from($data['status']),
            host: $data['host'], port: (int) $data['port'],
            dbname: $data['dbname'], user: $data['user'],
            password: $data['password'],
        );
    }
}
```

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    tenant_config_provider: App\Service\RedisTenantConfigProvider
```

> See full example: [`examples/08-custom-config-provider.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/08-custom-config-provider.php)

---

## 9. Tenant Fixtures

Mark fixtures with `#[TenantFixture]` to load them into tenant databases:

```php
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Doctrine\Bundle\FixturesBundle\Fixture;

#[TenantFixture]
class ProductFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $product = new Product();
        $product->setName('Basic Plan');
        $product->setPrice('9.99');
        $manager->persist($product);
        $manager->flush();
    }
}
```

```bash
php bin/console tenant:fixtures:load 42            # Specific tenant
php bin/console tenant:fixtures:load --append      # All, without purging
php bin/console tenant:fixtures:load --group=demo  # By group
```

> See full example: [`examples/09-tenant-fixtures.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/09-tenant-fixtures.php)

---

## 10. Tenant-Aware Cache

Enable cache isolation to prefix keys with the active tenant ID automatically:

```yaml
hakam_multi_tenancy:
    cache:
        enabled: true
```

```php
class ProductCatalogService
{
    public function getCatalog(CacheInterface $cache): array
    {
        // Key "product_catalog" becomes "42__product_catalog" for tenant 42
        return $cache->get('product_catalog', fn() => $this->buildCatalog());
    }
}
```

> See full example: [`examples/10-tenant-aware-cache.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/10-tenant-aware-cache.php)

---

## 11. Tenant Context

Access the current tenant ID anywhere via `TenantContextInterface`:

```php
class AuditService
{
    public function __construct(private TenantContextInterface $tenantContext) {}

    public function log(string $action): void
    {
        $tenantId = $this->tenantContext->getTenantId(); // null if no tenant active
        $this->logger->info($action, ['tenant' => $tenantId]);
    }
}
```

> See full example: [`examples/11-tenant-context.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/11-tenant-context.php)

---

## 12. Testing

Use `TenantTestTrait` for PHPUnit tests:

```php
use Hakam\MultiTenancyBundle\Test\TenantTestTrait;

class ProductServiceTest extends KernelTestCase
{
    use TenantTestTrait;

    public function testTenantIsolation(): void
    {
        // runInTenant() switches, runs callback, resets state automatically
        $this->runInTenant('tenant_a', function () {
            $em = $this->getTenantEntityManager();
            $product = new Product();
            $product->setName('Tenant A Product');
            $em->persist($product);
            $em->flush();
        });

        $this->runInTenant('tenant_b', function () {
            $products = $this->getTenantEntityManager()
                ->getRepository(Product::class)->findAll();
            $this->assertCount(0, $products); // Isolated!
        });
    }
}
```

> See full example: [`examples/12-testing.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/12-testing.php)

---

## 13. Shared Entities

Mark entities shared across tenants with optional exclusions:

```php
use Hakam\MultiTenancyBundle\Attribute\TenantShared;

#[TenantShared]
#[ORM\Entity]
class Plan { /* shared across ALL tenants */ }

#[TenantShared(excludeTenants: ['free_tier'], group: 'premium')]
#[ORM\Entity]
class PremiumFeature { /* shared, but not for free_tier */ }
```

```php
// Check access at runtime:
$attr = (new \ReflectionClass($entityClass))->getAttributes(TenantShared::class);
$tenantShared = $attr[0]->newInstance();
$canAccess = $tenantShared->isAvailableForTenant($currentTenantId);
```

> See full example: [`examples/13-shared-entities.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/13-shared-entities.php)

---

## 14. Custom Resolver

Implement `TenantResolverInterface` for custom resolution logic:

```php
class JwtTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $token = substr($request->headers->get('Authorization', ''), 7);
        $payload = json_decode(base64_decode(explode('.', $token)[1] ?? ''), true);
        return $payload['tenant_id'] ?? null;
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }
}
```

> See full example: [`examples/14-custom-resolver.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/14-custom-resolver.php)

---

## 15. Full Onboarding Flow

Complete end-to-end tenant setup: config entity, create DB, migrate, load fixtures, verify:

```php
class TenantOnboardingService
{
    public function onboard(string $companyName, string $databaseName): array
    {
        // 1. Create config entity in main DB
        $config = new TenantDbConfig();
        $config->setDbName($databaseName);
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // 2. Create the actual database
        $this->tenantManager->createTenantDatabase($dto);
        $this->tenantManager->updateTenantDatabaseStatus($id, DatabaseStatusEnum::DATABASE_CREATED);

        // 3. Run migrations
        $this->runCommand('tenant:migrations:migrate', ['type' => 'init', 'dbId' => $id]);

        // 4. Load fixtures
        $this->runCommand('tenant:fixtures:load', ['dbId' => $id, '--append' => true]);

        return ['tenant_id' => $id, 'status' => 'ready'];
    }
}
```

> See full example: [`examples/15-full-onboarding-flow.php`](https://github.com/RamyHakam/multi_tenancy_bundle/blob/master/examples/15-full-onboarding-flow.php)
