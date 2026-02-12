<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

class TenantDatabaseManagerTest extends IntegrationTestCase
{
    private DoctrineTenantDatabaseManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager');
    }

    public function testListDatabasesReturnsMigratedDatabases(): void
    {
        $this->insertTenantConfig('db_not_created', DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $this->insertTenantConfig('db_created', DatabaseStatusEnum::DATABASE_CREATED);
        $this->insertTenantConfig('db_migrated_1', DatabaseStatusEnum::DATABASE_MIGRATED);
        $this->insertTenantConfig('db_migrated_2', DatabaseStatusEnum::DATABASE_MIGRATED);

        $result = $this->manager->listDatabases();
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(TenantConnectionConfigDTO::class, $result);

        $names = array_map(fn($dto) => $dto->dbname, $result);
        $this->assertContains('db_migrated_1', $names);
        $this->assertContains('db_migrated_2', $names);
    }

    public function testListDatabasesThrowsWhenNoneMigrated(): void
    {
        $this->insertTenantConfig('only_created', DatabaseStatusEnum::DATABASE_CREATED);

        $this->expectException(\RuntimeException::class);
        $this->manager->listDatabases();
    }

    public function testListMissingDatabasesReturnsNotCreated(): void
    {
        $this->insertTenantConfig('db_missing_1', DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $this->insertTenantConfig('db_missing_2', DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $this->insertTenantConfig('db_migrated', DatabaseStatusEnum::DATABASE_MIGRATED);

        $result = $this->manager->listMissingDatabases();
        $this->assertCount(2, $result);

        $names = array_map(fn($dto) => $dto->dbname, $result);
        $this->assertContains('db_missing_1', $names);
        $this->assertContains('db_missing_2', $names);
    }

    public function testListMissingDatabasesThrowsWhenAllCreated(): void
    {
        $this->insertTenantConfig('already_created', DatabaseStatusEnum::DATABASE_CREATED);

        $this->expectException(\RuntimeException::class);
        $this->manager->listMissingDatabases();
    }

    public function testGetTenantDbListByDatabaseStatus(): void
    {
        $this->insertTenantConfig('created_1', DatabaseStatusEnum::DATABASE_CREATED);
        $this->insertTenantConfig('created_2', DatabaseStatusEnum::DATABASE_CREATED);
        $this->insertTenantConfig('migrated_1', DatabaseStatusEnum::DATABASE_MIGRATED);

        $created = $this->manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
        $this->assertCount(2, $created);

        $migrated = $this->manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_MIGRATED);
        $this->assertCount(1, $migrated);
    }

    public function testGetTenantDbListByDatabaseStatusThrowsWhenEmpty(): void
    {
        $this->insertTenantConfig('some_db', DatabaseStatusEnum::DATABASE_CREATED);

        $this->expectException(\RuntimeException::class);
        $this->manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_NOT_CREATED);
    }

    public function testGetTenantDatabaseById(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'find_by_id_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
            host: 'myhost',
            port: 3306,
            user: 'myuser',
            password: 'mypass',
        );

        $dto = $this->manager->getTenantDatabaseById($tenant->getId());

        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $dto);
        $this->assertSame($tenant->getId(), $dto->identifier);
        $this->assertSame('find_by_id_db', $dto->dbname);
        $this->assertSame(DriverTypeEnum::SQLITE, $dto->driver);
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $dto->dbStatus);
        $this->assertSame('myhost', $dto->host);
        $this->assertSame(3306, $dto->port);
        $this->assertSame('myuser', $dto->user);
        $this->assertSame('mypass', $dto->password);
    }

    public function testGetTenantDatabaseByIdThrowsForNonexistent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manager->getTenantDatabaseById(999999);
    }

    public function testGetDefaultTenantDatabase(): void
    {
        $this->insertTenantConfig('not_created_db', DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $tenant = $this->insertTenantConfig('created_db', DatabaseStatusEnum::DATABASE_CREATED);

        $dto = $this->manager->getDefaultTenantIDatabase();

        $this->assertSame('created_db', $dto->dbname);
        $this->assertSame($tenant->getId(), $dto->identifier);
    }

    public function testGetDefaultTenantDatabaseThrowsWhenNoneCreated(): void
    {
        $this->insertTenantConfig('migrated_only', DatabaseStatusEnum::DATABASE_MIGRATED);

        $this->expectException(\RuntimeException::class);
        $this->manager->getDefaultTenantIDatabase();
    }

    public function testUpdateTenantDatabaseStatus(): void
    {
        $tenant = $this->insertTenantConfig('status_update_db', DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $this->manager->updateTenantDatabaseStatus($tenant->getId(), DatabaseStatusEnum::DATABASE_CREATED);

        // Re-fetch from DB to verify
        $this->getDefaultEntityManager()->clear();
        $updated = $this->getDefaultEntityManager()
            ->getRepository(Fixtures\Entity\TenantDbConfig::class)
            ->find($tenant->getId());

        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $updated->getDatabaseStatus());
    }

    public function testUpdateTenantDatabaseStatusThrowsForNonexistent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manager->updateTenantDatabaseStatus(999999, DatabaseStatusEnum::DATABASE_CREATED);
    }

    public function testAddNewTenantDbConfig(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: DriverTypeEnum::SQLITE,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: 'newhost',
            port: 5432,
            dbname: 'new_tenant_db',
            user: 'newuser',
            password: 'newpass',
        );

        $result = $this->manager->addNewTenantDbConfig($dto);

        $this->assertInstanceOf(TenantConnectionConfigDTO::class, $result);
        $this->assertSame('new_tenant_db', $result->dbname);
        $this->assertSame(DriverTypeEnum::SQLITE, $result->driver);
        $this->assertSame(DatabaseStatusEnum::DATABASE_NOT_CREATED, $result->dbStatus);
        $this->assertNotNull($result->identifier);
    }

    public function testConvertToDtoPreservesAllFields(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'full_fields_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::MYSQL,
            host: 'prod-host',
            port: 3307,
            user: 'admin',
            password: 's3cret',
        );

        $dto = $this->manager->getTenantDatabaseById($tenant->getId());

        $this->assertSame($tenant->getId(), $dto->identifier);
        $this->assertSame('full_fields_db', $dto->dbname);
        $this->assertSame(DriverTypeEnum::MYSQL, $dto->driver);
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $dto->dbStatus);
        $this->assertSame('prod-host', $dto->host);
        $this->assertSame(3307, $dto->port);
        $this->assertSame('admin', $dto->user);
        $this->assertSame('s3cret', $dto->password);
    }
}
