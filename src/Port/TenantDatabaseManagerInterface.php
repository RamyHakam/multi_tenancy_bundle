<?php

namespace Hakam\MultiTenancyBundle\Port;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;

/**
 * Interface for managing tenant databases.
 *
 * This interface defines methods for listing existing tenant databases,
 * identifying missing tenant databases, and retrieving the default tenant
 * database configuration.
 *
 * @author Ramy Hakam <pencilsoft1@gmil.com
 * */
interface TenantDatabaseManagerInterface
{
    /**
     * @return TenantConnectionConfigDTO[]  All databases that currently exist and prepared for tenants
     * this function is used to sync all the tenant databases with the latest migrations and fixtures at once
     */
    public function listDatabases(): array;

    /**
     * @return TenantConnectionConfigDTO[]  Tenant databases that are not yet created
     * this function is used to identify which tenant databases are missing and need to be created
     */
    public function listMissingDatabases(): array;

    /**
     * Get a list of tenant databases filtered by their status.
     *
     * @param DatabaseStatusEnum $status The status to filter tenant databases by.
     * @return TenantConnectionConfigDTO[] An array of tenant database configurations matching the specified status.
     */

    public function getTenantDbListByDatabaseStatus(DatabaseStatusEnum $status): array;

    /**
     * The “default” tenant database configuration for migrations and fixtures management.
     * This is the database that will be used when no specific tenant is provided.
     */
    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO;

    /**
     * Create a new tenant database based on the provided configuration.
     *
     * @param TenantConnectionConfigDTO $tenantConnectionConfigDTO The configuration for the new tenant database.
     * @return bool True if the database was created successfully, false otherwise.
     */
    public function createTenantDatabase(TenantConnectionConfigDTO $tenantConnectionConfigDTO): bool;

    public function addNewTenantDbConfig(TenantConnectionConfigDTO $dto): TenantConnectionConfigDTO;

    // update Db Status
    /**
     * Update the status of a tenant database.
     *
     * @param string $identifier The identifier of the tenant database to update.
     * @param DatabaseStatusEnum $status The new status to set for the tenant database.
     * @return bool True if the status was updated successfully, false otherwise.
     */
    public function updateTenantDatabaseStatus(int $identifier, DatabaseStatusEnum $status): bool;

//    /**
//     * Delete a tenant database by its identifier.
//     *
//     * @param string $identifier The identifier of the tenant database to delete.
//     * @return bool True if the database was deleted successfully, false otherwise.
//     */
//    public function deleteTenantDatabase(string $identifier): bool;
}
