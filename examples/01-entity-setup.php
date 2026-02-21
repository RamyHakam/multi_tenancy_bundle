<?php

/**
 * Example 1: Tenant Database Configuration Entity
 *
 * Every application using this bundle needs an entity that stores
 * the connection details for each tenant database.
 *
 * Use TenantDbConfigTrait for the standard fields, then implement
 * TenantDbConfigurationInterface. The trait provides: dbName, driverType,
 * dbUserName, dbPassword, dbHost, dbPort, databaseStatus.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\Traits\TenantDbConfigTrait;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_db_config')]
class TenantDbConfig implements TenantDbConfigurationInterface
{
    use TenantDbConfigTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * You can add custom fields specific to your application.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $plan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * IMPORTANT: PHP method names are case-insensitive.
     * The trait defines getDbUserName() and the interface defines getDbUsername().
     * These are the SAME method in PHP, so this alias must access
     * the property directly to avoid infinite recursion.
     */
    public function getDbUsername(): ?string
    {
        return $this->dbUserName;
    }

    /**
     * Required by TenantDbConfigurationInterface.
     * Returns the value used as the tenant identifier (configured via
     * tenant_database_identifier in your bundle config).
     */
    public function getIdentifierValue(): mixed
    {
        return $this->id;
    }

    // Custom getters/setters for your application fields

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(?string $plan): self
    {
        $this->plan = $plan;
        return $this;
    }
}
