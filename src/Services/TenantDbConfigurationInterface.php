<?php


namespace Hakam\MultiTenancyBundle\Services;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
Interface TenantDbConfigurationInterface
{
    /**
     * Tenant database name
     * @return string
     */
    public function getDbName();

    /**
     * Tenant database user name
     * @return string
     */
    public function getDbUsername();

    /**
     * Tenant database password
     * @return mixed|null
     */
    public function getDbPassword();
}