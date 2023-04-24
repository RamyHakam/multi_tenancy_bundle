<?php

namespace Hakam\MultiTenancyBundle\Services;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
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
    ) {
    }
    /**
     * Creates a new database with the given name.
     *
     * @param string $dbName The name of the new database.
     * @throws MultiTenancyException|Exception If the database already exists or cannot be created.
     */
    public function createDatabase(string $dbName): void
    {
        $params = [
            "url" => $this->dbCredentials['db_url'],
        ];

        // Override the dbname without preferred dbname
        $tmpConnection = DriverManager::getConnection($params);

        $platform = $tmpConnection->getDatabasePlatform();
        if ($tmpConnection->getDriver() instanceof AbstractMySQLDriver) {
            $sql = $platform->getListDatabasesSQL();
        } else {
            $sql = 'SELECT name FROM sqlite_master WHERE type = "database"';
        }
        $statement = $tmpConnection->executeQuery($sql);
        $databaseList = $statement->fetchFirstColumn();

        $shouldNotCreateDatabase = in_array($dbName, $databaseList);

        if ($shouldNotCreateDatabase) {
            throw new MultiTenancyException(sprintf('Database %s already exists.', $dbName), Response::HTTP_BAD_REQUEST);
        }

        try {
            $schemaManager = method_exists($tmpConnection, 'createSchemaManager')
                ? $tmpConnection->createSchemaManager()
                : $tmpConnection->getSchemaManager();

            $schemaManager->createDatabase($dbName);
        } catch (\Exception $e) {
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage()), $e->getCode(), $e);
        }

        $tmpConnection->close();
    }

    /**
     * Creates a schema in the specified tenant database.
     *
     * @param int $UserDbId The tenant database ID.
     */
    public function createSchemaInDb(int $UserDbId): void
    {
        $metadata = $this->tenantEntityManager->getMetadataFactory()->getAllMetadata();

        $this->eventDispatcher->dispatch(new SwitchDbEvent($UserDbId));

        $schemaTool = new SchemaTool($this->tenantEntityManager);

        $sqls = $schemaTool->getUpdateSchemaSql($metadata);

        if (empty($sqls)) {
            return;
        }

        $schemaTool->updateSchema($metadata);
    }

    /**
     * Drops the specified database.
     *
     * @param string $dbName The name of the database to drop.
     * @throws MultiTenancyException|Exception If the database does not exist or cannot be dropped.
     */
    public function dropDatabase(string $dbName): void
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
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage()), $e->getCode(), $e);
        }

        $tmpConnection->close();
    }
}
