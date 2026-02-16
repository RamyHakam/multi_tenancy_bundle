<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Cache;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

class TenantAwareCacheDecorator implements CacheItemPoolInterface, CacheInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $inner,
        private readonly TenantContextInterface $tenantContext,
        private readonly string $separator = '__',
    ) {
    }

    public function getItem(string $key): CacheItemInterface
    {
        $prefixedKey = $this->prefixKey($key);
        $item = $this->inner->getItem($prefixedKey);

        return new TenantAwareCacheItem($item, $key);
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $keyMap = [];
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixed = $this->prefixKey($key);
            $keyMap[$prefixed] = $key;
            $prefixedKeys[] = $prefixed;
        }

        $items = $this->inner->getItems($prefixedKeys);
        $result = [];
        foreach ($items as $prefixedKey => $item) {
            $originalKey = $keyMap[$prefixedKey] ?? $prefixedKey;
            $result[$originalKey] = new TenantAwareCacheItem($item, $originalKey);
        }

        return $result;
    }

    public function hasItem(string $key): bool
    {
        return $this->inner->hasItem($this->prefixKey($key));
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->inner->clear($prefix);
    }

    public function deleteItem(string $key): bool
    {
        return $this->inner->deleteItem($this->prefixKey($key));
    }

    public function deleteItems(array $keys): bool
    {
        $prefixedKeys = array_map(fn(string $key) => $this->prefixKey($key), $keys);

        return $this->inner->deleteItems($prefixedKeys);
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($item instanceof TenantAwareCacheItem) {
            $item = $item->getInnerItem();
        }

        return $this->inner->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if ($item instanceof TenantAwareCacheItem) {
            $item = $item->getInnerItem();
        }

        return $this->inner->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->inner->commit();
    }

    /**
     * @template T
     * @param string $key
     * @param callable(CacheItemInterface, bool &$save): T $callback
     * @param float|null $beta
     * @param array|null &$metadata
     * @return T
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (!$this->inner instanceof CacheInterface) {
            throw new \LogicException(sprintf('The inner cache pool "%s" does not implement "%s".', get_class($this->inner), CacheInterface::class));
        }

        return $this->inner->get($this->prefixKey($key), $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        if (!$this->inner instanceof CacheInterface) {
            throw new \LogicException(sprintf('The inner cache pool "%s" does not implement "%s".', get_class($this->inner), CacheInterface::class));
        }

        return $this->inner->delete($this->prefixKey($key));
    }

    private function prefixKey(string $key): string
    {
        $tenantId = $this->tenantContext->getTenantId();

        if ($tenantId === null) {
            return $key;
        }

        return $tenantId . $this->separator . $key;
    }
}
