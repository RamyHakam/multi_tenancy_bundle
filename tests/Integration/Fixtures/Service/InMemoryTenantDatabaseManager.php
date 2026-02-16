<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;

class InMemoryTenantDatabaseManager implements TenantDatabaseManagerInterface
{
    /** @var array<string|int, TenantConnectionConfigDTO> */
    private array $databases = [];

    public function listDatabases(): array
    {
        return array_values(array_filter(
            $this->databases,
            fn (TenantConnectionConfigDTO $dto) => $dto->dbStatus !== DatabaseStatusEnum::DATABASE_NOT_CREATED
        ));
    }

    public function listMissingDatabases(): array
    {
        return array_values(array_filter(
            $this->databases,
            fn (TenantConnectionConfigDTO $dto) => $dto->dbStatus === DatabaseStatusEnum::DATABASE_NOT_CREATED
        ));
    }

    public function getTenantDatabaseById(mixed $identifier): TenantConnectionConfigDTO
    {
        if (!isset($this->databases[$identifier])) {
            throw new \RuntimeException(sprintf('Tenant database "%s" not found.', $identifier));
        }

        return $this->databases[$identifier];
    }

    public function getTenantDbListByDatabaseStatus(DatabaseStatusEnum $status): array
    {
        return array_values(array_filter(
            $this->databases,
            fn (TenantConnectionConfigDTO $dto) => $dto->dbStatus === $status
        ));
    }

    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO
    {
        return reset($this->databases) ?: TenantConnectionConfigDTO::fromArgs(
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
        $this->databases[$tenantConnectionConfigDTO->identifier] = $tenantConnectionConfigDTO;

        return true;
    }

    public function addNewTenantDbConfig(TenantConnectionConfigDTO $dto): TenantConnectionConfigDTO
    {
        $this->databases[$dto->identifier] = $dto;

        return $dto;
    }

    public function updateTenantDatabaseStatus(mixed $identifier, DatabaseStatusEnum $status): bool
    {
        if (!isset($this->databases[$identifier])) {
            return false;
        }

        $old = $this->databases[$identifier];
        $this->databases[$identifier] = TenantConnectionConfigDTO::fromArgs(
            identifier: $old->identifier,
            driver: $old->driver,
            dbStatus: $status,
            host: $old->host,
            port: $old->port,
            dbname: $old->dbname,
            user: $old->user,
            password: $old->password
        );

        return true;
    }
}
