<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Event dispatched when the active tenant database connection is switched.
 *
 * This event is fired after the database connection has been switched to a different tenant.
 *
 * Use cases:
 * - Track tenant access for analytics
 * - Update request context
 * - Clear tenant-specific caches
 * - Log tenant access for auditing/security
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class TenantSwitchedEvent extends AbstractTenantEvent
{
    public function __construct(
        mixed $tenantIdentifier,
        TenantConnectionConfigDTO $tenantConfig,
        private readonly ?string $previousTenantIdentifier = null,
        private readonly ?string $previousDatabaseName = null,
    ) {
        parent::__construct($tenantIdentifier, $tenantConfig);
    }

    /**
     * Get the identifier of the previous tenant, if any.
     */
    public function getPreviousTenantIdentifier(): ?string
    {
        return $this->previousTenantIdentifier;
    }

    /**
     * Get the database name of the previous tenant, if any.
     */
    public function getPreviousDatabaseName(): ?string
    {
        return $this->previousDatabaseName;
    }

    /**
     * Check if there was a previous tenant before this switch.
     */
    public function hadPreviousTenant(): bool
    {
        return $this->previousTenantIdentifier !== null;
    }
}
