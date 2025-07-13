<?php

namespace Hakam\MultiTenancyBundle\Services;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
interface TenantDbConfigurationInterface
{
    /**
     * Tenant database id.
     */
    public function getId(): ?int;

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

    /**
     * Tenant databasehost.
     */
    public function getDbHost(): ?string;

    /**
     * Tenant database port.
     */
    public function getDbPort(): ?int;

    /**
     * Tenant database status.
     */
    public function getDatabaseStatus(): DatabaseStatusEnum;

    /**
     * Tenant database status.
     */
    public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): self;

    public function getDsnUrl(): string;
    
    public function getDriverType(): DriverTypeEnum;

    public function getTenantIdentifier(): string;
}
