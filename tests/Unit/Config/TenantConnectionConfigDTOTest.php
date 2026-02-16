<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Config;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use PHPUnit\Framework\TestCase;

class TenantConnectionConfigDTOTest extends TestCase
{
    public function testFromArgsCreatesCorrectInstance(): void
    {
        $identifier = 'tenant_123';
        $driver = DriverTypeEnum::MYSQL;
        $dbStatus = DatabaseStatusEnum::DATABASE_CREATED;
        $host = 'localhost';
        $port = 3306;
        $dbname = 'tenant_db';
        $user = 'root';
        $password = 'secret';

        $dto = TenantConnectionConfigDTO::fromArgs(
            $identifier,
            $driver,
            $dbStatus,
            $host,
            $port,
            $dbname,
            $user,
            $password
        );

        $this->assertSame($identifier, $dto->identifier);
        $this->assertSame($driver, $dto->driver);
        $this->assertSame($dbStatus, $dto->dbStatus);
        $this->assertSame($host, $dto->host);
        $this->assertSame($port, $dto->port);
        $this->assertSame($dbname, $dto->dbname);
        $this->assertSame($user, $dto->user);
        $this->assertSame($password, $dto->password);
    }

    public function testFromArgsWithoutPassword(): void
    {
        $identifier = 42;
        $driver = DriverTypeEnum::POSTGRES;
        $dbStatus = DatabaseStatusEnum::DATABASE_MIGRATED;
        $host = '127.0.0.1';
        $port = 5432;
        $dbname = 'postgres_db';
        $user = 'postgres';

        $dto = TenantConnectionConfigDTO::fromArgs(
            $identifier,
            $driver,
            $dbStatus,
            $host,
            $port,
            $dbname,
            $user
        );

        $this->assertSame($identifier, $dto->identifier);
        $this->assertNull($dto->password);
    }

    public function testWithIdCreatesNewInstanceWithUpdatedId(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            'original_id',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'test_db',
            'user',
            'pass'
        );

        $newId = 999;
        $newDto = $dto->withId($newId);

        $this->assertNotSame($dto, $newDto);
        $this->assertSame($newId, $newDto->identifier);
        $this->assertSame('original_id', $dto->identifier);
        
        // Verify other properties remain the same
        $this->assertSame($dto->driver, $newDto->driver);
        $this->assertSame($dto->dbStatus, $newDto->dbStatus);
        $this->assertSame($dto->host, $newDto->host);
        $this->assertSame($dto->port, $newDto->port);
        $this->assertSame($dto->dbname, $newDto->dbname);
        $this->assertSame($dto->user, $newDto->user);
        $this->assertSame($dto->password, $newDto->password);
    }

    public function testWithIdPreservesAllOtherProperties(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            123,
            DriverTypeEnum::SQLITE,
            DatabaseStatusEnum::DATABASE_NOT_CREATED,
            'localhost',
            3306,
            'sqlite_db',
            'admin',
            null
        );

        $newDto = $dto->withId(456);

        $this->assertSame(456, $newDto->identifier);
        $this->assertSame(DriverTypeEnum::SQLITE, $newDto->driver);
        $this->assertSame(DatabaseStatusEnum::DATABASE_NOT_CREATED, $newDto->dbStatus);
        $this->assertNull($newDto->password);
    }

    public function testIdentifierCanBeNumeric(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            123,
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'db',
            'user'
        );

        $this->assertSame(123, $dto->identifier);
    }

    public function testIdentifierCanBeString(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            'tenant_code_abc',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'db',
            'user'
        );

        $this->assertSame('tenant_code_abc', $dto->identifier);
    }

    public function testDifferentDriverTypes(): void
    {
        $mysqlDto = TenantConnectionConfigDTO::fromArgs(
            1, DriverTypeEnum::MYSQL, DatabaseStatusEnum::DATABASE_CREATED,
            'localhost', 3306, 'mysql_db', 'root'
        );

        $postgresDto = TenantConnectionConfigDTO::fromArgs(
            2, DriverTypeEnum::POSTGRES, DatabaseStatusEnum::DATABASE_CREATED,
            'localhost', 5432, 'postgres_db', 'postgres'
        );

        $sqliteDto = TenantConnectionConfigDTO::fromArgs(
            3, DriverTypeEnum::SQLITE, DatabaseStatusEnum::DATABASE_CREATED,
            'localhost', 0, 'sqlite_db', 'user'
        );

        $this->assertSame(DriverTypeEnum::MYSQL, $mysqlDto->driver);
        $this->assertSame(DriverTypeEnum::POSTGRES, $postgresDto->driver);
        $this->assertSame(DriverTypeEnum::SQLITE, $sqliteDto->driver);
    }

    public function testDifferentDatabaseStatuses(): void
    {
        $notCreated = TenantConnectionConfigDTO::fromArgs(
            1, DriverTypeEnum::MYSQL, DatabaseStatusEnum::DATABASE_NOT_CREATED,
            'localhost', 3306, 'db1', 'user'
        );

        $created = TenantConnectionConfigDTO::fromArgs(
            2, DriverTypeEnum::MYSQL, DatabaseStatusEnum::DATABASE_CREATED,
            'localhost', 3306, 'db2', 'user'
        );

        $migrated = TenantConnectionConfigDTO::fromArgs(
            3, DriverTypeEnum::MYSQL, DatabaseStatusEnum::DATABASE_MIGRATED,
            'localhost', 3306, 'db3', 'user'
        );

        $this->assertSame(DatabaseStatusEnum::DATABASE_NOT_CREATED, $notCreated->dbStatus);
        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $created->dbStatus);
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $migrated->dbStatus);
    }

    public function testDtoIsImmutable(): void
    {
        $dto = TenantConnectionConfigDTO::fromArgs(
            100,
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'test_db',
            'user',
            'pass'
        );

        $newDto = $dto->withId(200);

        $this->assertSame(100, $dto->identifier);
        $this->assertSame(200, $newDto->identifier);
        $this->assertNotSame($dto, $newDto);
    }
}
