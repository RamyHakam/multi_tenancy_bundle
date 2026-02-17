<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Cache\TenantAwareCacheDecorator;
use Hakam\MultiTenancyBundle\Context\TenantContext;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Tests\Integration\Kernel\IntegrationTestKernel;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

class CacheSwitchingCombinedTest extends IntegrationTestCase
{
    protected function getKernelConfig(): array
    {
        return ['cache' => ['enabled' => true]];
    }

    protected function bootKernel(): void
    {
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

    public function testCacheIsolationViaRealTenantSwitch(): void
    {
        $tenantA = $this->insertTenantConfig(
            dbName: 'cache_switch_a',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'cache_switch_b',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');

        // Switch to tenant A, write cache
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $itemA = $cache->getItem('shared_key');
        $itemA->set('value_from_A');
        $cache->save($itemA);

        // Switch to tenant B, assert cache miss
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $itemB = $cache->getItem('shared_key');
        $this->assertFalse($itemB->isHit(), 'Tenant B should not see tenant A cache');

        // Write different value for tenant B
        $itemB->set('value_from_B');
        $cache->save($itemB);

        // Switch back to tenant A, assert original value intact
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $retrieved = $cache->getItem('shared_key');
        $this->assertTrue($retrieved->isHit());
        $this->assertSame('value_from_A', $retrieved->get());
    }

    public function testCacheDeleteOnlyAffectsCurrentTenant(): void
    {
        $tenantA = $this->insertTenantConfig(
            dbName: 'cache_del_a',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'cache_del_b',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');

        // Write same key under both tenants
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $item = $cache->getItem('delete_test');
        $item->set('A_value');
        $cache->save($item);

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $item = $cache->getItem('delete_test');
        $item->set('B_value');
        $cache->save($item);

        // Delete under tenant B
        $cache->deleteItem('delete_test');

        // Verify tenant B's value is deleted
        $this->assertFalse($cache->getItem('delete_test')->isHit());

        // Switch to A, verify A's value still exists
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $itemA = $cache->getItem('delete_test');
        $this->assertTrue($itemA->isHit());
        $this->assertSame('A_value', $itemA->get());
    }

    public function testCacheHasItemRespectsCurrentTenant(): void
    {
        $tenantA = $this->insertTenantConfig(
            dbName: 'cache_has_a',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'cache_has_b',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');

        // Write under tenant A
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $item = $cache->getItem('has_test');
        $item->set('exists');
        $cache->save($item);

        $this->assertTrue($cache->hasItem('has_test'));

        // Switch to tenant B — should not have the item
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));
        $this->assertFalse($cache->hasItem('has_test'));
    }

    public function testCacheWorksWithoutTenantSwitch(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');

        // No tenant switch — write/read should work as passthrough
        $item = $cache->getItem('no_tenant_key');
        $item->set('global_value');
        $cache->save($item);

        $retrieved = $cache->getItem('no_tenant_key');
        $this->assertTrue($retrieved->isHit());
        $this->assertSame('global_value', $retrieved->get());
    }
}
