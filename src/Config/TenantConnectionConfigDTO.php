<?php

namespace Hakam\MultiTenancyBundle\Config;

use Hakam\MultiTenancyBundle\DTO\TenantDatabaseRegistrationDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com
 */
final class TenantConnectionConfigDTO
{
    private function __construct(
        public TenantDatabaseIdentifier $identifier,
        public DriverTypeEnum           $driver,
        public DatabaseStatusEnum       $dbStatus,
        public string                   $host,
        public int                      $port,
        public string                   $dbname,
        public string                   $user,
        public ?string                  $password = null
    )
    {
    }

    public static function fromArgs(
        TenantDatabaseIdentifier $identifier,
        DriverTypeEnum           $driver,
        DatabaseStatusEnum       $dbStatus,
        string                   $host,
        int                      $port,
        string                   $dbname,
        string                   $user,
        ?string                  $password = null
    ): self
    {
        return new self($identifier, $driver, $dbStatus, $host, $port, $dbname, $user, $password);
    }

    public static function fromRegistrationDTO(TenantDatabaseRegistrationDTO $dto): self
    {
        return self::fromArgs(
            identifier: TenantDatabaseIdentifier::generateWithValue($dto->dbname),
            driver: $dto->driver,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $dto->host,
            port: $dto->port,
            dbname: $dto->dbname,
            user: $dto->user,
            password: $dto->password,
        );
    }
}