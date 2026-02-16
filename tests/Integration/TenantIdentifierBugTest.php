<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\Tests\Functional\MultiTenancyBundleTestingKernel;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for Issue #64: Migration command forces dbId with getId() instead of using the configured identifier
 * 
 * This test uses real YAML configuration with a custom tenant_database_identifier
 * to demonstrate the bug where convertToDTO() hardcodes getId() instead of using the configured field.
 */
class TenantIdentifierBugTest extends TestCase
{
    private MultiTenancyBundleTestingKernel $kernel;

    protected function setUp(): void
    {
        // Create configuration with custom tenant identifier
        $config = [
            'tenant_database_className' => 'Hakam\\MultiTenancyBundle\\Tests\\Integration\\TestTenantDbEntity',
            'tenant_database_identifier' => 'tenant_code', // ← Custom identifier field (NOT 'id')
            'tenant_config_provider' => 'hakam_tenant_config_provider.doctrine',
            'tenant_connection' => [
                'url' => 'sqlite:///:memory:',
                'host' => 'localhost',
                'driver' => 'pdo_sqlite',
                'charset' => 'utf8',
                'server_version' => '3.31'
            ],
            'tenant_migration' => [
                'tenant_migration_namespace' => 'DoctrineMigrations\\Tenant',
                'tenant_migration_path' => '%kernel.project_dir%/migrations/Tenant'
            ],
            'tenant_entity_manager' => [
                'tenant_naming_strategy' => 'doctrine.orm.naming_strategy.default',
                'mapping' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src/Entity',
                    'prefix' => 'App\\Tenant',
                    'alias' => 'Tenant'
                ]
            ]
        ];

