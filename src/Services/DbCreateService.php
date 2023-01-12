<?php

namespace Hakam\MultiTenancyBundle\Services;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @author Mark Ogilvie <m.ogilvie@parolla.ie>
 */
class DbCreateService
{
    public function __construct(private EntityManagerInterface $doctrine, private EventDispatcherInterface $eventDispatcher, private TenantEntityManager $entityManager)
    {
    }

    public function createDatabase($dbName): void
    {
        $connection = $this->doctrine->getConnection('tenant');

        $params = $connection->getParams();

        $tmpConnection = DriverManager::getConnection($params);

        $schemaManager = method_exists($tmpConnection, 'createSchemaManager')
            ? $tmpConnection->createSchemaManager()
            : $tmpConnection->getSchemaManager();

        $shouldNotCreateDatabase = in_array($dbName, $schemaManager->listDatabases());

        if ($shouldNotCreateDatabase) {
            return;
        }

        try {
            $schemaManager->createDatabase($dbName);
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage()));
        }

        $tmpConnection->close();
    }

    public function createSchemaInNewDb($tenantId)
    {
        $switchEvent = new SwitchDbEvent($tenantId);
        $this->eventDispatcher->dispatch($switchEvent);

        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($this->entityManager);

        $sqls = $schemaTool->getUpdateSchemaSql($metadatas);

        if (empty($sqls)) {
            return;
        }

        $schemaTool->updateSchema($metadatas);
    }
}
