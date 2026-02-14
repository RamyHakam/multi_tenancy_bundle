<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for all tenant lifecycle events.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
abstract class AbstractTenantEvent extends Event
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly mixed $tenantIdentifier,
        private readonly ?TenantConnectionConfigDTO $tenantConfig = null,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    /**
     * Get the tenant identifier.
     */
    public function getTenantIdentifier(): mixed
    {
        return $this->tenantIdentifier;
    }

    /**
     * Get the tenant connection configuration DTO.
     */
    public function getTenantConfig(): ?TenantConnectionConfigDTO
    {
        return $this->tenantConfig;
    }

    /**
     * Get the timestamp when this event occurred.
     */
    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
