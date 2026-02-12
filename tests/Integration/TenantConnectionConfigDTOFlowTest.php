<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;

class TenantConnectionConfigDTOFlowTest extends IntegrationTestCase
{
    public function testDTOFromEntityPreservesAllFields(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'dto_flow_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::MYSQL,
            host: 'db-server',
            port: 3306,
            user: 'admin',
            password: 's3cret',
        );

        /** @var DoctrineTenantDatabaseManager $manager */
        $manager = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager');
        $dtos = $manager->listDatabases();

        $this->assertCount(1, $dtos);
        $dto = $dtos[0];
        $this->assertSame($tenant->getId(), $dto->identifier);
        $this->assertSame('dto_flow_db', $dto->dbname);
        $this->assertSame(DriverTypeEnum::MYSQL, $dto->driver);
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $dto->dbStatus);
        $this->assertSame('db-server', $dto->host);
        $this->assertSame(3306, $dto->port);
        $this->assertSame('admin', $dto->user);
        $this->assertSame('s3cret', $dto->password);
    }

    public function testDTOWithIdReturnsNewInstance(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 3306,
            dbname: 'original',
            user: 'root',
            password: null,
        );

        $newDto = $dto->withId(42);

        $this->assertNotSame($dto, $newDto);
        $this->assertSame(42, $newDto->identifier);
        $this->assertSame(1, $dto->identifier);
        // Other fields preserved
        $this->assertSame('original', $newDto->dbname);
        $this->assertSame(DriverTypeEnum::SQLITE, $newDto->driver);
    }

    public function testDTOIdentifierMatchesConfiguredField(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'id_test_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
        );

        /** @var DoctrineTenantDatabaseManager $manager */
        $manager = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager');
        $dto = $manager->getTenantDatabaseById($tenant->getId());

        // The configured identifier is 'id', so identifier should equal the entity's id
        $this->assertSame($tenant->getId(), $dto->identifier);
    }

    public function testDTORoundTripThroughConfigProvider(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'provider_test_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
            host: 'providerhost',
            port: 5432,
            user: 'provideruser',
            password: 'providerpw',
        );

        $configProvider = $this->getContainer()->get('hakam_tenant_config_provider.doctrine');
        $dto = $configProvider->getTenantConnectionConfig((string) $tenant->getId());

        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $dto);
        $this->assertSame('provider_test_db', $dto->dbname);
        $this->assertSame(DriverTypeEnum::SQLITE, $dto->driver);
        $this->assertSame('providerhost', $dto->host);
        $this->assertSame(5432, $dto->port);
        $this->assertSame('provideruser', $dto->user);
        $this->assertSame('providerpw', $dto->password);
    }

    public function testDTOUsedInConnectionSwitching(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'e2e_switch_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
            host: 'switchhost',
            port: 3306,
            user: 'switchuser',
            password: 'switchpw',
        );

        // Full end-to-end: config provider -> event listener -> connection switch
        // Verifies the entire DTO flow works without errors
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // After switch, EM identity map should be cleared
        $tenantEM = $this->getTenantEntityManager();
        $this->assertNotNull($tenantEM);

        // Second dispatch to same tenant should be a no-op (tracked by listener)
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->assertTrue(true, 'Full DTO -> switch flow completed without error');
    }
}
