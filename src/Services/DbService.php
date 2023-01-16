<?php

namespace Hakam\MultiTenancyBundle\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Mark Ogilvie <m.ogilvie@parolla.ie>
 */
class DbService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private TenantEntityManager $tenantEntityManager,
        #[Autowire('%tenant_db_credentials%')]
        private array $dbCredentials
    )
    {
    }

    public function createDatabase($dbName): void
    {
        $params = [
            "url" => $this->dbCredentials['db_url'],
        ];

        // Override the dbname without preferred dbname
        $tmpConnection = DriverManager::getConnection($params);

        $schemaManager = method_exists($tmpConnection, 'createSchemaManager')
            ? $tmpConnection->createSchemaManager()
            : $tmpConnection->getSchemaManager();

        $shouldNotCreateDatabase = in_array($dbName, $schemaManager->listDatabases());

        if ($shouldNotCreateDatabase) {
            throw new MultiTenancyException(sprintf('Database %s already exists.', $dbName), Response::HTTP_BAD_REQUEST);
        }

        try {
            $schemaManager->createDatabase($dbName);
        } catch (\Exception $e) {
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage()),$e->getCode(), $e);
        }

        $tmpConnection->close();
    }

    public function createSchemaInDb()
    {
        $metadatas = $this->tenantEntityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($this->tenantEntityManager);

        $sqls = $schemaTool->getUpdateSchemaSql($metadatas);

        if (empty($sqls)) {
            return;
        }

        $schemaTool->updateSchema($metadatas);
    }

    public function dropDatabase($dbName): void
    {
        $connection = $this->tenantEntityManager->getConnection();

        $params = $connection->getParams();

        $tmpConnection = DriverManager::getConnection($params);

        $schemaManager = method_exists($tmpConnection, 'createSchemaManager')
            ? $tmpConnection->createSchemaManager()
            : $tmpConnection->getSchemaManager();

        $shouldNotCreateDatabase = !in_array($dbName, $schemaManager->listDatabases());

        if ($shouldNotCreateDatabase) {
            throw new MultiTenancyException(sprintf('Database %s does not exist.', $dbName), Response::HTTP_BAD_REQUEST);
        }

        try {
            $schemaManager->dropDatabase($dbName);
        } catch (\Exception $e) {
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage() ), $e->getCode(), $e);
        }

        $tmpConnection->close();
    }
}
