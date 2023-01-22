<?php

namespace Hakam\MultiTenancyBundle\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait to add tenant database configuration to an entity.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */ trait TenantDbConfigTrait
{
    /**
     * Use a Base32 Encoded version of UUID's
     */
    #[ORM\Column(length: 64)]
    protected string $dbName;

    #[ORM\Column(length: 255)]
    protected string $dbUserName;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $dbPassword = null;

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): self
    {
        $this->dbName = $dbName;

        return $this;
    }

    public function getDbUserName(): string
    {
        return $this->dbUserName;
    }

    public function setDbUserName(string $dbUser): self
    {
        $this->dbUserName = $dbUser;

        return $this;
    }

    public function getDbPassword(): ?string
    {
        return $this->dbPassword;
    }

    public function setDbPassword(?string $dbPassword): self
    {
        $this->dbPassword = $dbPassword;

        return $this;
    }
}
