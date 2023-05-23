<?php

namespace Hakam\MultiTenancyBundle\Services;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
interface TenantDbConfigurationInterface
{
    /**
     * Tenant database name.
     */
    public function getDbName(): string;

    /**
     * Tenant database user name.
     */
    public function getDbUsername(): ?string;

    /**
     * Tenant database password.
     */
    public function getDbPassword(): ?string;
}
