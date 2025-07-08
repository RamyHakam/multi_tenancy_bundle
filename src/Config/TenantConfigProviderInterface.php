<?php

namespace Hakam\MultiTenancyBundle\Config;

interface TenantConfigProviderInterface
{
    public function getTenantConnectionConfig( ?string $identifier): TenantConnectionConfigDTO;
}