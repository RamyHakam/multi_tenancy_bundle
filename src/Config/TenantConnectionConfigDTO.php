<?php

namespace Hakam\MultiTenancyBundle\Config;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com
 */
class TenantConnectionConfigDTO
{
    private function __construct(
        public ?int               $identifier,
        public DriverTypeEnum     $driver,
        public DatabaseStatusEnum $dbStatus,
        public string             $host,
        public int                $port,
        public string             $dbname,
        public string             $user,
        public ?string            $password = null
    )
    {
    }

    public static function fromArgs(
        ?int               $identifier,
        DriverTypeEnum     $driver,
        DatabaseStatusEnum $dbStatus,
        string             $host,
        int                $port,
        string             $dbname,
        string             $user,
        ?string            $password = null
    ): self
    {
        return new self($identifier, $driver, $dbStatus, $host, $port, $dbname, $user, $password);
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->driver,
            $this->dbStatus,
            $this->host,
            $this->port,
            $this->dbname,
            $this->user,
            $this->password
        );
    }
}