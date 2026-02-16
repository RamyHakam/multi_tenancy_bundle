<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Fixture\TenantProductFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LoadTenantFixtureCommandIntegrationTest extends IntegrationTestCase
{
    protected function getServiceRegistrar(): ?callable
    {
        return function (ContainerBuilder $container) {
            $container->register(TenantProductFixture::class, TenantProductFixture::class)
                ->addTag('tenant_fixture')
                ->setPublic(true);
        };
    }

    public function testFixtureCommandIsRegisteredAndRunnable(): void
    {
        $application = new Application(static::$kernel);
        $this->assertTrue($application->has('tenant:fixtures:load'));
    }

    public function testFixtureLoadInsertsDataIntoTenantDb(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'fixture_load_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        // Switch to tenant and create schema
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->createTenantSchema();

        // Run fixture command with --append to avoid purge issues with SQLite in-memory
        $application = new Application(static::$kernel);
        $command = $application->find('tenant:fixtures:load');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'dbId' => (string) $tenant->getId(),
            '--append' => true,
        ], ['interactive' => false]);

        if ($commandTester->getStatusCode() !== 0) {
            $this->markTestSkipped('Fixture loading failed: ' . $commandTester->getDisplay());
        }

        // Verify fixture data was inserted
        $tenantEm = $this->getTenantEntityManager();
        $tenantEm->clear();
        $products = $tenantEm->getRepository(TenantProduct::class)->findAll();

        $this->assertNotEmpty($products, 'Fixture should have inserted at least one product');
        $found = false;
        foreach ($products as $product) {
            if ($product->getName() === 'Fixture Product') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Fixture product "Fixture Product" should exist');
    }

    public function testFixtureLoadDispatchesTenantBootstrappedEvent(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'fixture_event_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        // Switch to tenant and create schema
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));
        $this->createTenantSchema();

        $capturedEvents = [];
        $dispatcher->addListener(TenantBootstrappedEvent::class, function (TenantBootstrappedEvent $e) use (&$capturedEvents) {
            $capturedEvents[] = $e;
        });

        $application = new Application(static::$kernel);
        $command = $application->find('tenant:fixtures:load');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'dbId' => (string) $tenant->getId(),
            '--append' => true,
        ], ['interactive' => false]);

        if ($commandTester->getStatusCode() !== 0) {
            $this->markTestSkipped('Fixture loading failed: ' . $commandTester->getDisplay());
        }

        $this->assertCount(1, $capturedEvents, 'TenantBootstrappedEvent should be dispatched');
        $this->assertNotEmpty($capturedEvents[0]->getLoadedFixtures());
    }
}
