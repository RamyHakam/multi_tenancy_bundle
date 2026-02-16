<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Cache;

use Hakam\MultiTenancyBundle\Cache\TenantAwareCacheDecorator;
use Hakam\MultiTenancyBundle\Cache\TenantAwareCacheItem;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

class TenantAwareCacheDecoratorTest extends TestCase
{
    private CacheItemPoolInterface&CacheInterface&MockObject $inner;
    private TenantContextInterface&MockObject $tenantContext;
    private TenantAwareCacheDecorator $decorator;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(InnerCachePool::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->decorator = new TenantAwareCacheDecorator($this->inner, $this->tenantContext);
    }

    public function testGetItemPrefixesKeyWhenTenantActive(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('tenant_1');

        $innerItem = $this->createMock(CacheItemInterface::class);
        $innerItem->method('getKey')->willReturn('tenant_1__my_key');

        $this->inner->expects($this->once())
            ->method('getItem')
            ->with('tenant_1__my_key')
            ->willReturn($innerItem);

        $result = $this->decorator->getItem('my_key');

        $this->assertInstanceOf(TenantAwareCacheItem::class, $result);
        $this->assertSame('my_key', $result->getKey());
    }

    public function testGetItemPassesThroughWhenNoTenant(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $innerItem = $this->createMock(CacheItemInterface::class);
        $innerItem->method('getKey')->willReturn('my_key');

        $this->inner->expects($this->once())
            ->method('getItem')
            ->with('my_key')
            ->willReturn($innerItem);

        $result = $this->decorator->getItem('my_key');

        $this->assertSame('my_key', $result->getKey());
    }

    public function testGetItemsPrefixesAllKeys(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $item1 = $this->createMock(CacheItemInterface::class);
        $item2 = $this->createMock(CacheItemInterface::class);

        $this->inner->expects($this->once())
            ->method('getItems')
            ->with(['t1__key1', 't1__key2'])
            ->willReturn(['t1__key1' => $item1, 't1__key2' => $item2]);

        $results = $this->decorator->getItems(['key1', 'key2']);

        $this->assertArrayHasKey('key1', $results);
        $this->assertArrayHasKey('key2', $results);
        $this->assertInstanceOf(TenantAwareCacheItem::class, $results['key1']);
        $this->assertInstanceOf(TenantAwareCacheItem::class, $results['key2']);
    }

    public function testHasItemPrefixesKey(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $this->inner->expects($this->once())
            ->method('hasItem')
            ->with('t1__key')
            ->willReturn(true);

        $this->assertTrue($this->decorator->hasItem('key'));
    }

    public function testDeleteItemPrefixesKey(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $this->inner->expects($this->once())
            ->method('deleteItem')
            ->with('t1__key')
            ->willReturn(true);

        $this->assertTrue($this->decorator->deleteItem('key'));
    }

    public function testDeleteItemsPrefixesAllKeys(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $this->inner->expects($this->once())
            ->method('deleteItems')
            ->with(['t1__a', 't1__b'])
            ->willReturn(true);

        $this->assertTrue($this->decorator->deleteItems(['a', 'b']));
    }

    public function testSaveUnwrapsTenantAwareCacheItem(): void
    {
        $innerItem = $this->createMock(CacheItemInterface::class);
        $wrappedItem = new TenantAwareCacheItem($innerItem, 'original_key');

        $this->inner->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($innerItem))
            ->willReturn(true);

        $this->assertTrue($this->decorator->save($wrappedItem));
    }

    public function testSaveDeferredUnwrapsTenantAwareCacheItem(): void
    {
        $innerItem = $this->createMock(CacheItemInterface::class);
        $wrappedItem = new TenantAwareCacheItem($innerItem, 'original_key');

        $this->inner->expects($this->once())
            ->method('saveDeferred')
            ->with($this->identicalTo($innerItem))
            ->willReturn(true);

        $this->assertTrue($this->decorator->saveDeferred($wrappedItem));
    }

    public function testClearDelegatesToInner(): void
    {
        $this->inner->expects($this->once())
            ->method('clear')
            ->with('')
            ->willReturn(true);

        $this->assertTrue($this->decorator->clear());
    }

    public function testCommitDelegatesToInner(): void
    {
        $this->inner->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $this->assertTrue($this->decorator->commit());
    }

    public function testGetPrefixesKeyWhenTenantActive(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $this->inner->expects($this->once())
            ->method('get')
            ->with('t1__my_key', $this->isType('callable'))
            ->willReturn('cached_value');

        $result = $this->decorator->get('my_key', fn() => 'computed_value');

        $this->assertSame('cached_value', $result);
    }

    public function testGetPassesThroughWhenNoTenant(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $this->inner->expects($this->once())
            ->method('get')
            ->with('my_key', $this->isType('callable'))
            ->willReturn('cached_value');

        $result = $this->decorator->get('my_key', fn() => 'computed_value');

        $this->assertSame('cached_value', $result);
    }

    public function testDeletePrefixesKey(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $this->inner->expects($this->once())
            ->method('delete')
            ->with('t1__key')
            ->willReturn(true);

        $this->assertTrue($this->decorator->delete('key'));
    }

    public function testCustomSeparator(): void
    {
        $decorator = new TenantAwareCacheDecorator($this->inner, $this->tenantContext, '.');

        $this->tenantContext->method('getTenantId')->willReturn('t1');

        $innerItem = $this->createMock(CacheItemInterface::class);

        $this->inner->expects($this->once())
            ->method('getItem')
            ->with('t1.key')
            ->willReturn($innerItem);

        $decorator->getItem('key');
    }
}

/**
 * Combined interface for mocking purposes.
 */
interface InnerCachePool extends CacheItemPoolInterface, CacheInterface
{
}
