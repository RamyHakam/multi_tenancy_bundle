<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Context;

use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Symfony\Contracts\Service\ResetInterface;

class TenantContext implements TenantContextInterface, ResetInterface
{
    private ?string $tenantId = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function onTenantSwitched(TenantSwitchedEvent $event): void
    {
        $this->tenantId = (string) $event->getTenantIdentifier();
    }

    public function reset(): void
    {
        $this->tenantId = null;
    }
}
