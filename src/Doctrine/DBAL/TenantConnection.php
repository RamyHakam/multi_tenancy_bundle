<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class TenantConnection extends Connection
{
    /** @var mixed */
    protected array $params = [];
    /** @var bool */
    protected bool $isConnected = false;
    /** @var bool */
    protected bool $autoCommit = true;

    /**
     * @param array $params
     * @return bool
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function switchConnection(array $params): bool
    {
        $this->close();
        $this->_conn = property_exists($this, 'driver') ? $this->driver->connect($params) : $this->_driver->connect($params);
        
        $this->isConnected = true;
        
        if ($this->autoCommit === false) {
            $this->beginTransaction();
        }
        return true;
    }

    public function close(): void
    {
        $this->_conn = null;
        $this->isConnected = false;
    }
}
