<?php

namespace Hakam\MultiTenancyBundle\Services;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;

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
     * Tenant database status.
     */
    public function getDatabaseStatus(): DatabaseStatusEnum;

    /**
     * Tenant database status.
     */
    public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): self;
}
