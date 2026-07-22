<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

/**
 * Wraps the real DBAL driver to intercept connect() calls,
 * applying tenant-specific connection parameters when available.
 *
 * Extends AbstractDriverMiddleware which handles delegation of all
 * Driver interface methods except connect().
 */
final class TenantDriver extends AbstractDriverMiddleware
{
    public function __construct(
        \Doctrine\DBAL\Driver $driver,
        private readonly TenantDriverMiddleware $middleware,
    ) {
        parent::__construct($driver);
    }

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        $overrideParams = $this->middleware->getOverrideParams();

        if ($overrideParams !== null) {
            $params = array_merge($params, $overrideParams);
        }

        return parent::connect($params);
    }
}