        $this->kernel = new MultiTenancyBundleTestingKernel($config);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    /**
     * Test that validates Issue #64 is FIXED: convertToDTO now uses configured identifier
     * 
     * This test verifies that when 'tenant_code' is configured as the identifier field,
     * the DoctrineTenantDatabaseManager correctly returns the tenant_code value instead of the entity ID.
     */
    public function testIssue64FixedConvertToDtoUsesConfiguredIdentifier(): void
    {
        $container = $this->kernel->getContainer();
        
        // Verify our custom configuration was loaded
        $this->assertEquals('tenant_code', $container->getParameter('hakam.tenant_db_identifier'));
        $this->assertEquals('Hakam\\MultiTenancyBundle\\Tests\\Integration\\TestTenantDbEntity', $container->getParameter('hakam.tenant_db_list_entity'));

        // Create a test tenant entity with different ID and tenant_code values
        $testTenant = new TestTenantDbEntity();
        $testTenant->setId(999);
        $testTenant->setTenantCode('CUSTOM_TENANT_123');
        $testTenant->setDriverType(DriverTypeEnum::MYSQL);
        $testTenant->setDatabaseStatus(DatabaseStatusEnum::DATABASE_MIGRATED);
        $testTenant->setDbHost('localhost');
        $testTenant->setDbPort(3306);
        $testTenant->setDbName('custom_tenant_db');
        $testTenant->setDbUserName('tenant_user');
        $testTenant->setDbPassword('tenant_pass');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->method('getRepository')->willReturn($repository);
        $repository->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED])
            ->willReturn([$testTenant]);

        $connectionGenerator = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);

        $manager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $connectionGenerator,
            'Hakam\\MultiTenancyBundle\\Tests\\Integration\\TestTenantDbEntity',
            'tenant_code'
        );

        // Execute the method that has the bug
        $dtoList = $manager->listDatabases();

        // BUG DEMONSTRATION: This assertion will PASS, proving the bug exists
        // The DTO identifier contains 999 (from getId()) instead of 'CUSTOM_TENANT_123' (from getTenantCode())
        $this->assertCount(1, $dtoList);
        $dto = $dtoList[0];
        
        // ISSUE #64 IS NOW FIXED! ✅
        // The DTO now correctly contains the configured identifier field value instead of the entity ID
        $this->assertEquals('CUSTOM_TENANT_123', $dto->identifier, 
            'ISSUE #64 FIXED: convertToDTO now correctly uses configured identifier field value!');
        
        // Should NOT be the entity ID anymore
        $this->assertNotEquals(999, $dto->identifier, 'Should not use entity ID when custom identifier is configured');
        
        // Additional verification that our test entity has the correct values
        $this->assertEquals('CUSTOM_TENANT_123', $testTenant->getTenantCode());
        $this->assertEquals(999, $testTenant->getId());
    }

    /**
     * Test that shows the impact on migration commands
     * 
     * This test demonstrates how the bug affects migration commands by showing that
     * the identifier in the DTO doesn't match what the tenant lookup expects.
     */
    public function testIssue64ImpactOnMigrationCommands(): void
    {
        $container = $this->kernel->getContainer();
        
        // Create test tenant with custom identifier
        $testTenant = new TestTenantDbEntity();
        $testTenant->setId(888);
        $testTenant->setTenantCode('MIGRATION_TENANT');
        $testTenant->setDriverType(DriverTypeEnum::MYSQL);
        $testTenant->setDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
        $testTenant->setDbHost('localhost');
        $testTenant->setDbPort(3306);
        $testTenant->setDbName('migration_test_db');
        $testTenant->setDbUserName('test_user');
        $testTenant->setDbPassword('test_pass');

        // Mock repository and entity manager
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        
        $entityManager->method('getRepository')->willReturn($repository);
        
        // When getting tenants by status, return our test tenant
        $repository->method('findBy')
            ->with(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED])
            ->willReturn([$testTenant]);

        // When looking up by configured identifier field (tenant_code), return our tenant
        $repository->method('findOneBy')
            ->with(['tenant_code' => 'MIGRATION_TENANT'])
            ->willReturn($testTenant);

        $connectionGenerator = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);

        $manager = new DoctrineTenantDatabaseManager(
            $entityManager,
            $connectionGenerator,
            'Hakam\\MultiTenancyBundle\\Tests\\Integration\\TestTenantDbEntity',
            'tenant_code'
        );

        // Get list of tenants to migrate (what migration command would do)
        $tenantsToMigrate = $manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
        
        $this->assertCount(1, $tenantsToMigrate);
        $tenantDto = $tenantsToMigrate[0];

        // ISSUE #64 FIXED: DTO now contains the configured identifier field value
        $this->assertEquals('MIGRATION_TENANT', $tenantDto->identifier,
            'DTO should contain configured identifier field value, not entity ID');

        // Now look up the tenant using the DTO identifier (what migration command would do)
        // This works correctly because the DTO contains 'MIGRATION_TENANT' (the tenant_code)
        $foundTenant = $manager->getTenantDatabaseById('MIGRATION_TENANT');

        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $foundTenant);
        $this->assertEquals('migration_test_db', $foundTenant->dbname);
    }

    /**
     * Test what SHOULD happen after the fix is implemented
     * 
     * @group skip-until-fixed
     */
    public function testExpectedBehaviorAfterFix(): void
    {
        $this->markTestSkipped('This test will pass once Issue #64 is fixed');
        
        // After fix, the DTO should contain the configured identifier field value:
        // $this->assertEquals('CUSTOM_TENANT_123', $dto->identifier);
        // And migration commands should work correctly with custom identifiers
    }

    /**
     * Test that the MigrateCommand now respects the dbId argument
     * This validates the fix for the second part of the issue
     */
    public function testMigrateCommandRespectsDbIdArgument(): void
    {
        $container = $this->kernel->getContainer();
        
        // Create test tenant with custom identifier
        $testTenant = new TestTenantDbEntity();
        $testTenant->setId(777);
        $testTenant->setTenantCode('SPECIFIC_TENANT');
        $testTenant->setDriverType(DriverTypeEnum::MYSQL);
        $testTenant->setDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
        $testTenant->setDbHost('localhost');
        $testTenant->setDbPort(3306);
        $testTenant->setDbName('specific_tenant_db');
        $testTenant->setDbUserName('test_user');
        $testTenant->setDbPassword('test_pass');

        // Mock repository and entity manager
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        
        $entityManager->method('getRepository')->willReturn($repository);
        
        // When looking up by configured identifier field, return our tenant
        $repository->method('findOneBy')
            ->with(['tenant_code' => 'SPECIFIC_TENANT'])
            ->willReturn($testTenant);

        $connectionGenerator = $this->createMock(\Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface::class);

        $manager = new \Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager(
            $entityManager,
            $connectionGenerator,
            'Hakam\\MultiTenancyBundle\\Tests\\Integration\\TestTenantDbEntity',
            'tenant_code'
        );

        // Test that getTenantDatabaseById works with the configured identifier
        $foundTenant = $manager->getTenantDatabaseById('SPECIFIC_TENANT');
        
        $this->assertInstanceOf(\Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO::class, $foundTenant);

        // ISSUE #64 FIXED: identifier now contains the configured field value
        $this->assertEquals('SPECIFIC_TENANT', $foundTenant->identifier,
            'DTO should contain configured identifier field value, not entity ID');

        $this->assertEquals('SPECIFIC_TENANT', $testTenant->getTenantCode());
        $this->assertEquals('specific_tenant_db', $foundTenant->dbname);
    }
}

