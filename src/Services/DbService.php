<?php

namespace Hakam\MultiTenancyBundle\Services;

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
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
        private TenantEntityManager      $tenantEntityManager,
        private EntityManagerInterface   $entityManager,
        #[Autowire('%hakam.tenant_db_list_entity%')]
        private string                   $tenantDbListEntity,
        #[Autowire('%hakam.tenant_db_credentials%')]
        private array                    $dbCredentials
    )
    {
    }

    /**
     * Creates a new database with the given name.
     *
     * @param string $dbName The name of the new database.
     * @throws MultiTenancyException|Exception If the database already exists or cannot be created.
     */
    public function createDatabase(string $dbName): int
    {

        $dsnParser = new DsnParser(['mysql' => 'pdo_mysql']);
        $tmpConnection = DriverManager::getConnection($dsnParser->parse($this->dbCredentials['db_url']));

        $platform = $tmpConnection->getDatabasePlatform();
        if ($tmpConnection->getDriver() instanceof AbstractMySQLDriver || $tmpConnection->getDriver() instanceof AbstractPostgreSQLDriver) {
            $sql = $platform->getListDatabasesSQL();
        } else {
            // support SQLite
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
            $tmpConnection->close();
            return 1;

        } catch (\Exception $e) {
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s', $dbName, $e->getMessage()), $e->getCode(), $e);
        }
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

    private function onboardNewDatabaseConfig(string $dbname): int
    {
        //check if db already exists
        $dbConfig = $this->entityManager->getRepository($this->tenantDbListEntity)->findOneBy(['dbName' => $dbname]);
        if ($dbConfig) {
            return $dbConfig->getId();
        }
        $newDbConfig = new   $this->tenantDbListEntity();
        $newDbConfig->setDbName($dbname);
        $this->entityManager->persist($newDbConfig);
        $this->entityManager->flush();
        return $newDbConfig->getId();
    }

    public function getListOfNotCreatedDataBases(): array
    {
        return $this->entityManager->getRepository($this->tenantDbListEntity)->findBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_NOT_CREATED]);
    }

    public function getDefaultTenantDataBase(): TenantDbConfigurationInterface
    {
        return $this->entityManager->getRepository($this->tenantDbListEntity)->findOneBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED]);
    }
}
