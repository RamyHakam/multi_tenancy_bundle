<?php

namespace Hakam\MultiTenancyBundle\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class TenantDatabaseIdentifier
{
    private function __construct(private readonly string $uuid)
    {
    }

    /**
     * Accepts the stored form written by TenantDatabaseIdentifierType — a bare
     * UUID — and, for convenience, a "<prefix>:<uuid>" form. The prefix is
     * informational only and is dropped.
     */
    public static function create(string $identifier): self
    {
        $uuid = str_contains($identifier, ':')
            ? substr($identifier, strrpos($identifier, ':') + 1)
            : $identifier;

        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException(sprintf('Invalid tenant database identifier: "%s".', $identifier));
        }

        return new self($uuid);
    }

    /**
     * Derives a stable identifier from a value that is already unique per
     * tenant (the database name, for instance), so the same value always maps
     * to the same identifier.
     */
    public static function generateWithValue(string $value): self
    {
        $namespace = Uuid::fromString(Uuid::NAMESPACE_DNS);

        return new self(Uuid::v5($namespace, $value)->toRfc4122());
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
