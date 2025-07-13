<?php

namespace Hakam\MultiTenancyBundle\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class TenantDatabaseIdentifier
{
    private function __construct(private  readonly string $uuid)
    {}

    public  static  function create(string $uuid): TenantDatabaseIdentifier
    {
        [$prefix, $uuid] = explode(':', $uuid);
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException("Invalid UUID: $uuid");
        }
        return new self($uuid);
    }
    public static function generateWithValue(string $value): self
    {
        $namespace =  Uuid::fromString(Uuid::NAMESPACE_DNS);
        $uuid = Uuid::v5($namespace, $value)->toRfc4122();
        return new self($uuid);
    }

    public function __toString(): string
    {
        return $this->uuid;
    }

    public function equals(self $other): bool
    {
        return $this->uuid === $other->uuid;
    }
}