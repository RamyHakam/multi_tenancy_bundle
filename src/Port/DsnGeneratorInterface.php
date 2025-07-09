<?php

namespace Hakam\MultiTenancyBundle\Port;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Interface for generating DSN strings based on tenant connection configuration.
 *
 * @author Ramy Hakam < pencilsoft1@gmail.com>
 */
interface DsnGeneratorInterface
{
    /**
     * Generates a DSN string based on the provided tenant connection configuration.
     *
     * @param TenantConnectionConfigDTO $cfg The tenant connection configuration.
     * @return string The generated DSN string.
     */
    public function generate(TenantConnectionConfigDTO $cfg): string;

    /**
     * Generates a DSN string for maintenance mode, which connects to the server without specifying the tenant database.
     *
     * @param TenantConnectionConfigDTO $cfg The tenant connection configuration.
     * @return string The generated maintenance DSN string.
     */
    public function generateMaintenanceDsn(TenantConnectionConfigDTO $cfg): string;
}