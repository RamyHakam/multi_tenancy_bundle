<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Event dispatched when a new tenant database is created.
 *
 * This event is fired after the database has been successfully created,
 * but before any migrations or fixtures have been applied.
 *
 * Use cases:
 * - Trigger billing/subscription setup
 * - Send welcome notifications
 * - Initialize external services for the tenant
 * - Log tenant creation for auditing
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class TenantCreatedEvent extends AbstractTenantEvent
{
    public function __construct(
        mixed $tenantIdentifier,
        TenantConnectionConfigDTO $tenantConfig,
        private readonly string $databaseName,
    ) {
        parent::__construct($tenantIdentifier, $tenantConfig);
    }

    /**
     * Get the name of the created database.
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }
}
