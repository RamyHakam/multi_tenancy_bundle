<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantConnectionManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

/**
 * Reading and writing tenant configuration rows lives on the connection
 * manager — it is the side that owns the repository.
 */
class DoctrineTenantConnectionManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ObjectRepository&MockObject $repo;
    private DoctrineTenantConnectionManager $manager;
    private const ENTITY_CLASS = 'TenantDbConfig';

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(EntityRepository::class);

        $this->em->method('getRepository')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->repo);

        $this->manager = new DoctrineTenantConnectionManager($this->em, self::ENTITY_CLASS);
    }

    public function testGetTenantConnectionConfigMapsTheRowOntoTheDto(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('db');
        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with(['tenantIdentifier' => $identifier])
            ->willReturn($this->tenantRow($identifier, DatabaseStatusEnum::DATABASE_MIGRATED));

        $dto = $this->manager->getTenantConnectionConfig($identifier);

        $this->assertTrue($identifier->equals($dto->identifier));
        $this->assertSame('db', $dto->dbname);
        $this->assertSame(DriverTypeEnum::MYSQL, $dto->driver);
        $this->assertSame(3306, $dto->port);
    }

    public function testGetTenantConnectionConfigThrowsWhenTheTenantIsUnknown(): void
    {
        $this->repo->method('findOneBy')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->manager->getTenantConnectionConfig(TenantDatabaseIdentifier::generateWithValue('absent'));
    }

    public function testListDatabasesReturnsDtos(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('db');
        $this->repo->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$this->tenantRow($identifier, DatabaseStatusEnum::DATABASE_MIGRATED)]);

        $result = $this->manager->listDatabases();

        $this->assertCount(1, $result);
        $this->assertTrue($identifier->equals($result[0]->identifier));
        $this->assertSame('db', $result[0]->dbname);
    }

    public function testListDatabasesThrowsIfEmpty(): void
    {
        $this->repo->method('findBy')->willReturn([]);

        $this->expectException(RuntimeException::class);

        $this->manager->listDatabases();
    }

    public function testListMissingDatabasesReturnsDtos(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('missing-db');
        $this->repo->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_NOT_CREATED])
            ->willReturn([$this->tenantRow($identifier, DatabaseStatusEnum::DATABASE_NOT_CREATED, 'missing-db')]);

        $result = $this->manager->listMissingDatabases();

        $this->assertCount(1, $result);
        $this->assertTrue($identifier->equals($result[0]->identifier));
    }

    public function testListMissingDatabasesThrowsIfEmpty(): void
    {
        $this->repo->method('findBy')->willReturn([]);

        $this->expectException(RuntimeException::class);

        $this->manager->listMissingDatabases();
    }

    public function testGetTenantDbListByDatabaseStatusFiltersOnTheGivenStatus(): void
    {
        $this->repo->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED])
            ->willReturn([$this->tenantRow(
                TenantDatabaseIdentifier::generateWithValue('db'),
                DatabaseStatusEnum::DATABASE_CREATED,
            )]);

        $result = $this->manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);

        $this->assertCount(1, $result);
        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $result[0]->dbStatus);
    }

    public function testGetDefaultTenantIDatabaseReturnsDto(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('default-db');
        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED])
            ->willReturn($this->tenantRow($identifier, DatabaseStatusEnum::DATABASE_CREATED, 'default-db'));

        $dto = $this->manager->getDefaultTenantIDatabase();

        $this->assertTrue($identifier->equals($dto->identifier));
        $this->assertSame('default-db', $dto->dbname);
    }

    public function testGetDefaultTenantIDatabaseThrowsIfNone(): void
    {
        $this->repo->method('findOneBy')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->manager->getDefaultTenantIDatabase();
    }

    public function testUpdateTenantDatabaseStatus(): void
    {
        $identifier = TenantDatabaseIdentifier::generateWithValue('db');
        $row = $this->createMock(TenantDbConfigurationInterface::class);

        $this->repo->method('findOneBy')
            ->with(['tenantIdentifier' => $identifier])
            ->willReturn($row);
        $row->expects($this->once())
            ->method('setDatabaseStatus')
            ->with(DatabaseStatusEnum::DATABASE_CREATED);
        $this->em->expects($this->once())->method('persist')->with($row);
        $this->em->expects($this->once())->method('flush');

        $this->assertTrue(
            $this->manager->updateTenantDatabaseStatus($identifier, DatabaseStatusEnum::DATABASE_CREATED),
        );
    }

    public function testUpdateTenantDatabaseStatusThrowsIfNotFound(): void
    {
        $this->repo->method('findOneBy')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->manager->updateTenantDatabaseStatus(
            TenantDatabaseIdentifier::generateWithValue('absent'),
            DatabaseStatusEnum::DATABASE_CREATED,
        );
    }

    private function tenantRow(
        TenantDatabaseIdentifier $identifier,
        DatabaseStatusEnum $status,
        string $dbName = 'db',
    ): TenantDbConfigurationInterface {
        return $this->createConfiguredMock(TenantDbConfigurationInterface::class, [
            'getTenantIdentifier' => $identifier,
            'getDriverType' => DriverTypeEnum::MYSQL,
            'getDatabaseStatus' => $status,
            'getDbHost' => 'h',
            'getDbPort' => 3306,
            'getDbName' => $dbName,
            'getDbUserName' => 'u',
            'getDbPassword' => 'p',
        ]);
    }
}
