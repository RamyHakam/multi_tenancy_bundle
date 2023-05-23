<?php

namespace Hakam\MultiTenancyBundle\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 *  Trait to add tenant database configuration to an entity.
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
trait TenantDbConfigTrait
{
    #[ORM\Column(type: 'string', length: 255)]
    protected string $dbName;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ["default" => null])]
    protected ?string $dbUserName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ["default" => null])]
    protected ?string $dbPassword = null;

    /**
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     * @return self
     */
    public function setDbName(string $dbName): self
    {
        $this->dbName = $dbName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDbUserName(): ?string
    {
        return $this->dbUserName;
    }

    /**
     * @param string|null $dbUser
     * @return self
     */
    public function setDbUserName(?string $dbUser = null): self
    {
        $this->dbUserName = $dbUser;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDbPassword(): ?string
    {
        return $this->dbPassword;
    }

    /**
     * @param string|null $dbPassword
     * @return self
     */
    public function setDbPassword(?string $dbPassword): self
    {
        $this->dbPassword = $dbPassword;
        return $this;
    }
}
