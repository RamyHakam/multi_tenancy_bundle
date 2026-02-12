<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

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

abstract class IntegrationTestCase extends TestCase
{
    protected static ?KernelInterface $kernel = null;
    protected static ?ContainerInterface $container = null;

    protected function getKernelConfig(): array
    {
        return [];
    }

    protected function bootKernel(): void
    {
        static::$kernel = new IntegrationTestKernel($this->getKernelConfig());
        static::$kernel->boot();
        // Use test.service_container for access to private services
        static::$container = static::$kernel->getContainer()->has('test.service_container')
            ? static::$kernel->getContainer()->get('test.service_container')
            : static::$kernel->getContainer();
    }

    protected function setUp(): void
    {
        $this->bootKernel();
        $this->createMainSchema();
    }

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            static::$kernel->shutdown();
            static::$kernel = null;
            static::$container = null;
        }
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

    protected function insertTenantConfig(
        string             $dbName,
        DatabaseStatusEnum $status = DatabaseStatusEnum::DATABASE_NOT_CREATED,
        DriverTypeEnum     $driver = DriverTypeEnum::SQLITE,
        ?string            $host = 'localhost',
        ?int               $port = 0,
        ?string            $user = 'test_user',
        ?string            $password = 'test_pass'
    ): TenantDbConfig {
        $em = $this->getDefaultEntityManager();
        $config = new TenantDbConfig();
        $config->setDbName($dbName);
        $config->setDatabaseStatus($status);
        $config->setDriverType($driver);
        $config->setDbHost($host);
        $config->setDbPort($port);
        $config->setDbUserName($user);
        $config->setDbPassword($password);
        $em->persist($config);
        $em->flush();

        return $config;
    }
}
