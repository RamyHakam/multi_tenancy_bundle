<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantDatabaseManagerInterface::class)]
class AsAliasDatabaseManager implements TenantDatabaseManagerInterface
{
    public function listDatabases(): array
    {
        return [];
    }

    public function listMissingDatabases(): array
    {
        return [];
    }

    public function getTenantDatabaseById(mixed $identifier): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            identifier: $identifier,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 0,
            dbname: ':memory:',
            user: 'test',
            password: 'test'
        );
    }

    public function getTenantDbListByDatabaseStatus(DatabaseStatusEnum $status): array
    {
        return [];
    }

    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 0,
            dbname: ':memory:',
            user: 'test',
            password: 'test'
        );
    }

    public function createTenantDatabase(TenantConnectionConfigDTO $tenantConnectionConfigDTO): bool
    {
        return true;
    }

    public function addNewTenantDbConfig(TenantConnectionConfigDTO $dto): TenantConnectionConfigDTO
    {
        return $dto;
    }

    public function updateTenantDatabaseStatus(mixed $identifier, DatabaseStatusEnum $status): bool
    {
        return true;
    }
}
