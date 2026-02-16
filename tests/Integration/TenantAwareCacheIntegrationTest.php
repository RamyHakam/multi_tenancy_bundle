<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Cache\TenantAwareCacheDecorator;
use Hakam\MultiTenancyBundle\Context\TenantContext;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Psr\Cache\CacheItemPoolInterface;

class TenantAwareCacheIntegrationTest extends IntegrationTestCase
{
    protected function getKernelConfig(): array
    {
        return ['cache' => ['enabled' => true]];
    }

    public function testCacheDecoratorIsRegistered(): void
    {
        $cacheApp = $this->getContainer()->get('cache.app');
        $this->assertInstanceOf(TenantAwareCacheDecorator::class, $cacheApp);
    }

    public function testTenantContextIsRegistered(): void
    {
        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertInstanceOf(TenantContext::class, $context);
    }

    public function testCacheIsolationBetweenTenants(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');
        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);

        // Simulate tenant A
        $this->simulateTenantSwitch($context, 'tenant_a');
        $itemA = $cache->getItem('shared_key');
        $itemA->set('value_from_tenant_a');
        $cache->save($itemA);

        // Simulate tenant B
        $this->simulateTenantSwitch($context, 'tenant_b');
        $itemB = $cache->getItem('shared_key');
        $this->assertFalse($itemB->isHit(), 'Tenant B should not see tenant A cache');
        $itemB->set('value_from_tenant_b');
        $cache->save($itemB);

        // Switch back to tenant A
        $this->simulateTenantSwitch($context, 'tenant_a');
        $itemA2 = $cache->getItem('shared_key');
        $this->assertTrue($itemA2->isHit());
        $this->assertSame('value_from_tenant_a', $itemA2->get());

        // Verify tenant B still has its own value
        $this->simulateTenantSwitch($context, 'tenant_b');
        $itemB2 = $cache->getItem('shared_key');
        $this->assertTrue($itemB2->isHit());
        $this->assertSame('value_from_tenant_b', $itemB2->get());
    }

    public function testCacheWithoutTenantPassesThrough(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');

        // No tenant switch - keys should pass through unprefixed
        $item = $cache->getItem('global_key');
        $item->set('global_value');
        $cache->save($item);

        $retrieved = $cache->getItem('global_key');
        $this->assertTrue($retrieved->isHit());
        $this->assertSame('global_value', $retrieved->get());
    }

    public function testCacheItemReturnsOriginalKey(): void
    {
        /** @var CacheItemPoolInterface $cache */
        $cache = $this->getContainer()->get('cache.app');
        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);

        $this->simulateTenantSwitch($context, 'tenant_x');
        $item = $cache->getItem('my_key');

        $this->assertSame('my_key', $item->getKey());
    }

    private function simulateTenantSwitch(TenantContext $context, string $tenantId): void
    {
        $event = $this->createMock(\Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent::class);
        $event->method('getTenantIdentifier')->willReturn($tenantId);
        $context->onTenantSwitched($event);
    }
}
