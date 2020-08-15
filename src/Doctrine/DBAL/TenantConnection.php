<?php


namespace Hakam\DoctrineDbSwitcherBundle\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Event;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class TenantConnection extends Connection
{

    /** @var mixed */
    protected $params = [];
    /** @var bool */
    protected $isConnected = false;
    /** @var bool */
    protected $autoCommit = true;

    /**
     * ConnectionSwitcher constructor.
     *
     * @param $params
     * @param Driver $driver
     * @param Configuration|null $config
     * @param EventManager|null $eventManager
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct($params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->params = $params;
        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @return bool
     */
    public function connect()
    {
        if ($this->isConnected) {
            return false;
        }

        $driverOptions = $this->params['driverOptions'] ?? [];
        $user = $this->params['user'] ?? null;
        $password = $this->params['password'] ?? null;

        $this->_conn = $this->_driver->connect($this->params, $user, $password, $driverOptions);
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
     * @param string $dbName
     * @param string $dbUser
     * @param string $dbPassword
     * @return TenantConnection
     */
    public function changeParams(string $dbName, string $dbUser, ?string $dbPassword): self
    {
        $this->params['dbname'] = $dbName;
        $this->params['user'] = $dbUser;
        $this->params['password'] = $dbPassword;
        return $this;
    }

    public function reconnect()
    {
        if ($this->isConnected) {
            $this->close();
        }

        $this->connect();
    }

    /**
     * @return mixed|mixed[]
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
