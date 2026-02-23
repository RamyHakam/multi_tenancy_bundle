<?php

namespace Hakam\MultiTenancyBundle\Attribute;

use Attribute;

/**
 * Marks an entity as shared across tenants with optional exclusions.
 * 
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TenantShared
{
    /**
     * @param array<string> $excludeTenants List of tenant identifiers that should NOT have access to this entity
     * @param string|null $group Optional grouping identifier for organizing shared entities
     */
    public function __construct(
        private array $excludeTenants = [],
        private ?string $group = null
    ) {
    }

    /**
     * Get the list of excluded tenant identifiers.
     *
     * @return array<string>
     */
    public function getExcludeTenants(): array
    {
        return $this->excludeTenants;
    }

    /**
     * Check if this entity is available for a specific tenant.
     *
     * @param string $tenantIdentifier
     * @return bool
     */
    public function isAvailableForTenant(string $tenantIdentifier): bool
    {
        return !in_array($tenantIdentifier, $this->excludeTenants, true);
    }

    /**
     * Check if there are any tenant exclusions.
     *
     * @return bool
     */
    public function hasExclusions(): bool
    {
        return count($this->excludeTenants) > 0;
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
