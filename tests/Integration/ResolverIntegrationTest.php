<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\EventListener\TenantResolutionListener;
use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\ChainResolver;
use Hakam\MultiTenancyBundle\Resolver\HeaderResolver;
use Hakam\MultiTenancyBundle\Tests\Integration\Kernel\IntegrationTestKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ResolverIntegrationTest extends IntegrationTestCase
{
    private array $resolverConfig = [];

    protected function getKernelConfig(): array
    {
        return ['resolver' => $this->resolverConfig];
    }

    protected function bootKernel(): void
    {
        // Clear stale container cache to avoid spl_object_id reuse issues
        $kernel = new IntegrationTestKernel($this->getKernelConfig(), $this->getServiceRegistrar());
        $cacheDir = $kernel->getCacheDir();
        if (is_dir($cacheDir)) {
            (new Filesystem())->remove($cacheDir);
        }

        static::$kernel = $kernel;
        static::$kernel->boot();
        static::$container = static::$kernel->getContainer()->has('test.service_container')
            ? static::$kernel->getContainer()->get('test.service_container')
            : static::$kernel->getContainer();
    }

    private function bootWithResolverConfig(array $config): void
    {
        // Shutdown previous kernel if running
        if (static::$kernel !== null) {
            static::$kernel->shutdown();
            static::$kernel = null;
            static::$container = null;
        }

        $this->resolverConfig = $config;
        $this->bootKernel();
        $this->createMainSchema();
    }

    public function testHeaderResolverServiceWiredCorrectly(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
        ]);

        $resolver = $this->getContainer()->get(TenantResolverInterface::class);
        $this->assertInstanceOf(HeaderResolver::class, $resolver);
    }

    public function testHeaderResolverDispatchesSwitchDbEvent(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
        ]);

        $tenant = $this->insertTenantConfig(
            dbName: 'resolver_header_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $capturedEvents = [];
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addListener(SwitchDbEvent::class, function (SwitchDbEvent $event) use (&$capturedEvents) {
            $capturedEvents[] = $event;
        });

        $request = Request::create('/some-page', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenant->getId());

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertCount(1, $capturedEvents);
        $this->assertSame((string) $tenant->getId(), $capturedEvents[0]->getDbIndex());
    }

    public function testHeaderResolverUpdatesTenantContext(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
        ]);

        $tenant = $this->insertTenantConfig(
            dbName: 'resolver_context_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $request = Request::create('/any-path', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenant->getId());

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertSame((string) $tenant->getId(), $context->getTenantId());
    }

    public function testChainResolverWiresMultipleResolvers(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'chain',
            'options' => [
                'chain_order' => ['header', 'path'],
            ],
        ]);

        $resolver = $this->getContainer()->get(TenantResolverInterface::class);
        $this->assertInstanceOf(ChainResolver::class, $resolver);
        $this->assertCount(2, $resolver->getResolvers());
    }

    public function testChainResolverFallsThrough(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'chain',
            'options' => [
                'chain_order' => ['header', 'path'],
            ],
        ]);

        // Request without header but with a path segment — path resolver picks it up
        $request = Request::create('/my-tenant/dashboard', 'GET');

        $resolver = $this->getContainer()->get(TenantResolverInterface::class);
        $tenantId = $resolver->resolve($request);

        $this->assertSame('my-tenant', $tenantId);
    }

    public function testExcludedPathSkipsResolution(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
            'excluded_paths' => ['/health', '/api/public'],
        ]);

        $capturedEvents = [];
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addListener(SwitchDbEvent::class, function (SwitchDbEvent $event) use (&$capturedEvents) {
            $capturedEvents[] = $event;
        });

        $request = Request::create('/health', 'GET');
        $request->headers->set('X-Tenant-ID', '1');

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        $this->assertCount(0, $capturedEvents, 'SwitchDbEvent should not fire for excluded path');
    }

    public function testThrowOnMissingThrowsWhenNoTenantResolved(): void
    {
        $this->bootWithResolverConfig([
            'enabled' => true,
            'strategy' => 'header',
            'throw_on_missing' => true,
        ]);

        // Request without the X-Tenant-ID header — resolver does not support it
        $request = Request::create('/some-page', 'GET');

        $listener = $this->getContainer()->get(TenantResolutionListener::class);
        $event = new RequestEvent(static::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/resolve tenant/i');

        $listener->onKernelRequest($event);
    }
}
