<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Hakam\MultiTenancyBundle\Adapter\DefaultDsnGenerator;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use PHPUnit\Framework\TestCase;

class DsnGeneratorIntegrationTest extends TestCase
{
    private DefaultDsnGenerator $dsnGenerator;
    private TenantDBALConnectionGenerator $connectionGenerator;

    protected function setUp(): void
    {
        $this->dsnGenerator = new DefaultDsnGenerator();
        $this->connectionGenerator = new TenantDBALConnectionGenerator($this->dsnGenerator);
    }

    public function testSqliteDsnCreatesWorkingConnection(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
            host: '',
            port: 0,
            dbname: ':memory:',
            user: '',
            password: null,
        );

        $dsn = $this->dsnGenerator->generate($dto);
        $this->assertStringStartsWith('sqlite:///', $dsn);
    }

    public function testSqliteMaintenanceDsnMatchesRegularDsn(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
            host: '',
            port: 0,
            dbname: ':memory:',
            user: '',
            password: null,
        );

        $regularDsn = $this->dsnGenerator->generate($dto);
        $maintenanceDsn = $this->dsnGenerator->generateMaintenanceDsn($dto);

        // For SQLite, maintenance DSN should be same as regular DSN
        $this->assertSame($regularDsn, $maintenanceDsn);
    }

    public function testTenantDBALConnectionGeneratorProducesWorkingConnection(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
            host: '',
            port: 0,
            dbname: ':memory:',
            user: '',
            password: null,
        );

        $connection = $this->connectionGenerator->generate($dto);
        $this->assertInstanceOf(Connection::class, $connection);

        // Verify we can execute a query
        $result = $connection->executeQuery('SELECT 1 as val');
        $this->assertEquals(1, $result->fetchOne());

        $connection->close();
    }

    public function testTenantDBALConnectionGeneratorMaintenanceProducesWorkingConnection(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 1,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
            host: '',
            port: 0,
            dbname: ':memory:',
            user: '',
            password: null,
        );

        $connection = $this->connectionGenerator->generateMaintenanceConnection($dto);
        $this->assertInstanceOf(Connection::class, $connection);

        $result = $connection->executeQuery('SELECT 1 as val');
        $this->assertEquals(1, $result->fetchOne());

        $connection->close();
    }
}
