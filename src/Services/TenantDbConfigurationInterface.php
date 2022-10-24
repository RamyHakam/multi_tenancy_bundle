<?php

namespace Hakam\MultiTenancyBundle\Services;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
interface TenantDbConfigurationInterface
{
    /**
     * Tenant database name
     * @return string
     */
    public function getDbName(): string;

    /**
     * Tenant database user name
     * @return string
     */
    public function getDbUsername(): string;

    /**
     * Tenant database password
     * @return string|null
     */
    public function getDbPassword(): ?string;
}
