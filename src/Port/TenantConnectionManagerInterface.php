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
}