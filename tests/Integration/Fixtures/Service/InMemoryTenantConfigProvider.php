<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;

class InMemoryTenantConfigProvider implements TenantConfigProviderInterface
{
    /** @var array<string|int, TenantConnectionConfigDTO> */
    private array $tenants = [];

    public function addTenant(mixed $identifier, TenantConnectionConfigDTO $config): void
    {
        $this->tenants[$identifier] = $config;
    }

    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        if (!isset($this->tenants[$identifier])) {
            throw new \RuntimeException(sprintf('Tenant "%s" not found in InMemoryTenantConfigProvider.', $identifier));
        }

        return $this->tenants[$identifier];
    }

    public static function createWithDefaults(): self
    {
        $provider = new self();
        $provider->addTenant('tenant_1', TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 0,
            dbname: ':memory:',
            user: 'test',
            password: 'test'
        ));

        return $provider;
    }
}
