<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Cache;

use Psr\Cache\CacheItemInterface;

class TenantAwareCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly CacheItemInterface $innerItem,
        private readonly string $originalKey,
    ) {
    }

    public function getKey(): string
    {
        return $this->originalKey;
    }

    public function get(): mixed
    {
        return $this->innerItem->get();
    }

    public function isHit(): bool
    {
        return $this->innerItem->isHit();
    }

    public function set(mixed $value): static
    {
        $this->innerItem->set($value);

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->innerItem->expiresAt($expiration);

        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        $this->innerItem->expiresAfter($time);

        return $this;
    }

    public function getInnerItem(): CacheItemInterface
    {
        return $this->innerItem;
    }
}
