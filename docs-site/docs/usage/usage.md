---
title: Usage & Features
---

The **Usage & Features** section dives into the core capabilities of the Symfony Multi-Tenancy Bundle. You'll find detailed workflows, examples, and best practices for each major feature:

## Runtime DB Switching

Dynamically switch the active database connection to a tenant’s database at runtime.

```php
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderController
{
    public function __construct(
        private TenantEntityManager $tenantEm,
        private EventDispatcherInterface $dispatcher
    ) {}

    public function createOrder(int $tenantId)
    {
        // Switch to tenant DB
        $this->dispatcher->dispatch(new SwitchDbEvent($tenantId));

        // Use tenant EM as usual
        $order = new Order();
        $order->setTotal(49.99);
        $this->tenantEm->persist($order);
        $this->tenantEm->flush();
    }
}
```

**Advanced Tips**

* Dispatch using either the tenant ID or the full `TenantDbConfig` entity.
* Minimize dispatch calls by batching operations per tenant.
* Enable `auto_create` in config to let the bundle provision tenant DBs on demand.

---

## Tenant Migrations

Manage each tenant’s schema migrations independently from your main application.

```bash
# Generate a new migration for tenant entities
php bin/console tenant:migration:diff --dbid=3

# Initialize a newly created tenant database
php bin/console tenant:migration:migrate init --dbid=3

# Update all existing tenant DBs to latest
php bin/console tenant:migration:migrate update --all
```

**Key Options**

* `--dbid`: target a specific tenant by ID.
* `init` vs `update`: run initial migrations on new DBs vs apply diffs to existing.
* `--all`: run the command across every tenant DB in one go.

**Best Practices**

* Preview diffs (`--dry-run`) before running in production.
* Keep tenant migration files under `migrations/Tenant` to avoid conflicts.

---

## Bulk Operations

Perform cross-tenant tasks programmatically, such as data migrations or analytics.

```php
$tenants = $mainEm->getRepository(TenantDbConfig::class)->findAll();
foreach ($tenants as $config) {
    // Switch once per tenant
    $dispatcher->dispatch(new SwitchDbEvent($config));

    // Example: seed a default setting
    $setting = new TenantSetting();
    $setting->setKey('timezone')->setValue('UTC');
    $tenantEm->persist($setting);
    $tenantEm->flush();
}
```

**Optimizations**

* Use generators or `yield` to stream large tenant lists.
* Wrap each tenant loop in a database transaction to rollback on failure.
* Parallelize using Symfony Messenger or CLI batching for large fleets.

---

## Tenant Fixtures

Seed tenant-specific demo or test data via the same Doctrine fixtures API.

```php
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

#[TenantFixture]
class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $product = new Product();
        $product->setName('Demo Product');
        $manager->persist($product);
    }
}
```

```bash
# Load only tenant fixtures, per-tenant or all
php bin/console tenant:fixtures:load --dbid=5 --append
php bin/console tenant:fixtures:load --all
```

**Fixture Groups & Dependencies**

* Use `--group=` to segment fixtures by purpose (e.g. `demo`, `test`).
* Leverage Doctrine’s `DependentFixtureInterface` to define load order.

---

## Custom Drivers & Credentials

Override default DBAL settings per tenant for advanced sharding or hosted setups.

```yaml
# global default
hakam_multi_tenancy:
  tenant_connection:
    driver: 'pdo_mysql'
    host: '%env(TENANT_DB_HOST)%'

# override in TenantDbConfig entity:
class TenantDbConfig implements TenantDbConfigurationInterface
{
    public function getDriver(): string
    {
        return 'pdo_pgsql';
    }

    public function getHost(): string
    {
        return $this->customHost;
    }
}
```

**Use Cases**

* Point high-tier tenants at dedicated clusters (e.g., AWS RDS vs on-prem).
* Mix MySQL main database with PostgreSQL tenant stores for specific analytics.
* Rotate credentials programmatically for security compliance.

---

## Cache Isolation

Prevent cross-tenant cache collisions when sharing a backend like Redis or Memcached.

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    cache:
        enabled: true
```

Once enabled, all `cache.app` operations are automatically scoped to the active tenant. No code changes needed:

```php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ReportService
{
    public function __construct(
        private CacheInterface $cache,
        private TenantEntityManager $tenantEm
    ) {}

    public function getMonthlyStats(): array
    {
        // Key is automatically prefixed with the tenant ID
        return $this->cache->get('monthly_stats', function (ItemInterface $item) {
            $item->expiresAfter(3600);

            return $this->tenantEm->getRepository(Order::class)
                ->getMonthlyStats();
        });
    }
}
```

**How It Works**

* The bundle decorates `cache.app` with `TenantAwareCacheDecorator`
* Keys are prefixed: `{tenantId}__key` (e.g., `acme__monthly_stats`)
* When no tenant is active, keys pass through unprefixed
* The `TenantContext` service tracks the current tenant and resets between requests

**Key Points**

* Enable with `cache.enabled: true` — disabled by default, fully backward-compatible.
* Works with PSR-6 (`CacheItemPoolInterface`) and Symfony Contracts (`CacheInterface`).
* Pairs naturally with [Automatic Tenant Resolution](/resolver/automatic-resolution).

[Full Cache Isolation documentation →](/cache/cache-isolation)

---

With these usage patterns and examples, you'll harness the full power of the Symfony Multi-Tenancy Bundle to build robust, scalable, and secure multi-tenant applications.
