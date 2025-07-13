<?php

namespace Hakam\MultiTenancyBundle\DTO;

use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

final  class TenantDatabaseRegistrationDTO
{
    public function __construct(
        public readonly DriverTypeEnum $driver,
        public readonly string $host,
        public readonly int $port,
        public readonly string $dbname,
        public readonly string $user,
        public readonly ?string $password = null,
    ) {}

    public static function fromArgs(
        DriverTypeEnum $driver,
        string $host,
        int $port,
        string $dbname,
        string $user,
        ?string $password = null
    ): self {
        return new self($driver, $host, $port, $dbname, $user, $password);
    }
}