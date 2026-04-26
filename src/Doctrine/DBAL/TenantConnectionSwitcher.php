<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\DBAL\Connection;

/**
 * Coordinates dynamic tenant database switching via DBAL middleware.
 *
 * Replaces the old TenantConnection (wrapper_class) approach that was
 * incompatible with DBAL 4.
 */
final class TenantConnectionSwitcher
{
    private \ReflectionProperty $paramsProperty;

    public function __construct(
        private readonly Connection $tenantConnection,
        private readonly TenantDriverMiddleware $middleware,
    ) {
        $this->paramsProperty = new \ReflectionProperty(Connection::class, 'params');
    }

    /**
     * Switch the tenant connection to use new parameters.
     *
     * Updates the middleware override params (used by TenantDriver on reconnect),
     * syncs Connection::$params so that getDatabase() returns the correct value,
     * then closes the connection to trigger a lazy reconnect.
     */
    public function switchConnection(array $params): void
    {
        $this->middleware->setOverrideParams($params);

        // Sync Connection's internal params so getDatabase() and other
        // metadata methods reflect the switched database.
        $currentParams = $this->paramsProperty->getValue($this->tenantConnection);
        $this->paramsProperty->setValue(
            $this->tenantConnection,
            array_merge($currentParams, $params),
        );

        $this->tenantConnection->close();
    }
}
