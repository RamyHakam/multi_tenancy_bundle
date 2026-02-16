---
title: "Tenant-Aware Cache Isolation"
sidebar_position: 1
---

# Tenant-Aware Cache Isolation

**Available since v3.0.0**

The Tenant-Aware Cache Isolation feature prevents cross-tenant data leakage through shared cache backends. When multiple tenants share the same Redis, Memcached, or filesystem cache, identical cache keys can collide and expose one tenant's data to another. This feature transparently prefixes all cache keys with the active tenant's identifier, ensuring complete isolation.

## Why Cache Isolation Matters

| Without Cache Isolation | With Cache Isolation |
|------------------------|---------------------|
| Tenant A writes `user_count = 50` | Tenant A writes `tenantA__user_count = 50` |
| Tenant B reads `user_count` and gets `50` | Tenant B reads `tenantB__user_count` and gets its own value |
| Cross-tenant data leakage | Complete isolation, zero leakage |

:::danger Security Risk
Without cache isolation, any cache key shared between tenants can leak sensitive data — user counts, settings, computed results, or even serialized entities. This is especially critical when using Redis or Memcached as a shared cache backend.
:::

---

## Quick Start

Enable cache isolation in your configuration:

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    cache:
        enabled: true
```

That's it! All operations on `cache.app` are now automatically scoped to the active tenant.

---

## How It Works

The bundle decorates Symfony's `cache.app` service with a `TenantAwareCacheDecorator` that:

1. **Reads the active tenant** from `TenantContext` (updated automatically on every `TenantSwitchedEvent`)
2. **Prefixes cache keys** with `{tenantId}__{key}` before delegating to the inner cache pool
3. **Strips the prefix** from returned `CacheItem` keys so your application code sees the original key
4. **Passes through unprefixed** when no tenant is active (e.g., during CLI commands or public routes)

```
Tenant A active:  cache->getItem('settings')  →  inner->getItem('tenantA__settings')
Tenant B active:  cache->getItem('settings')  →  inner->getItem('tenantB__settings')
No tenant:        cache->getItem('settings')  →  inner->getItem('settings')
```

---

## Usage Examples

### Basic Cache Operations

No code changes needed — inject `CacheItemPoolInterface` or `CacheInterface` as usual:

```php
use Psr\Cache\CacheItemPoolInterface;

class DashboardService
{
    public function __construct(
        private CacheItemPoolInterface $cache
    ) {}

    public function getStats(): array
    {
        $item = $this->cache->getItem('dashboard_stats');

        if (!$item->isHit()) {
            $stats = $this->computeExpensiveStats();
            $item->set($stats);
            $item->expiresAfter(3600);
            $this->cache->save($item);
        }

        return $item->get();
    }
}
```

When Tenant A is active, `dashboard_stats` is stored as `tenantA__dashboard_stats`. When Tenant B is active, it gets its own isolated copy. Your service code never changes.

### Using Symfony Cache Contracts

The decorator also implements `Symfony\Contracts\Cache\CacheInterface`:

```php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProductCatalogService
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    public function getFeaturedProducts(): array
    {
        return $this->cache->get('featured_products', function (ItemInterface $item) {
            $item->expiresAfter(1800);

            return $this->repository->findFeatured();
        });
    }
}
```

### Combining with Runtime DB Switching

Cache isolation works seamlessly with manual tenant switching:

```php
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;

class TenantReportService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private CacheInterface $cache,
        private TenantEntityManager $tenantEm
    ) {}

    public function generateReport(int $tenantId): array
    {
        // Switch to tenant — cache is now automatically scoped
        $this->dispatcher->dispatch(new SwitchDbEvent($tenantId));

        return $this->cache->get('monthly_report', function () {
            return $this->tenantEm->getRepository(Order::class)
                ->getMonthlyReport();
        });
    }
}
```

---

## TenantContext

The `TenantContext` service is the canonical source of the current tenant identity. It is always registered (even when cache isolation is disabled) and can be used in your own services.

### Reading the Current Tenant

```php
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;

class AuditLogger
{
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {}

    public function log(string $message): void
    {
        $tenantId = $this->tenantContext->getTenantId();
        // $tenantId is null when no tenant is active

        $this->logger->info($message, ['tenant' => $tenantId]);
    }
}
```

### How TenantContext Gets Updated

`TenantContext` listens to `TenantSwitchedEvent` (fired by `DbSwitchEventListener` after a successful connection switch). It stores the tenant identifier as a string. It also implements `ResetInterface`, so it resets to `null` between requests in long-running processes.

---

## Configuration Reference

### Full Configuration

```yaml
hakam_multi_tenancy:
    # ... other configuration ...

    cache:
        # Enable/disable tenant-aware cache isolation (default: false)
        enabled: true

        # Separator between tenant ID and cache key (default: '__')
        prefix_separator: '__'
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable tenant-aware cache key prefixing |
| `prefix_separator` | string | `__` | Separator between tenant ID and original key |

