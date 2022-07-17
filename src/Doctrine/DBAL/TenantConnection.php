<?php


namespace Hakam\MultiTenancyBundle\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Event;
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
    protected bool  $autoCommit = true;

    /**
     * ConnectionSwitcher constructor.
     *
     * @param $params
     * @param Driver $driver
     * @param Configuration|null $config
     * @param EventManager|null $eventManager
     * @throws Exception
     */
    public function __construct($params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->params = $params;
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function connect(): bool
    {
        if ($this->isConnected) {
            return false;
        }
        $this->_conn = $this->_driver->connect(
            $this->params,
        );
        $this->isConnected = true;

        if ($this->autoCommit === false) {
            $this->beginTransaction();
        }

        if ($this->_eventManager->hasListeners(Events::postConnect)) {
            $eventArgs = new Event\ConnectionEventArgs($this);
            $this->_eventManager->dispatchEvent(Events::postConnect, $eventArgs);
        }

        return true;
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
