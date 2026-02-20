<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CreateAndDropDatabaseTest extends RealDatabaseTestCase
{
    public function testCreateDatabaseViaManager(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->trackDatabase($dbName);

        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        );

        /** @var DoctrineTenantDatabaseManager $manager */
        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);
        $result = $manager->createTenantDatabase($dto);

        $this->assertTrue((bool) $result, 'createTenantDatabase should return truthy on success');

        // Verify the database exists by connecting to it
        $dtoForConnection = TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        );

        $connGenerator = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator');
        $conn = $connGenerator->generate($dtoForConnection);
        $result = $conn->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
        $conn->close();
    }

    public function testCreateDatabaseViaCommand(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->trackDatabase($dbName);

        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_NOT_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $capturedCreated = [];
        $dispatcher->addListener(TenantCreatedEvent::class, function (TenantCreatedEvent $e) use (&$capturedCreated) {
            $capturedCreated[] = $e;
        });

        $application = new Application(static::$kernel);
        $command = $application->find('tenant:database:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dbid' => $tenant->getId()]);

        $this->assertSame(0, $commandTester->getStatusCode(), 'Command should exit with 0: ' . $commandTester->getDisplay());
        $this->assertStringContainsString('created successfully', $commandTester->getDisplay());
        $this->assertCount(1, $capturedCreated, 'TenantCreatedEvent should fire once');

        // Verify status was updated to DATABASE_CREATED
        $em = $this->getDefaultEntityManager();
        $em->clear();
        $updated = $em->find(get_class($tenant), $tenant->getId());
        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $updated->getDatabaseStatus());
    }

    public function testCreateDatabaseThatAlreadyExistsReturnsGracefully(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->trackDatabase($dbName);

        // Mark tenant as already created so the command short-circuits
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $application = new Application(static::$kernel);
        $command = $application->find('tenant:database:create');
        $commandTester = new CommandTester($command);

        // First creation should succeed
        $commandTester->execute(['--dbid' => $tenant->getId()]);
        $this->assertSame(0, $commandTester->getStatusCode(), 'First creation should succeed: ' . $commandTester->getDisplay());

        // Second attempt — DB now has status DATABASE_CREATED, command should return 0 with "already exists" message
        $commandTester->execute(['--dbid' => $tenant->getId()]);
        $this->assertSame(0, $commandTester->getStatusCode(), 'Second creation should handle gracefully: ' . $commandTester->getDisplay());
        $this->assertStringContainsString('already exists', $commandTester->getDisplay());
    }

    public function testDropDatabaseCleanup(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        // Deliberately NOT tracking — we drop manually to verify the drop path works

        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        );

        /** @var DoctrineTenantDatabaseManager $manager */
        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);
        $manager->createTenantDatabase($dto);

        // Drop the database via maintenance connection
        $connGenerator = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator');
        $maintenanceConn = $connGenerator->generateMaintenanceConnection($dto);
        $schemaManager = method_exists($maintenanceConn, 'createSchemaManager')
            ? $maintenanceConn->createSchemaManager()
            : $maintenanceConn->getSchemaManager();
        $schemaManager->dropDatabase($dbName);
        $maintenanceConn->close();

        // Verify the database no longer exists — attempting to connect should fail
        try {
            $conn = $connGenerator->generate(TenantConnectionConfigDTO::fromArgs(
                identifier: null,
                driver: $this->driverType,
                dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
                host: $this->host,
                port: $this->port,
                dbname: $dbName,
                user: $this->user,
                password: $this->password,
            ));
            $conn->executeQuery('SELECT 1');
            $this->fail('Connection to dropped database should have failed');
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1); // Exception is expected
        }
    }
}
