<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Functional\DatabaseLifecycle;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantDbConfig;
use Hakam\MultiTenancyBundle\Tests\Integration\Kernel\IntegrationTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class RealDatabaseTestCase extends TestCase
{
    protected static ?KernelInterface $kernel = null;
    protected static ?ContainerInterface $container = null;

    protected string $driver;
    protected string $host;
    protected int $port;
    protected string $user;
    protected string $password;
    protected string $serverVersion;
    protected DriverTypeEnum $driverType;

    /** @var string[] Database names created during the test, to be dropped in tearDown */
    private array $createdDatabases = [];

    protected function setUp(): void
    {
        $driver = getenv('TENANT_DB_DRIVER') ?: ($_ENV['TENANT_DB_DRIVER'] ?? '');
        if ($driver === '') {
            $this->markTestSkipped('TENANT_DB_DRIVER not set — skipping real database tests.');
        }

        $this->driver = $driver;
        $this->host = getenv('TENANT_DB_HOST') ?: ($_ENV['TENANT_DB_HOST'] ?? '127.0.0.1');
        $this->port = (int) (getenv('TENANT_DB_PORT') ?: ($_ENV['TENANT_DB_PORT'] ?? '3306'));
        $this->user = getenv('TENANT_DB_USER') ?: ($_ENV['TENANT_DB_USER'] ?? 'root');
        $this->password = getenv('TENANT_DB_PASSWORD') ?: ($_ENV['TENANT_DB_PASSWORD'] ?? '');
        $this->serverVersion = getenv('TENANT_DB_SERVER_VERSION') ?: ($_ENV['TENANT_DB_SERVER_VERSION'] ?? '8.0');

        $this->driverType = match ($this->driver) {
            'pdo_mysql' => DriverTypeEnum::MYSQL,
            'pdo_pgsql' => DriverTypeEnum::POSTGRES,
            default => $this->markTestSkipped("Unsupported driver: {$this->driver}"),
        };

        $this->bootKernel();
        $this->createMainSchema();
    }

    protected function tearDown(): void
    {
        $this->dropCreatedDatabases();

        if (static::$kernel !== null) {
            static::$kernel->shutdown();
            static::$kernel = null;
            static::$container = null;
        }
    }

    protected function bootKernel(): void
    {
        $tenantBootDbName = $this->driver === 'pdo_pgsql' ? 'postgres' : '';
        $pass = $this->password !== '' ? ':' . $this->password : '';
        $scheme = $this->driver === 'pdo_pgsql' ? 'pgsql' : 'mysql';
        $dsn = sprintf(
            '%s://%s%s@%s:%d/%s',
            $scheme,
            $this->user,
            $pass,
            $this->host,
            $this->port,
            $tenantBootDbName
        );

        $kernelConfig = [
            'tenant_connection' => [
                'url' => $dsn,
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => (string) $this->port,
                'charset' => 'utf8',
                'server_version' => $this->serverVersion,
            ],
        ];

        static::$kernel = new IntegrationTestKernel($kernelConfig);
        static::$kernel->boot();
        static::$container = static::$kernel->getContainer()->has('test.service_container')
            ? static::$kernel->getContainer()->get('test.service_container')
            : static::$kernel->getContainer();
    }

    protected function createMainSchema(): void
    {
        $em = $this->getDefaultEntityManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function createTenantSchema(): void
    {
        $em = $this->getTenantEntityManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function getDefaultEntityManager(): EntityManagerInterface
    {
        return static::$container->get('doctrine.orm.default_entity_manager');
    }

    protected function getTenantEntityManager(): TenantEntityManager
    {
        return static::$container->get('tenant_entity_manager');
    }

    protected function getContainer(): ContainerInterface
    {
        return static::$container;
    }

    protected function generateUniqueDatabaseName(): string
    {
        return 'hakam_test_' . uniqid();
    }

    /**
     * Track a database name for cleanup in tearDown.
     */
    protected function trackDatabase(string $dbName): void
    {
        $this->createdDatabases[] = $dbName;
    }

    protected function insertTenantConfig(
        string $dbName,
        DatabaseStatusEnum $status = DatabaseStatusEnum::DATABASE_NOT_CREATED,
    ): TenantDbConfig {
        $em = $this->getDefaultEntityManager();
        $config = new TenantDbConfig();
        $config->setDbName($dbName);
        $config->setDatabaseStatus($status);
        $config->setDriverType($this->driverType);
        $config->setDbHost($this->host);
        $config->setDbPort($this->port);
        $config->setDbUserName($this->user);
        $config->setDbPassword($this->password);
        $em->persist($config);
        $em->flush();

        return $config;
    }

    private function dropCreatedDatabases(): void
    {
        if (empty($this->createdDatabases)) {
            return;
        }

        try {
            $dsnParser = new DsnParser([
                'mysql' => 'pdo_mysql',
                'postgresql' => 'pdo_pgsql',
            ]);

            $pass = $this->password !== '' ? ':' . $this->password : '';
            $scheme = $this->driver === 'pdo_pgsql' ? 'pgsql' : 'mysql';
            $maintenanceDb = $this->driver === 'pdo_pgsql' ? '/postgres' : '';
            $maintenanceDsn = sprintf(
                '%s://%s%s@%s:%d%s',
                $scheme,
                $this->user,
                $pass,
                $this->host,
                $this->port,
                $maintenanceDb
            );

            $conn = DriverManager::getConnection($dsnParser->parse($maintenanceDsn));
            $schemaManager = method_exists($conn, 'createSchemaManager')
                ? $conn->createSchemaManager()
                : $conn->getSchemaManager();

            foreach ($this->createdDatabases as $dbName) {
                try {
                    $schemaManager->dropDatabase($dbName);
                } catch (\Throwable) {
                    // Best-effort cleanup — don't fail the test if DROP fails
                }
            }

            $conn->close();
        } catch (\Throwable) {
            // Best-effort cleanup
        }

        $this->createdDatabases = [];
    }
}
