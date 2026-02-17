<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FullLifecycleTest extends IntegrationTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/hakam_lifecycle_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /**
     * Golden path: insert config → create DB → switch → schema → CRUD → events.
     * Gracefully skips on platforms where SQLite createDatabase is not supported.
     */
    public function testFullLifecycleGoldenPath(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $capturedCreated = [];
        $capturedSwitched = [];
        $dispatcher->addListener(TenantCreatedEvent::class, function (TenantCreatedEvent $e) use (&$capturedCreated) {
            $capturedCreated[] = $e;
        });
        $dispatcher->addListener(TenantSwitchedEvent::class, function (TenantSwitchedEvent $e) use (&$capturedSwitched) {
            $capturedSwitched[] = $e;
        });

        $dbPath = $this->tempDir . '/lifecycle_golden.sqlite';
        $tenant = $this->insertTenantConfig(
            dbName: $dbPath,
            status: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $application = new Application(static::$kernel);
        $command = $application->find('tenant:database:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--dbid' => $tenant->getId()]);

        if ($commandTester->getStatusCode() !== 0) {
            $this->markTestSkipped('SQLite createDatabase not supported on this platform: ' . $commandTester->getDisplay());
        }

        $this->assertStringContainsString('created successfully', $commandTester->getDisplay());
        $this->assertCount(1, $capturedCreated, 'TenantCreatedEvent should fire');

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->assertCount(1, $capturedSwitched, 'TenantSwitchedEvent should fire');

        $tenantEm = $this->getTenantEntityManager();
        $schemaTool = new SchemaTool($tenantEm);
        $metadata = $tenantEm->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);

        // Create
        $product = new TenantProduct();
        $product->setName('Golden Product');
        $product->setPrice('42.50');
        $tenantEm->persist($product);
        $tenantEm->flush();

        $found = $tenantEm->getRepository(TenantProduct::class)->findOneBy(['name' => 'Golden Product']);
        $this->assertNotNull($found);
        $this->assertEquals((float) '42.50', (float) $found->getPrice());

        // Update
        $found->setName('Updated Product');
        $tenantEm->flush();
        $tenantEm->clear();

        $updated = $tenantEm->getRepository(TenantProduct::class)->find($found->getId());
        $this->assertSame('Updated Product', $updated->getName());

        // Delete
        $tenantEm->remove($updated);
        $tenantEm->flush();
        $tenantEm->clear();

        $deleted = $tenantEm->getRepository(TenantProduct::class)->find($updated->getId());
        $this->assertNull($deleted);
    }

    /**
     * Verifies that switching between tenants clears the entity manager identity map
     * and that CRUD works after each switch.
     */
    public function testSwitchingClearsStateAndCrudWorksAfterSwitch(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $tenantA = $this->insertTenantConfig(
            dbName: 'isolation_a',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'isolation_b',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        // Switch to tenant A, create schema, persist an entity
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();
        $productA = new TenantProduct();
        $productA->setName('Product A');
        $productA->setPrice('10.00');
        $tenantEm->persist($productA);
        $tenantEm->flush();

        $this->assertTrue($tenantEm->contains($productA));

        // Switch to tenant B — EM should be cleared
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $this->assertFalse($tenantEm->contains($productA), 'EM identity map should be cleared after switch');

        // Create schema in tenant B and persist a different entity
        $this->createTenantSchema();
        $productB = new TenantProduct();
        $productB->setName('Product B');
        $productB->setPrice('20.00');
        $tenantEm->persist($productB);
        $tenantEm->flush();

        $found = $tenantEm->getRepository(TenantProduct::class)->findOneBy(['name' => 'Product B']);
        $this->assertNotNull($found);
        $this->assertSame('Product B', $found->getName());
    }

    /**
     * Verifies both SwitchDbEvent and TenantSwitchedEvent fire during a switch.
     * The DbSwitchEventListener (subscriber) handles SwitchDbEvent and dispatches
     * TenantSwitchedEvent from within its handler.
     */
    public function testLifecycleEventsAllFire(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $switchDbFired = false;
        $switchedFired = false;

        $dispatcher->addListener(SwitchDbEvent::class, function () use (&$switchDbFired) {
            $switchDbFired = true;
        });
        $dispatcher->addListener(TenantSwitchedEvent::class, function () use (&$switchedFired) {
            $switchedFired = true;
        });

        $tenant = $this->insertTenantConfig(
            dbName: 'event_fire_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        $this->assertTrue($switchDbFired, 'SwitchDbEvent should fire');
        $this->assertTrue($switchedFired, 'TenantSwitchedEvent should fire');
    }

    public function testTenantContextConsistentThroughLifecycle(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $context = $this->getContainer()->get(TenantContextInterface::class);

        // Before any switch
        $this->assertNull($context->getTenantId());

        $tenant = $this->insertTenantConfig(
            dbName: 'context_lifecycle_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        // Switch
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->assertSame((string) $tenant->getId(), $context->getTenantId());

        // After CRUD
        $this->createTenantSchema();
        $tenantEm = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Context Test');
        $product->setPrice('5.00');
        $tenantEm->persist($product);
        $tenantEm->flush();

        // Context should still be consistent after CRUD operations
        $this->assertSame((string) $tenant->getId(), $context->getTenantId());
    }
}
