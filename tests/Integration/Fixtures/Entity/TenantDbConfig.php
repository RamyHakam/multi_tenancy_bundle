<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifierValue(): mixed
    {
        return $this->id;
    }

    // The interface defines getDbUsername() but the trait provides getDbUserName()
    // PHP method names are case-insensitive, so we must access the property directly
    public function getDbUsername(): ?string
    {
        return $this->dbUserName;
    }
}
