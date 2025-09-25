<?php

namespace Hakam\MultiTenancyBundle\Port;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Interface for providing tenant connection configuration.
 *
 * @author Ramy Hakam < pencilsoft1@gmail.com>
 * */
interface TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO;
}
