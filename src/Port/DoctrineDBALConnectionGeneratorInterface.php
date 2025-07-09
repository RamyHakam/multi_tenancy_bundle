<?php

namespace Hakam\MultiTenancyBundle\Port;

use Doctrine\DBAL\Connection;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Interface for generating Doctrine DBAL connection configurations based on tenant connection configuration.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
interface DoctrineDBALConnectionGeneratorInterface
{
    /**
     * Generates a Doctrine DBAL connection configuration array based on the provided tenant connection configuration.
     *
     * @param TenantConnectionConfigDTO $cfg The tenant connection configuration.
     * @return  Connection The generated Doctrine DBAL connection.
     */
    public function generate(TenantConnectionConfigDTO $cfg): Connection;

    /**
     * Generates a maintenance connection configuration for the tenant.
     *
     * This connection is used to connect to the database server without specifying a tenant database.
     *
     * @param TenantConnectionConfigDTO $cfg The tenant connection configuration.
     * @return Connection The generated maintenance connection.
     */
    public function generateMaintenanceConnection(TenantConnectionConfigDTO $cfg): Connection;
}