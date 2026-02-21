<?php

/**
 * Example 10: Tenant-Aware Cache
 *
 * When enabled, the bundle decorates Symfony's cache to automatically
 * prefix keys with the current tenant ID. This ensures cache isolation
 * between tenants without any changes to your application code.
 *
 * Tenant A's "product_list" key becomes "42__product_list"
 * Tenant B's "product_list" key becomes "99__product_list"
 */

/*
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    cache:
        enabled: true
        prefix_separator: '__'   # tenant_id + separator + key
*/

namespace App\Service;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ProductCatalogService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    /**
     * This cache call is automatically tenant-scoped.
     * When tenant 42 is active, the key becomes "42__product_catalog".
     * When tenant 99 is active, the key becomes "99__product_catalog".
     *
     * No changes needed in your code — the TenantAwareCacheDecorator
     * handles the prefixing transparently.
     */
    public function getCatalog(): array
    {
        return $this->cache->get('product_catalog', function () {
            // This expensive computation runs once per tenant
            return $this->buildCatalog();
        });
    }

    /**
     * Deleting a cache entry also uses the tenant prefix automatically.
     */
    public function invalidateCatalog(): void
    {
        $this->cache->delete('product_catalog');
    }

    private function buildCatalog(): array
    {
        // Expensive query — result is cached per-tenant
        return ['products' => '...'];
    }
}

/**
 * If you need to work with the PSR-6 CacheItemPoolInterface directly,
 * the same tenant-aware prefixing applies.
 */
class ReportService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
    ) {}

    public function getReport(string $reportId): array
    {
        $item = $this->cachePool->getItem("report_{$reportId}");

        if ($item->isHit()) {
            return $item->get();
        }

        $report = $this->generateReport($reportId);

        $item->set($report);
        $item->expiresAfter(3600);
        $this->cachePool->save($item);

        return $report;
    }

    private function generateReport(string $reportId): array
    {
        return ['id' => $reportId, 'data' => '...'];
    }
}
