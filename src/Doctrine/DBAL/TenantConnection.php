<?php

namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Event;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class TenantConnection extends Connection
{
    /** @var mixed */
    protected array $params = [];

    protected bool $isConnected = false;

    protected bool  $autoCommit = true;

    /**
     * ConnectionSwitcher constructor.
     *
     * @throws Exception
     */
    public function __construct($params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->params = $params;
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @throws Exception
     */
    public function connect(): bool
    {
        if ($this->isConnected) {
            return false;
        }
        $this->_conn = $this->_driver->connect($this->params);
        $this->isConnected = true;

        if (false === $this->autoCommit) {
            $this->beginTransaction();
        }

        if ($this->_eventManager->hasListeners(Events::postConnect)) {
            $eventArgs = new Event\ConnectionEventArgs($this);
            $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
        }

        return true;
    }

    public function changeParams(string $dbName, string $dbUser, ?string $dbPassword): self
    {
        $this->params['dbname'] = $dbName;
        $this->params['user'] = $dbUser;
        $this->params['password'] = $dbPassword;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function reconnect()
    {
        if ($this->isConnected) {
            $this->close();
        }

        $this->connect();
    }

    /**
     * @return mixed|array
     */
    public function getParams()
    {
        return $this->params;
    }

    public function close()
    {
        $this->_conn = null;

        $this->isConnected = false;
    }
}