### Custom Separator

If your tenant IDs contain double underscores, use a different separator:

```yaml
hakam_multi_tenancy:
    cache:
        enabled: true
        prefix_separator: '||'
```

This changes the key format from `tenantA__key` to `tenantA||key`.

---

## Testing

### Using TenantTestTrait

The `TenantTestTrait` automatically resets `TenantContext` in `resetTenantState()`:

```php
use Hakam\MultiTenancyBundle\Test\TenantTestTrait;

class CachedServiceTest extends KernelTestCase
{
    use TenantTestTrait;

    public function testCacheIsolation(): void
    {
        $cache = self::getContainer()->get('cache.app');

        // Write as tenant A
        $this->switchToTenant('tenant_a');
        $item = $cache->getItem('key');
        $item->set('value_a');
        $cache->save($item);

        // Write as tenant B
        $this->switchToTenant('tenant_b');
        $item = $cache->getItem('key');
        $this->assertFalse($item->isHit()); // Isolated!
        $item->set('value_b');
        $cache->save($item);

        // Verify tenant A still sees its own data
        $this->switchToTenant('tenant_a');
        $item = $cache->getItem('key');
        $this->assertSame('value_a', $item->get());
    }

    protected function tearDown(): void
    {
        $this->resetTenantState();
        parent::tearDown();
    }
}
```

### Manual TenantContext Testing

For unit tests where you don't need the full kernel, inject a mock:

```php
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;

class MyServiceTest extends TestCase
{
    public function testWithTenant(): void
    {
        $context = $this->createMock(TenantContextInterface::class);
        $context->method('getTenantId')->willReturn('tenant_42');

        $service = new MyService($context);
        // ...
    }
}
```

---

## Architecture Details

### Services Registered

| Service | Condition | Description |
|---------|-----------|-------------|
| `TenantContext` | Always | Tracks current tenant identity |
| `TenantContextInterface` | Always | Alias to `TenantContext` |
| `TenantAwareCacheDecorator` | `cache.enabled: true` | Decorates `cache.app` |

### Interfaces Implemented by the Decorator

* `Psr\Cache\CacheItemPoolInterface` (PSR-6)
* `Symfony\Contracts\Cache\CacheInterface` (Symfony Contracts)

### Key Prefixing Behavior

| Scenario | Input Key | Stored Key |
|----------|-----------|------------|
| Tenant `acme` active | `settings` | `acme__settings` |
| Tenant `42` active | `user_count` | `42__user_count` |
| No tenant active | `global_config` | `global_config` |

---

## Known Limitations

### `clear()` Clears All Tenants

Calling `$cache->clear()` delegates to the inner pool and clears **all** cached data, including other tenants. This is a limitation of PSR-6's `clear()` which takes no arguments for scoping.

**Workaround:** Use `deleteItem()` or `deleteItems()` for tenant-scoped cache invalidation instead of `clear()`.

### Only Decorates `cache.app`

The decorator is applied to `cache.app` (Symfony's default application cache pool). If you use custom cache pools, they are not automatically decorated.

**Workaround:** Manually decorate additional pools in your service configuration:

```yaml
services:
    app.tenant_aware_custom_cache:
        class: Hakam\MultiTenancyBundle\Cache\TenantAwareCacheDecorator
        decorates: 'cache.custom_pool'
        arguments:
            - '@.inner'
            - '@Hakam\MultiTenancyBundle\Context\TenantContextInterface'
            - '__'
```

---

## Best Practices

### 1. Enable Cache Isolation in Production

If tenants share a cache backend (Redis, Memcached), always enable cache isolation:

```yaml
hakam_multi_tenancy:
    cache:
        enabled: true
```

### 2. Use Meaningful Tenant Identifiers

Since tenant IDs become part of cache keys, prefer short, filesystem-safe identifiers:

```
Good:  acme, tenant_42, org-7
Avoid: My Company Name!, tenant/with/slashes
```

### 3. Invalidate Per-Tenant, Not Globally

Avoid `$cache->clear()` in multi-tenant contexts. Instead, track and delete specific keys:

```php
// Instead of $cache->clear()
$cache->deleteItems(['dashboard_stats', 'user_count', 'settings']);
```

### 4. Combine with Automatic Resolution

Cache isolation pairs naturally with [Automatic Tenant Resolution](/resolver/automatic-resolution):

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: subdomain
        options:
            base_domain: 'myapp.com'
    cache:
        enabled: true
```

Requests to `acme.myapp.com` automatically switch the database **and** scope the cache.

---

## Backward Compatibility

Cache isolation is **disabled by default**. Enabling it requires no changes to your existing application code — the decorator transparently wraps `cache.app` and prefixes keys only when a tenant is active.

Your existing cache usage continues to work exactly as before:

```php
// This works identically with or without cache isolation
$item = $cache->getItem('my_key');
```
