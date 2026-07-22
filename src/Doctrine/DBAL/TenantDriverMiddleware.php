<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * DBAL Middleware that enables dynamic tenant connection switching.
 *
 * Holds mutable connection parameters that override the default ones
 * when the wrapped driver's connect() is called after a connection reset.
 */
final class TenantDriverMiddleware implements Middleware
{
    private ?array $overrideParams = null;

    public function wrap(Driver $driver): Driver
    {
        return new TenantDriver($driver, $this);
    }

    public function setOverrideParams(?array $params): void
    {
        $this->overrideParams = $params;
    }

    public function getOverrideParams(): ?array
    {
        return $this->overrideParams;
    }
}
