<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConnectionSwitchingTest extends RealDatabaseTestCase
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

    public function testSwitchToTenantDatabase(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);

        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $switchedEvents = [];
        $dispatcher->addListener(TenantSwitchedEvent::class, function (TenantSwitchedEvent $e) use (&$switchedEvents) {
            $switchedEvents[] = $e;
        });

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        $this->assertCount(1, $switchedEvents, 'TenantSwitchedEvent should fire');

        // Verify connection is live by running a query
        $tenantEm = $this->getTenantEntityManager();
        $result = $tenantEm->getConnection()->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
    }

    public function testSwitchBetweenTwoTenantsWithDataIsolation(): void
    {
        $dbNameA = $this->generateUniqueDatabaseName();
        $dbNameB = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbNameA);
        $this->createRealTenantDatabase($dbNameB);

        $tenantA = $this->insertTenantConfig($dbNameA, DatabaseStatusEnum::DATABASE_CREATED);
        $tenantB = $this->insertTenantConfig($dbNameB, DatabaseStatusEnum::DATABASE_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // Switch to tenant A, create schema, insert data
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();
        $productA = new TenantProduct();
        $productA->setName('Product A');
        $productA->setPrice('10.00');
        $tenantEm->persist($productA);
        $tenantEm->flush();

        // Switch to tenant B, create schema, insert different data
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $this->createTenantSchema();

        $productB = new TenantProduct();
        $productB->setName('Product B');
        $productB->setPrice('20.00');
        $tenantEm->persist($productB);
        $tenantEm->flush();

        // Verify tenant B only has Product B
        $foundInB = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $foundInB);
        $this->assertSame('Product B', $foundInB[0]->getName());

        // Switch back to tenant A, verify isolation — only Product A exists
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $tenantEm->clear();

        $foundInA = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $foundInA);
        $this->assertSame('Product A', $foundInA[0]->getName());
    }

    public function testCrudAfterSwitch(): void
    {
        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);

        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();

        // Create
        $product = new TenantProduct();
        $product->setName('CRUD Product');
        $product->setPrice('42.50');
        $tenantEm->persist($product);
        $tenantEm->flush();

        $id = $product->getId();
        $this->assertNotNull($id);

        // Read
        $tenantEm->clear();
        $found = $tenantEm->find(TenantProduct::class, $id);
        $this->assertNotNull($found);
        $this->assertSame('CRUD Product', $found->getName());
        $this->assertEquals(42.50, (float) $found->getPrice());

        // Update
        $found->setName('Updated CRUD Product');
        $found->setPrice('99.99');
        $tenantEm->flush();
        $tenantEm->clear();

        $updated = $tenantEm->find(TenantProduct::class, $id);
        $this->assertSame('Updated CRUD Product', $updated->getName());
        $this->assertEquals(99.99, (float) $updated->getPrice());

        // Delete
        $tenantEm->remove($updated);
        $tenantEm->flush();
        $tenantEm->clear();

        $deleted = $tenantEm->find(TenantProduct::class, $id);
        $this->assertNull($deleted);
    }

    public function testSwitchClearsEntityManagerIdentityMap(): void
    {
        $dbNameA = $this->generateUniqueDatabaseName();
        $dbNameB = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbNameA);
        $this->createRealTenantDatabase($dbNameB);

        $tenantA = $this->insertTenantConfig($dbNameA, DatabaseStatusEnum::DATABASE_CREATED);
        $tenantB = $this->insertTenantConfig($dbNameB, DatabaseStatusEnum::DATABASE_CREATED);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // Switch to A, create schema, persist entity
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Identity Map Test');
        $product->setPrice('5.00');
        $tenantEm->persist($product);
        $tenantEm->flush();

        $this->assertTrue($tenantEm->contains($product), 'EM should contain the entity before switch');

        // Switch to B — EM identity map should be cleared
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $this->assertFalse($tenantEm->contains($product), 'EM identity map should be cleared after switch');
    }
}
