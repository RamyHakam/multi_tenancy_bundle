<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service\InMemoryTenantConfigProvider;
use Hakam\MultiTenancyBundle\Tests\Integration\Kernel\IntegrationTestKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

class ConfigManagerOverrideTest extends RealDatabaseTestCase
{
    private bool $useCustomProvider = false;

    protected function bootKernel(): void
    {
        $tenantBootDbName = $this->driver === 'pdo_pgsql' ? 'postgres' : '';
        $pass = $this->password !== '' ? ':' . $this->password : '';
        $scheme = $this->driver === 'pdo_pgsql' ? 'pgsql' : 'mysql';
        $dsn = sprintf(
            '%s://%s%s@%s:%d/%s',
            $scheme,
            $this->user,
            $pass,
            $this->host,
            $this->port,
            $tenantBootDbName
        );

        $kernelConfig = [
            'tenant_connection' => [
                'url' => $dsn,
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => (string) $this->port,
                'charset' => 'utf8',
                'server_version' => $this->serverVersion,
            ],
        ];

        $serviceRegistrar = null;
        if ($this->useCustomProvider) {
            $kernelConfig['tenant_config_provider'] = 'test.in_memory_config_provider';
            $serviceRegistrar = function (ContainerBuilder $container): void {
                $container->register('test.in_memory_config_provider', InMemoryTenantConfigProvider::class)
                    ->setPublic(true);
            };
        }

        // Clear stale container cache
        $kernel = new IntegrationTestKernel($kernelConfig, $serviceRegistrar);
        $cacheDir = $kernel->getCacheDir();
        if (is_dir($cacheDir)) {
            (new Filesystem())->remove($cacheDir);
        }

        static::$kernel = new IntegrationTestKernel($kernelConfig, $serviceRegistrar);
        static::$kernel->boot();
        static::$container = static::$kernel->getContainer()->has('test.service_container')
            ? static::$kernel->getContainer()->get('test.service_container')
            : static::$kernel->getContainer();
    }

    private function rebootWithCustomProvider(): void
    {
        if (static::$kernel !== null) {
            static::$kernel->shutdown();
            static::$kernel = null;
            static::$container = null;
        }

        $this->useCustomProvider = true;
        $this->bootKernel();
        $this->createMainSchema();
    }

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

    public function testCustomProviderUsedForRealDbSwitch(): void
    {
        $this->rebootWithCustomProvider();

        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);

        // Populate the InMemoryTenantConfigProvider with real DB config
        /** @var InMemoryTenantConfigProvider $provider */
        $provider = $this->getContainer()->get(TenantConfigProviderInterface::class);
        $this->assertInstanceOf(InMemoryTenantConfigProvider::class, $provider);

        $provider->addTenant('custom-tenant-1', TenantConnectionConfigDTO::fromArgs(
            identifier: 'custom-tenant-1',
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        ));

        // Dispatch SwitchDbEvent — should use custom provider to resolve config
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent('custom-tenant-1'));

        // Verify real connection works
        $tenantEm = $this->getTenantEntityManager();
        $result = $tenantEm->getConnection()->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
    }

    public function testAddNewTenantDbConfigAndSwitchToIt(): void
    {
        $dbName = $this->generateUniqueDatabaseName();

        /** @var TenantDatabaseManagerInterface $manager */
        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);

        // Add a new tenant config via the manager (runtime addition)
        $newTenantDto = $manager->addNewTenantDbConfig(TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        ));

        $this->assertNotNull($newTenantDto->identifier, 'addNewTenantDbConfig should return a DTO with an identifier');

        // Create the actual database
        $createDto = TenantConnectionConfigDTO::fromArgs(
            identifier: $newTenantDto->identifier,
            driver: $this->driverType,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $this->host,
            port: $this->port,
            dbname: $dbName,
            user: $this->user,
            password: $this->password,
        );
        $manager->createTenantDatabase($createDto);
        $this->trackDatabase($dbName);

        // Update status to DATABASE_CREATED
        $manager->updateTenantDatabaseStatus($newTenantDto->identifier, DatabaseStatusEnum::DATABASE_CREATED);

        // Switch to the new tenant DB
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $newTenantDto->identifier));

        // Create schema and do CRUD
        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Runtime Tenant Product');
        $product->setPrice('50.00');
        $tenantEm->persist($product);
        $tenantEm->flush();

        $tenantEm->clear();
        $found = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $found);
        $this->assertSame('Runtime Tenant Product', $found[0]->getName());
    }

    public function testManagerListsAndFiltersByStatus(): void
    {
        // Insert multiple tenant configs with different statuses
        $dbNameCreated = $this->generateUniqueDatabaseName();
        $dbNameMigrated1 = $this->generateUniqueDatabaseName();
        $dbNameMigrated2 = $this->generateUniqueDatabaseName();

        $this->insertTenantConfig($dbNameCreated, DatabaseStatusEnum::DATABASE_CREATED);
        $this->insertTenantConfig($dbNameMigrated1, DatabaseStatusEnum::DATABASE_MIGRATED);
        $this->insertTenantConfig($dbNameMigrated2, DatabaseStatusEnum::DATABASE_MIGRATED);

        /** @var TenantDatabaseManagerInterface $manager */
        $manager = $this->getContainer()->get(TenantDatabaseManagerInterface::class);

        // listDatabases() returns only DATABASE_MIGRATED
        $migratedList = $manager->listDatabases();
        $migratedDbNames = array_map(fn($dto) => $dto->dbname, $migratedList);
        $this->assertContains($dbNameMigrated1, $migratedDbNames);
        $this->assertContains($dbNameMigrated2, $migratedDbNames);
        $this->assertNotContains($dbNameCreated, $migratedDbNames);

        // getTenantDbListByDatabaseStatus(DATABASE_CREATED) returns correct subset
        $createdList = $manager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
        $createdDbNames = array_map(fn($dto) => $dto->dbname, $createdList);
        $this->assertContains($dbNameCreated, $createdDbNames);
        $this->assertNotContains($dbNameMigrated1, $createdDbNames);
        $this->assertNotContains($dbNameMigrated2, $createdDbNames);
    }
}
