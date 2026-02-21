<?php

/**
 * Example 13: Shared Entities with #[TenantShared]
 *
 * Mark entities that should be shared across tenants (with optional exclusions).
 * This is useful for reference data, feature flags, or shared catalogs.
 */

namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;

/**
 * This entity is shared across ALL tenants.
 * Every tenant sees the same data.
 */
#[TenantShared]
#[ORM\Entity]
#[ORM\Table(name: 'shared_plan')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $monthlyPrice;

    // ...getters/setters
}

/**
 * This entity is shared but with exclusions.
 * Tenants "tenant_free" and "tenant_trial" don't get access.
 */
#[TenantShared(
    excludeTenants: ['tenant_free', 'tenant_trial'],
    group: 'premium'
)]
#[ORM\Entity]
#[ORM\Table(name: 'premium_feature')]
class PremiumFeature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $featureName;

    // ...getters/setters
}


// ──────────────────────────────────────────────
// Checking tenant access in your services
// ──────────────────────────────────────────────

namespace App\Service;

use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;

class FeatureAccessService
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext,
    ) {}

    /**
     * Check if the current tenant can access a shared entity.
     */
    public function canAccess(string $entityClass): bool
    {
        $reflection = new \ReflectionClass($entityClass);
        $attributes = $reflection->getAttributes(TenantShared::class);

        if (empty($attributes)) {
            return true; // Not a shared entity — always accessible
        }

        $tenantShared = $attributes[0]->newInstance();
        $tenantId = $this->tenantContext->getTenantId();

        if ($tenantId === null) {
            return false;
        }

        return $tenantShared->isAvailableForTenant($tenantId);
    }
}
