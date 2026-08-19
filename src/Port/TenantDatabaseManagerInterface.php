<?php

namespace Hakam\MultiTenancyBundle\Port;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Interface for managing tenant databases.
 *
 * Creating and dropping the databases themselves; reading and writing their
 * configuration rows is TenantConnectionManagerInterface's job.
 *
 * @author Ramy Hakam <pencilsoft1@gmil.com
 * */
interface TenantDatabaseManagerInterface
{
    /**
     * Create a new tenant database based on the provided configuration.
     *
     * @param TenantConnectionConfigDTO $tenantConnectionConfigDTO The configuration for the new tenant database.
     * @return bool True if the database was created successfully, false otherwise.
     */
    public function createTenantDatabase(TenantConnectionConfigDTO $tenantConnectionConfigDTO): bool;
}
