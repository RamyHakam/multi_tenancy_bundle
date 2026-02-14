<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;

/**
 * Event dispatched when a tenant database has been bootstrapped with initial data.
 *
 * This event is fired after fixtures have been loaded into the tenant database.
 *
 * Use cases:
 * - Notify tenant that their environment is ready
 * - Trigger post-setup tasks
 * - Initialize default configurations
 * - Send onboarding emails
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class TenantBootstrappedEvent extends AbstractTenantEvent
{
    public function __construct(
        mixed $tenantIdentifier,
        ?TenantConnectionConfigDTO $tenantConfig = null,
        private readonly array $loadedFixtures = [],
    ) {
        parent::__construct($tenantIdentifier, $tenantConfig);
    }

    /**
     * Get the list of loaded fixture class names.
     *
     * @return array<string>
     */
    public function getLoadedFixtures(): array
    {
        return $this->loadedFixtures;
    }
}
