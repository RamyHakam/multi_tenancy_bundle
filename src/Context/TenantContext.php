<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Context;

use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Symfony\Contracts\Service\ResetInterface;

class TenantContext implements TenantContextInterface, ResetInterface
{
    private ?string $tenantId = null;
    private ?string $dbName = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function onTenantSwitched(TenantSwitchedEvent $event): void
    {
        $this->tenantId = (string) $event->getTenantIdentifier();
        $this->dbName = $event->getTenantConfig()?->dbname;
    }

    public function reset(): void
    {
        $this->tenantId = null;
        $this->dbName = null;
    }

    public function getSchema(): string
    {
        if ($this->dbName === null) {
            throw new \LogicException('Cannot get schema: no tenant is currently active.');
        }

        return $this->dbName;
    }
}
