<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;

class DsnGenerationTest extends RealDatabaseTestCase
{
    public function testGeneratedDsnConnects(): void
    {
        $dbName = $this->generateUniqueDatabaseName();

        // First create the database so we can connect to it
        $dto = $this->buildDto($dbName, DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);
        $manager->createTenantDatabase($dto);
        $this->trackDatabase($dbName);

        // Now generate a connection via the DSN pipeline and verify it works
        $connGenerator = $this->getContainer()->get(TenantDBALConnectionGenerator::class);
        $dtoForConn = $this->buildDto($dbName, DatabaseStatusEnum::DATABASE_CREATED);
        $conn = $connGenerator->generate($dtoForConn);

        $result = $conn->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
        $conn->close();
    }

    public function testMaintenanceDsnConnects(): void
    {
        $dto = $this->buildDto('dummy_unused_db', DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $connGenerator = $this->getContainer()->get(TenantDBALConnectionGenerator::class);
        $maintenanceConn = $connGenerator->generateMaintenanceConnection($dto);

        $result = $maintenanceConn->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
        $maintenanceConn->close();
    }

    public function testMaintenanceDsnCanCreateDatabase(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $dto = $this->buildDto($dbName, DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $connGenerator = $this->getContainer()->get(TenantDBALConnectionGenerator::class);
        $maintenanceConn = $connGenerator->generateMaintenanceConnection($dto);

        $schemaManager = method_exists($maintenanceConn, 'createSchemaManager')
            ? $maintenanceConn->createSchemaManager()
            : $maintenanceConn->getSchemaManager();

        $schemaManager->createDatabase($dbName);
        $this->trackDatabase($dbName);
        $maintenanceConn->close();

        // Verify the database was created by connecting to it
        $connToNew = $connGenerator->generate($this->buildDto($dbName, DatabaseStatusEnum::DATABASE_CREATED));
        $result = $connToNew->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
        $connToNew->close();
    }

    private function buildDto(string $dbName, DatabaseStatusEnum $status): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $this->driverType,
            dbStatus: $status,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        );
    }
}