/**
 * Test entity that implements the TenantDbConfigurationInterface with a custom identifier field
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_tenant_db')]
class TestTenantDbEntity implements TenantDbConfigurationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $tenant_code;

    #[ORM\Column(type: 'string', enumType: DriverTypeEnum::class)]
    private DriverTypeEnum $driverType;

    #[ORM\Column(type: 'string', enumType: DatabaseStatusEnum::class)]
    private DatabaseStatusEnum $databaseStatus;

    #[ORM\Column(type: 'string', length: 255)]
    private string $dbHost;

    #[ORM\Column(type: 'integer')]
    private int $dbPort;

    #[ORM\Column(type: 'string', length: 100)]
    private string $dbName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $dbUserName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $dbPassword = null;

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTenantCode(): string
    {
        return $this->tenant_code;
    }

    public function setTenantCode(string $tenant_code): self
    {
        $this->tenant_code = $tenant_code;
        return $this;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): self
    {
        $this->dbName = $dbName;
        return $this;
    }

    public function getDbUsername(): ?string
    {
        return $this->dbUserName;
    }

    public function setDbUserName(string $dbUserName): self
    {
        $this->dbUserName = $dbUserName;
        return $this;
    }

    public function getDbPassword(): ?string
    {
        return $this->dbPassword;
    }

    public function setDbPassword(?string $dbPassword): self
    {
        $this->dbPassword = $dbPassword;
        return $this;
    }

    public function getDbHost(): ?string
    {
        return $this->dbHost;
    }

    public function setDbHost(string $dbHost): self
    {
        $this->dbHost = $dbHost;
        return $this;
    }

    public function getDbPort(): ?int
    {
        return $this->dbPort;
    }

    public function setDbPort(int $dbPort): self
    {
        $this->dbPort = $dbPort;
        return $this;
    }

    public function getDatabaseStatus(): DatabaseStatusEnum
    {
        return $this->databaseStatus;
    }

    public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): self
    {
        $this->databaseStatus = $databaseStatus;
        return $this;
    }

    public function getDsnUrl(): string
    {
        return sprintf('%s://%s:%s@%s:%d/%s',
            $this->driverType->value,
            $this->dbUserName,
            $this->dbPassword,
            $this->dbHost,
            $this->dbPort,
            $this->dbName
        );
    }

    public function getDriverType(): DriverTypeEnum
    {
        return $this->driverType;
    }

    public function setDriverType(DriverTypeEnum $driverType): self
    {
        $this->driverType = $driverType;
        return $this;
    }
    public function getIdentifierValue(): mixed
    {
        // Return the tenant_code since that's our configured identifier in the test
        return $this->tenant_code;
    }
}