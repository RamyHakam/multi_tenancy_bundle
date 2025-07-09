<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Adapter\Doctrine;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Connection;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator;
use Hakam\MultiTenancyBundle\Port\DsnGeneratorInterface;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

class TenantDBALConnectionGeneratorTest extends TestCase
{
    private TenantDBALConnectionGenerator $generator;
    private DsnGeneratorInterface $dsnGenerator;

    protected function setUp(): void
    {
        $this->dsnGenerator = $this->createMock(DsnGeneratorInterface::class);
        $this->generator = new TenantDBALConnectionGenerator($this->dsnGenerator);
    }

    public function testGenerateReturnsSqliteMemoryConnection(): void
    {
        $dto = TenantConnectionConfigDTO::fromArray([
            'identifier' => 'tenant1',
            'driver'     => DriverTypeEnum::SQLITE,
            'host'       => '',
            'port'       => 0,
            'dbname'     => ':memory:',
            'user'       => '',
            'password'   => null,
        ]);

        $this->dsnGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($dto)
            ->willReturn('sqlite:///:memory:');

        $connection = $this->generator->generate($dto);

        $this->assertInstanceOf(Connection::class, $connection);

        // Assert that the platform is SQLite
        $platformName = $connection->getDatabasePlatform()->getName();
        $this->assertSame('sqlite', $platformName);
    }

    public function testGenerateMaintenanceConnectionReturnsSqliteMemoryConnection(): void
    {
        $dto = TenantConnectionConfigDTO::fromArray([
            'identifier' => 'tenant1',
            'driver'     => DriverTypeEnum::SQLITE,
            'host'       => '',
            'port'       => 0,
            'dbname'     => ':memory:',
            'user'       => '',
            'password'   => null,
        ]);

        $this->dsnGenerator
            ->expects($this->once())
            ->method('generateMaintenanceDsn')
            ->with($dto)
            ->willReturn('sqlite:///:memory:');

        $connection = $this->generator->generateMaintenanceConnection($dto);

        $this->assertInstanceOf(Connection::class, $connection);

        // Assert that the platform is SQLite
        $platformName = $connection->getDatabasePlatform()->getName();
        $this->assertSame('sqlite', $platformName);
    }
}

