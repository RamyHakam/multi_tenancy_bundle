<?php

namespace Hakam\MultiTenancyBundle\Attribute;

use Attribute;

/**
 * Marks an entity as tenant-specific, meaning it is only accessible to a specific tenant or group of tenants, with optional exclusions.
 * 
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TenantEntity
{
    /**
     * @param array $tenants List of tenant identifiers that should have access to this entity (if empty, it is available to all tenants)
     * @param string|null $group Optional grouping identifier for organizing tenant-specific entities
     */
    public function __construct(
        private array $tenants = [],
        private ?string $group = null
    ) {
    }

    /**
     * Get the list of excluded tenant identifiers.
     *
     * @return array<string>
     */
    public function getTenants(): array
    {
        return $this->tenants;
    }

    /**
     * Check if this entity is available for a specific tenant.
     * An empty tenants list means available to all.
     *
     * @param string $tenantIdentifier
     * @return bool
     */
    public function isAvailableForTenant(string $tenantIdentifier): bool
    {
        return empty($this->tenants) || in_array($tenantIdentifier, $this->tenants, true);
    }

    /**
     * Check if this entity is restricted to a specific set of tenants.
     *
     * @return bool
     */
    public function isRestrictedToSpecificTenants(): bool
    {
        return count($this->tenants) > 0;
    }

    /**
     * Get the optional group identifier.
     *
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }
}
