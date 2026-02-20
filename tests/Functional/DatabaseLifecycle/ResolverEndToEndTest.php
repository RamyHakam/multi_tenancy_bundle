<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\EventListener\TenantResolutionListener;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;
use Hakam\MultiTenancyBundle\Tests\Integration\Kernel\IntegrationTestKernel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ResolverEndToEndTest extends RealDatabaseTestCase
{
    private array $resolverConfig = [];

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
            'resolver' => $this->resolverConfig,
        ];

        // Clear stale container cache to avoid spl_object_id reuse issues
        $kernel = new IntegrationTestKernel($kernelConfig);
        $cacheDir = $kernel->getCacheDir();
        if (is_dir($cacheDir)) {
            (new Filesystem())->remove($cacheDir);
        }

        static::$kernel = new IntegrationTestKernel($kernelConfig);
        static::$kernel->boot();
        static::$container = static::$kernel->getContainer()->has('test.service_container')
            ? static::$kernel->getContainer()->get('test.service_container')
            : static::$kernel->getContainer();
    }

    private function bootWithResolverConfig(array $config): void
    {
        if (static::$kernel !== null) {
            static::$kernel->shutdown();
            static::$kernel = null;
            static::$container = null;
        }

        $this->resolverConfig = $config;
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

    private function switchAndCreateSchemaWithData(string $tenantId, string $productName, string $price): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent($tenantId));

        $this->createTenantSchema();

        $tenantEm = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName($productName);
        $product->setPrice($price);
        $tenantEm->persist($product);
        $tenantEm->flush();
    }

    public function testHeaderResolverTriggersRealDbSwitch(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
        ]);

        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantId = (string) $tenant->getId();

        // Create schema and insert data in the tenant DB
        $this->switchAndCreateSchemaWithData($tenantId, 'Header Product', '25.00');

        // Clear EM to reset state
        $this->getTenantEntityManager()->clear();

        // Now simulate HTTP request with X-Tenant-ID header
        $request = Request::create('/some-page', 'GET');
        $request->headers->set('X-Tenant-ID', $tenantId);

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        // Verify tenant context is set
        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertSame($tenantId, $context->getTenantId());

        // Verify we can query real data from the tenant DB
        $tenantEm = $this->getTenantEntityManager();
        $tenantEm->clear();
        $products = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $products);
        $this->assertSame('Header Product', $products[0]->getName());
    }

    public function testPathResolverTriggersRealDbSwitch(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'path',
        ]);

        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantId = (string) $tenant->getId();

        $this->switchAndCreateSchemaWithData($tenantId, 'Path Product', '30.00');
        $this->getTenantEntityManager()->clear();

        // Simulate HTTP request with tenant ID in path
        $request = Request::create('/' . $tenantId . '/dashboard', 'GET');

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertSame($tenantId, $context->getTenantId());

        $tenantEm = $this->getTenantEntityManager();
        $tenantEm->clear();
        $products = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $products);
        $this->assertSame('Path Product', $products[0]->getName());
    }

    public function testChainResolverFallsToSecondResolver(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'chain',
            'options' => [
                'chain_order' => ['header', 'path'],
            ],
        ]);

        $dbName = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbName);
        $tenant = $this->insertTenantConfig($dbName, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantId = (string) $tenant->getId();

        $this->switchAndCreateSchemaWithData($tenantId, 'Chain Product', '35.00');
        $this->getTenantEntityManager()->clear();

        // Send request WITHOUT header but WITH path — path resolver should pick up
        $request = Request::create('/' . $tenantId . '/page', 'GET');

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertSame($tenantId, $context->getTenantId());

        $tenantEm = $this->getTenantEntityManager();
        $tenantEm->clear();
        $products = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $products);
        $this->assertSame('Chain Product', $products[0]->getName());
    }

    public function testResolverSwitchesBetweenTenantsOnConsecutiveRequests(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
        ]);

        // Create two tenant DBs with different data
        $dbNameA = $this->generateUniqueDatabaseName();
        $dbNameB = $this->generateUniqueDatabaseName();
        $this->createRealTenantDatabase($dbNameA);
        $this->createRealTenantDatabase($dbNameB);

        $tenantA = $this->insertTenantConfig($dbNameA, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantB = $this->insertTenantConfig($dbNameB, DatabaseStatusEnum::DATABASE_MIGRATED);
        $tenantIdA = (string) $tenantA->getId();
        $tenantIdB = (string) $tenantB->getId();

        $this->switchAndCreateSchemaWithData($tenantIdA, 'Tenant A Product', '10.00');
        $this->switchAndCreateSchemaWithData($tenantIdB, 'Tenant B Product', '20.00');

        $listener = $this->getContainer()->get(TenantResolutionListener::class);

        // First request — tenant A
        $requestA = Request::create('/page-a', 'GET');
        $requestA->headers->set('X-Tenant-ID', $tenantIdA);
        $eventA = new RequestEvent(static::$kernel, $requestA, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($eventA);

        $tenantEm = $this->getTenantEntityManager();
        $tenantEm->clear();
        $productsA = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $productsA);
        $this->assertSame('Tenant A Product', $productsA[0]->getName());

        // Second request — tenant B
        $requestB = Request::create('/page-b', 'GET');
        $requestB->headers->set('X-Tenant-ID', $tenantIdB);
        $eventB = new RequestEvent(static::$kernel, $requestB, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($eventB);

        $tenantEm->clear();
        $productsB = $tenantEm->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(1, $productsB);
        $this->assertSame('Tenant B Product', $productsB[0]->getName());
    }
}
