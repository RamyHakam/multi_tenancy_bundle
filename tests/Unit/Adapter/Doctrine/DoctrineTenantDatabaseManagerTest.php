<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Exception;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DoctrineTenantDatabaseManagerTest extends TestCase
{
    private EntityManagerInterface $em;
    private ObjectRepository $repo;
    private DoctrineDBALConnectionGeneratorInterface $connGen;
    private DoctrineTenantDatabaseManager $manager;
    private const ENTITY_CLASS = 'TenantDbConfig';
    private const IDENTIFIER_FIELD = 'id';

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(EntityRepository::class);
        $this->connGen = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);

        // stub getRepository
        $this->em->method('getRepository')
            ->with(self::ENTITY_CLASS)
            ->willReturn($this->repo);

        $this->manager = new DoctrineTenantDatabaseManager(
            $this->em,
            $this->connGen,
            self::ENTITY_CLASS,
            self::IDENTIFIER_FIELD
        );
    }

    public function testListDatabasesReturnsDtos(): void
    {
        $entity1 = $this->createConfiguredMock(TenantDbConfigurationInterface::class, [
            'getId' => 12,
            'getIdentifierValue' => 12,
            'getDriverType' => DriverTypeEnum::MYSQL,
            'getDbHost' => 'h',
            'getDatabaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED,
            'getDbPort' => 3306,
            'getDbName' => 'db',
            'getDbUserName' => 'u',
            'getDbPassword' => 'p',
        ]);
        $this->repo->expects($this->once())
            ->method('findBy')
            ->willReturn([$entity1]);

        $result = $this->manager->listDatabases();
        $this->assertCount(1, $result);
        $dto = $result[0];
        $this->assertSame(12, $dto->identifier);
        $this->assertSame('db', $dto->dbname);
    }

    public function testListDatabasesThrowsIfEmpty(): void
    {
        $this->repo->method('findBy')
            ->willReturn([]);
        $this->expectException(RuntimeException::class);
        $this->manager->listDatabases();
    }

    public function testListMissingDatabasesReturnsDtos(): void
    {
        $entity = $this->createConfiguredMock(TenantDbConfigurationInterface::class, [
            'getId' => 11,
            'getIdentifierValue' =>"Tenant ID",
            'getDriverType' => DriverTypeEnum::MYSQL,
            'getDatabaseStatus' => DatabaseStatusEnum::DATABASE_NOT_CREATED,
            'getDbHost' => 'h',
            'getDbPort' => 3306,
            'getDbName' => 'db',
            'getDbUserName' => 'u',
            'getDbPassword' => 'p',
        ]);
        $this->repo->method('findBy')
            ->willReturn([$entity]);
        $result = $this->manager->listMissingDatabases();
        $this->assertCount(1, $result);
        $this->assertSame('Tenant ID', $result[0]->identifier);
    }

    public function testListMissingDatabasesThrowsIfEmpty(): void
    {
        $this->repo->method('findBy')
            ->willReturn([]);
        $this->expectException(RuntimeException::class);
        $this->manager->listMissingDatabases();
    }

    public function testGetDefaultTenantIDatabaseReturnsDto(): void
    {
        $entity = $this->createConfiguredMock(TenantDbConfigurationInterface::class, [
            'getId' => 13,
            'getIdentifierValue' => 13,
            'getDriverType' => DriverTypeEnum::MYSQL,
            'getDbHost' => 'h',
            'getDatabaseStatus' => DatabaseStatusEnum::DATABASE_CREATED,
            'getDbPort' => 3306,
            'getDbName' => 'db',
            'getDbUserName' => 'u',
            'getDbPassword' => 'p',
        ]);
        $this->repo->method('findOneBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED])
            ->willReturn($entity);
        $dto = $this->manager->getDefaultTenantIDatabase();
        $this->assertSame(13, $dto->identifier);
    }

    public function testGetDefaultTenantIDatabaseThrowsIfNone(): void
    {
        $this->repo->method('findOneBy')
            ->willReturn(null);
        $this->expectException(RuntimeException::class);
        $this->manager->getDefaultTenantIDatabase();
    }

    public function testCreateTenantDatabaseWrapsExceptions(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: 14,
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: 'h',
            port: 3306,
            dbname: 'db',
            user: 'u',
            password: 'p',
        );
        $this->connGen->method('generateMaintenanceConnection')
            ->willThrowException(new Exception('err'));
        $this->expectException(MultiTenancyException::class);
        $this->manager->createTenantDatabase($dto);
    }

    public function testUpdateTenantDatabaseStatus(): void
    {
        $entity = $this->createMock(TenantDbConfigurationInterface::class);
        $this->repo->method('findOneBy')
            ->with([self::IDENTIFIER_FIELD => 130])
            ->willReturn($entity);
        $this->em->expects($this->once())->method('persist')->with($entity);
        $this->em->expects($this->once())->method('flush');
        $result = $this->manager->updateTenantDatabaseStatus(130, DatabaseStatusEnum::DATABASE_CREATED);
        $this->assertTrue($result);
    }

    public function testUpdateTenantDatabaseStatusThrowsIfNotFound(): void
    {
        $this->repo->method('findOneBy')->willReturn(null);
        $this->expectException(RuntimeException::class);
        $this->manager->updateTenantDatabaseStatus(10, DatabaseStatusEnum::DATABASE_CREATED);
    }

    public function testGetTenantDatabaseByIdReturnsDto(): void
    {
        $entity = $this->createConfiguredMock(TenantDbConfigurationInterface::class, [
            'getId' => 42,
            'getIdentifierValue' => 42,
            'getDriverType' => DriverTypeEnum::MYSQL,
            'getDbHost' => 'host',
            'getDatabaseStatus' => DatabaseStatusEnum::DATABASE_NOT_CREATED,
            'getDbPort' => 3306,
            'getDbName' => 'tenant_42',
            'getDbUserName' => 'user',
            'getDbPassword' => 'pass',
        ]);

        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with([self::IDENTIFIER_FIELD => 42])
            ->willReturn($entity);

        $result = $this->manager->getTenantDatabaseById(42);

        $this->assertSame(42, $result->identifier);
        $this->assertSame('tenant_42', $result->dbname);
        $this->assertSame(DatabaseStatusEnum::DATABASE_NOT_CREATED, $result->dbStatus);
    }

    public function testGetTenantDatabaseByIdThrowsIfNotFound(): void
    {
        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with([self::IDENTIFIER_FIELD => 999])
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant database with identifier "999" not found');

        $this->manager->getTenantDatabaseById(999);
    }
}
