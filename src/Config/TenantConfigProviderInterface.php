<?php

namespace Hakam\MultiTenancyBundle\Config;

/**
 * Interface for providing tenant connection configuration.
 *
 * @author Ramy Hakam < pencilsoft1@gmail.com>
 * */
interface TenantConfigProviderInterface
{
    public function getTenantConnectionConfig( ?string $identifier): TenantConnectionConfigDTO;
}