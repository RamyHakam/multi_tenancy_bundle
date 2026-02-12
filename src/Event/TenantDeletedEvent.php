<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

/**
 * Event dispatched when a tenant database is deleted/dropped.
 *
 * This event is fired after the database has been successfully dropped.
 *
 * Use cases:
 * - Clean up external resources associated with the tenant
 * - Cancel billing/subscriptions
 * - Archive tenant data
 * - Send notification about tenant deletion
 * - Update monitoring/logging systems
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class TenantDeletedEvent extends AbstractTenantEvent
{
    public function __construct(
        mixed $tenantIdentifier,
        private readonly string $databaseName,
    ) {
        parent::__construct($tenantIdentifier);
    }

    /**
     * Get the name of the deleted database.
     */
    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }
}
