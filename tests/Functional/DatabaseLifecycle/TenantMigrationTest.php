<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantMigratedEvent;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TenantMigrationTest extends RealDatabaseTestCase
{
    private function createRealTenantDatabase(string $dbName): void
    {
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

        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);
        $manager->createTenantDatabase($dto);
        $this->trackDatabase($dbName);
    }

    private function runMigrateCommand(string $type, ?string $dbId = null): CommandTester
    {
        $application = new Application(static::$kernel);
        $command = $application->find('tenant:migrations:migrate');
        $commandTester = new CommandTester($command);

        $args = ['type' => $type];
        if ($dbId !== null) {
            $args['dbId'] = $dbId;
        }
        $args['--allow-no-migration'] = true;

        $commandTester->execute($args, ['interactive' => false]);

        return $commandTester;
    }

    private function assertTenantTableExists(string $tenantId): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent($tenantId));

        $tenantEm = $this->getTenantEntityManager();
        $result = $tenantEm->getConnection()->executeQuery('SELECT 1 FROM tenant_product LIMIT 0');
        $this->assertNotNull($result, 'tenant_product table should exist after migration');
    }

    public function testInitMigrationCreatesSchema(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_CREATED);
        $tenantId = (string) $tenant->getId();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $capturedEvents = [];
        $dispatcher->addListener(TenantMigratedEvent::class, function (TenantMigratedEvent $e) use (&$capturedEvents) {
            $capturedEvents[] = $e;
        });

        $commandTester = $this->runMigrateCommand('init', $tenantId);

        $this->assertSame(0, $commandTester->getStatusCode(), 'Init migration should succeed: ' . $commandTester->getDisplay());

        // Verify table was created by migration
        $this->assertTenantTableExists($tenantId);

        // Verify status updated to DATABASE_MIGRATED
        $em = $this->getDefaultEntityManager();
        $em->clear();
        $updated = $em->find(get_class($tenant), $tenant->getId());
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $updated->getDatabaseStatus());

        // Verify TenantMigratedEvent fired with type init
        $this->assertCount(1, $capturedEvents, 'TenantMigratedEvent should fire once');
        $this->assertSame(TenantMigratedEvent::TYPE_INIT, $capturedEvents[0]->getMigrationType());
    }

    public function testBatchInitMigration(): void
    {
        $dbNameA = $this->generateUniqueDatabaseName();
        $dbNameB = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbNameA);
        $this->createRealTenantDatabase($dbNameB);

        $tenantA = $this->insertTenantConfig($dbNameA, DatabaseStatusEnum::DATABASE_CREATED);
        $tenantB = $this->insertTenantConfig($dbNameB, DatabaseStatusEnum::DATABASE_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $capturedEvents = [];
        $dispatcher->addListener(TenantMigratedEvent::class, function (TenantMigratedEvent $e) use (&$capturedEvents) {
            $capturedEvents[] = $e;
        });

        $commandTester = $this->runMigrateCommand('init');

        $this->assertSame(0, $commandTester->getStatusCode(), 'Batch init migration should succeed: ' . $commandTester->getDisplay());

        // Verify both DBs have the schema
        $this->assertTenantTableExists((string) $tenantA->getId());
        $this->assertTenantTableExists((string) $tenantB->getId());

        // Verify both statuses updated
        $em = $this->getDefaultEntityManager();
        $em->clear();
        $updatedA = $em->find(get_class($tenantA), $tenantA->getId());
        $updatedB = $em->find(get_class($tenantB), $tenantB->getId());
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $updatedA->getDatabaseStatus());
        $this->assertSame(DatabaseStatusEnum::DATABASE_MIGRATED, $updatedB->getDatabaseStatus());

        // Verify 2 TenantMigratedEvent fired
        $this->assertCount(2, $capturedEvents);
    }

    public function testUpdateMigrationOnAlreadyMigratedDb(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_CREATED);
        $tenantId = (string) $tenant->getId();

        // First: init migration
        $commandTester = $this->runMigrateCommand('init', $tenantId);
        $this->assertSame(0, $commandTester->getStatusCode(), 'Init should succeed: ' . $commandTester->getDisplay());

        // Now run update migration on already-migrated DB
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $capturedEvents = [];
        $dispatcher->addListener(TenantMigratedEvent::class, function (TenantMigratedEvent $e) use (&$capturedEvents) {
            $capturedEvents[] = $e;
        });

        $commandTester = $this->runMigrateCommand('update', $tenantId);

        $this->assertSame(0, $commandTester->getStatusCode(), 'Update migration should succeed: ' . $commandTester->getDisplay());

        // Verify TenantMigratedEvent fired with type update
        $this->assertCount(1, $capturedEvents, 'TenantMigratedEvent should fire for update');
        $this->assertSame(TenantMigratedEvent::TYPE_UPDATE, $capturedEvents[0]->getMigrationType());
    }

    public function testInitMigrationRejectsWrongStatus(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);

        // Insert with DATABASE_MIGRATED status — init requires DATABASE_CREATED
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantId = (string) $tenant->getId();

        $commandTester = $this->runMigrateCommand('init', $tenantId);

        $this->assertSame(1, $commandTester->getStatusCode(), 'Init should reject wrong status');
        $this->assertStringContainsString('not in CREATED status', $commandTester->getDisplay());
    }
}
