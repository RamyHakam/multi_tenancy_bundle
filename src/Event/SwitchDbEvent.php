<?php

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class SwitchDbEvent extends Event
{
    private ?TenantDatabaseIdentifier $identifier;

    /**
     * Dispatchers may pass the identifier itself or its string form, so call
     * sites that read a tenant id off a console argument or a request keep
     * working without knowing about the value object.
     */
    public function __construct(TenantDatabaseIdentifier|string|null $tenantIdentifier)
    {
        $this->identifier = is_string($tenantIdentifier)
            ? TenantDatabaseIdentifier::create($tenantIdentifier)
            : $tenantIdentifier;
    }

    public function getIdentifier(): ?TenantDatabaseIdentifier
    {
        return $this->identifier;
    }

    public function getDbIndex(): ?string
    {
        return $this->identifier === null ? null : (string) $this->identifier;
    }
}
