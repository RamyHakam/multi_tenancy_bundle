<?php

namespace Hakam\MultiTenancyBundle\Config;

use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com
 */
class TenantConnectionConfigDTO
{
    private function __construct(
        public int         $identifier,
        public DriverTypeEnum $driver,
        public string         $host,
        public int            $port,
        public string         $dbname,
        public string         $user,
        public ?string        $password = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['identifier'],
            $data['driver'],
            $data['host'],
            $data['port'],
            $data['dbname'],
            $data['user'],
            $data['password'] ?? null
        );
    }
}