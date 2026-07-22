<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Adapter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DoctrineTenantDatabaseManagerTest extends TestCase
{
    private DoctrineTenantDatabaseManager $manager;
    private EntityManagerInterface&MockObject $entityManager;
    private EntityRepository&MockObject $repository;
    private DoctrineDBALConnectionGeneratorInterface&MockObject $connectionGenerator;
    private string $tenantDbEntityClassName;
    private string $tenantDbIdentifier;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->connectionGenerator = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);
        $this->tenantDbEntityClassName = 'App\Entity\TenantDb';
        $this->tenantDbIdentifier = 'id'; // Default identifier
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with($this->tenantDbEntityClassName)
            ->willReturn($this->repository);

        $this->manager = new DoctrineTenantDatabaseManager(
            $this->entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            $this->tenantDbIdentifier
        );
    }

    /**
     * Test that convertToDTO uses getIdentifierValue() for the identifier field
     */
    public function testConvertToDtoUsesGetIdentifierValue(): void
    {
        // Create a custom tenant entity that returns a custom identifier via getIdentifierValue()
        $tenantDb = new class implements TenantDbConfigurationInterface {
            public function getId(): ?int { return 123; } // Entity ID
            public function getIdentifierValue(): mixed { return 'TENANT_XYZ'; } // Custom identifier value
            public function getDbName(): string { return 'test_db'; }
            public function getDbUsername(): ?string { return 'test_user'; }
            public function getDbPassword(): ?string { return 'test_pass'; }
            public function getDbHost(): ?string { return 'localhost'; }
            public function getDbPort(): ?int { return 3306; }
            public function getDatabaseStatus(): DatabaseStatusEnum { return DatabaseStatusEnum::DATABASE_MIGRATED; }
            public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): self { return $this; }
            public function getDsnUrl(): string { return 'mysql://test'; }
            public function getDriverType(): DriverTypeEnum { return DriverTypeEnum::MYSQL; }
        };

        // Setup repository to return our custom entity
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $customManager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            'tenant_code'
        );

        $result = $customManager->listDatabases();
        
        // convertToDTO should use getIdentifierValue(), not getId()
        $this->assertCount(1, $result);
        $this->assertEquals('TENANT_XYZ', $result[0]->identifier);
        $this->assertNotEquals(123, $result[0]->identifier);
    }

    /**
     * Test that convertToDTO correctly uses getIdentifierValue() with mocked entity
     */
    public function testConvertToDtoWithMockedIdentifierValue(): void
    {
        $tenantDb = $this->createTenantDbMockWithIdentifier('TENANT_XYZ');

        // Setup repository to return our mock
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $customManager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            'tenant_code'
        );

        $result = $customManager->listDatabases();
        
        $this->assertCount(1, $result);
        $this->assertEquals('TENANT_XYZ', $result[0]->identifier);
    }

    /**
     * Test convertToDTO with default 'id' identifier uses getIdentifierValue()
     */
    public function testConvertToDtoWithDefaultIdIdentifier(): void
    {
        $tenantDb = $this->createTenantDbMockWithIdentifier(456);

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb]);

        $result = $this->manager->listDatabases();
        
        $this->assertCount(1, $result);
        $this->assertEquals(456, $result[0]->identifier);
    }

    /**
     * Test convertToDTO handles null from getIdentifierValue()
     */
    public function testConvertToDtoHandlesNullIdentifierValue(): void
    {
        $tenantDb = $this->createTenantDbMockWithIdentifier(null);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $customManager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            'some_field'
        );

        $result = $customManager->listDatabases();
        
        $this->assertCount(1, $result);
        $this->assertNull($result[0]->identifier);
    }

    /**
     * Test convertToDTO with custom implementation of getIdentifierValue()
     */
    public function testConvertToDtoWithCustomIdentifierImplementation(): void
    {
        // Create entity that implements getIdentifierValue() with custom logic
        $tenantDb = new class implements TenantDbConfigurationInterface {
            public function getId(): ?int { return 999; }
            public function getIdentifierValue(): mixed { return 'custom-slug-value'; }
            public function getDbName(): string { return 'test_db'; }
            public function getDbUsername(): ?string { return 'test_user'; }
            public function getDbPassword(): ?string { return 'test_pass'; }
            public function getDbHost(): ?string { return 'localhost'; }
            public function getDbPort(): ?int { return 3306; }
            public function getDatabaseStatus(): DatabaseStatusEnum { return DatabaseStatusEnum::DATABASE_MIGRATED; }
            public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): self { return $this; }
            public function getDsnUrl(): string { return 'mysql://test'; }
            public function getDriverType(): DriverTypeEnum { return DriverTypeEnum::MYSQL; }
        };

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $customManager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            'tenant_slug'
        );

        $result = $customManager->listDatabases();
        
        // Should use getIdentifierValue() which returns 'custom-slug-value'
        $this->assertCount(1, $result);
        $this->assertEquals('custom-slug-value', $result[0]->identifier);
        $this->assertNotEquals(999, $result[0]->identifier);
    }

    /**
     * Test that getTenantDatabaseById uses correct identifier field for lookup
     */
    public function testGetTenantDatabaseByIdUsesCorrectIdentifierField(): void
    {
        $customIdentifier = 'tenant_code';
        $repository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $customManager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $this->connectionGenerator,
            $this->tenantDbEntityClassName,
            $customIdentifier
        );

        $tenantDb = $this->createTenantDbMockWithIdentifier('LOOKUP_TENANT');

        // Verify it searches by the correct field
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([$customIdentifier => 'LOOKUP_TENANT'])
            ->willReturn($tenantDb);

        $result = $customManager->getTenantDatabaseById('LOOKUP_TENANT');
        
        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $result);
        $this->assertEquals('LOOKUP_TENANT', $result->identifier);
    }

    public function testGetTenantDatabaseByIdThrowsExceptionWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant database with identifier "123" not found');

        $this->manager->getTenantDatabaseById(123);
    }

    public function testListDatabasesThrowsExceptionWhenNoDatabasesFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tenant databases found in repository');

        $this->manager->listDatabases();
    }

    public function testListDatabasesReturnsCorrectDTOs(): void
    {
        $tenantDb1 = $this->createTenantDbMockWithIdentifierAndDbName(1, 'tenant1_db');
        $tenantDb2 = $this->createTenantDbMockWithIdentifierAndDbName(2, 'tenant2_db');

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$tenantDb1, $tenantDb2]);

        $result = $this->manager->listDatabases();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $result[0]);
        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $result[1]);
        $this->assertEquals('tenant1_db', $result[0]->dbname);
        $this->assertEquals('tenant2_db', $result[1]->dbname);
    }

    public function testUpdateTenantDatabaseStatusSuccess(): void
    {
        $tenantDb = $this->createTenantDbMock();
        
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn($tenantDb);

        $tenantDb->expects($this->once())
            ->method('setDatabaseStatus')
            ->with(DatabaseStatusEnum::DATABASE_MIGRATED)
            ->willReturn($tenantDb);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($tenantDb);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->manager->updateTenantDatabaseStatus(123, DatabaseStatusEnum::DATABASE_MIGRATED);

        $this->assertTrue($result);
    }

    private function createTenantDbMock(): TenantDbConfigurationInterface&MockObject
    {
        return $this->createTenantDbMockWithIdentifier(1);
    }

    private function createTenantDbMockWithIdentifier(mixed $identifierValue): TenantDbConfigurationInterface&MockObject
    {
        return $this->createTenantDbMockWithIdentifierAndDbName($identifierValue, 'test_db');
    }

    private function createTenantDbMockWithIdentifierAndDbName(mixed $identifierValue, string $dbName): TenantDbConfigurationInterface&MockObject
    {
        $mock = $this->createMock(TenantDbConfigurationInterface::class);
        
        $mock->method('getId')->willReturn(1);
        $mock->method('getIdentifierValue')->willReturn($identifierValue);
        $mock->method('getDbName')->willReturn($dbName);
        $mock->method('getDbUsername')->willReturn('test_user');
        $mock->method('getDbPassword')->willReturn('test_pass');
        $mock->method('getDbHost')->willReturn('localhost');
        $mock->method('getDbPort')->willReturn(3306);
        $mock->method('getDatabaseStatus')->willReturn(DatabaseStatusEnum::DATABASE_MIGRATED);
        $mock->method('getDsnUrl')->willReturn('mysql://test');
        $mock->method('getDriverType')->willReturn(DriverTypeEnum::MYSQL);
        $mock->method('setDatabaseStatus')->willReturnSelf();

        return $mock;
    }
}
