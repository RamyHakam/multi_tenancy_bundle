<?php

namespace Hakam\MultiTenancyBundle\Port;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\DTO\TenantDatabaseRegistrationDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;

/**
 * Interface for providing tenant connection configuration.
 *
 * @author Ramy Hakam < pencilsoft1@gmail.com>
 * */
interface TenantConnectionManagerInterface
{
    /**
     * Get the configuration for a tenant connection based on the provided identifier.
     *
     * @param int|null $identifier The identifier of the tenant connection. If null, the default tenant connection is returned.
     * @return TenantConnectionConfigDTO The configuration of the tenant connection.
     */
    public function getTenantConnectionConfig( TenantDatabaseIdentifier $identifier): TenantConnectionConfigDTO;

    /**
     * Register a new tenant database with the provided configuration.
     *
     * @param TenantDatabaseRegistrationDTO $registrationDTO The registration data transfer object containing the tenant database configuration.
     * @return TenantConnectionConfigDTO The configuration of the registered tenant database.
     */
    public function registerTenantDatabase(TenantDatabaseRegistrationDTO $registrationDTO): TenantConnectionConfigDTO;

    /**
     * update the status of a tenant database after creation or migration.
     *
     * @param TenantDatabaseIdentifier $identifier
     * @param DatabaseStatusEnum $status
     * @return bool True if the database was created successfully, false otherwise.
     */
    public function updateTenantDatabaseStatus(TenantDatabaseIdentifier $identifier, DatabaseStatusEnum $status): bool;

    /**
     * Get a list of tenant databases filtered by their status.
     *
     * @return TenantConnectionConfigDTO[]
     */
    public function getTenantDbListByDatabaseStatus(DatabaseStatusEnum $status): array;

    /**
     * @return TenantConnectionConfigDTO[] All databases that exist and are migrated, so they can be
     *                                     synced with the latest migrations and fixtures at once.
     */
    public function listDatabases(): array;

    /**
     * @return TenantConnectionConfigDTO[] Tenant databases that are registered but not created yet.
     */
    public function listMissingDatabases(): array;

    /**
     * The "default" tenant database configuration for migrations and fixtures management —
     * used when no specific tenant is provided.
     */
    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO;
}