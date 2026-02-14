<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Event dispatched when migrations have been executed on a tenant database.
 *
 * This event is fired after migrations have been successfully applied,
 * either during initial setup (init) or during updates (update).
 *
 * Use cases:
 * - Log migration history for the tenant
 * - Trigger post-migration tasks (e.g., cache warming)
 * - Notify administrators of schema changes
 * - Update tenant status in external systems
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class TenantMigratedEvent extends AbstractTenantEvent
{
    public const TYPE_INIT = 'init';
    public const TYPE_UPDATE = 'update';

    public function __construct(
        mixed $tenantIdentifier,
        TenantConnectionConfigDTO $tenantConfig,
        private readonly string $migrationType,
        private readonly ?string $toVersion = null,
    ) {
        parent::__construct($tenantIdentifier, $tenantConfig);
    }

    /**
     * Get the migration type (init or update).
     */
    public function getMigrationType(): string
    {
        return $this->migrationType;
    }

    /**
     * Check if this is an initial migration.
     */
    public function isInitialMigration(): bool
    {
        return $this->migrationType === self::TYPE_INIT;
    }

    /**
     * Check if this is an update migration.
     */
    public function isUpdateMigration(): bool
    {
        return $this->migrationType === self::TYPE_UPDATE;
    }

    /**
     * Get the target version of the migration.
     */
    public function getToVersion(): ?string
    {
        return $this->toVersion;
    }
}
