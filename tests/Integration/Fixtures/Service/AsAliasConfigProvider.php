<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantConfigProviderInterface::class)]
class AsAliasConfigProvider implements TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
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
}
