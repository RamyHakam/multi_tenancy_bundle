<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\EventListener;

use Hakam\MultiTenancyBundle\Config\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use PHPUnit\Framework\TestCase;
use Hakam\MultiTenancyBundle\EventListener\DbSwitchEventListener;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Hakam\MultiTenancyBundle\Services\DbConfigService;

class DbSwitchEventListenerTest extends TestCase
{
    public function testOnHakamMultiTenancyBundleEventSwitchDbEvent()
    {
        // mock the necessary dependencies
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockTenantDbConfigProvider = $this->createMock(TenantConfigProviderInterface::class);
        $mockTenantEntityManager = $this->createMock(TenantEntityManager::class);


        // create a test instance of the listener
        $listener = new DbSwitchEventListener($mockContainer, $mockTenantDbConfigProvider, $mockTenantEntityManager, 'test_database_url');

        // create a test event
        $testDbIndex = 1;
        $testEvent = new SwitchDbEvent($testDbIndex);

        // mock the expected behavior of the DbConfigService and ContainerInterface


        $mockDbConfig = TenantConnectionConfigDTO::fromArray(
            [
                'driver' => DriverTypeEnum::MYSQL,
                'host' => ' localhost',
                'port' => '3306',
                'dbname' => 'test_db_name',
                'user' => 'test_username',
                'password' => 'test_password'
            ]
        );

        $mockTenantDbConfigProvider->expects($this->once())
            ->method('getTenantConnectionConfig')
            ->with($testDbIndex)
            ->willReturn($mockDbConfig);
        $mockTenantConnection = $this->createMock(TenantConnection::class);
        $mockTenantConnection->expects($this->once())
            ->method('switchConnection');
        $mockDoctrine = $this->createMock(ManagerRegistry::class);
        $mockDoctrine->expects($this->once())
            ->method('getConnection')
            ->with('tenant')
            ->willReturn($mockTenantConnection);
        $mockContainer->expects($this->once())
            ->method('get')
            ->with('doctrine')
            ->willReturn($mockDoctrine);

        $mockTenantEntityManager->expects($this->once())
            ->method('clear');

        // trigger the event and test the result
        $listener->onHakamMultiTenancyBundleEventSwitchDbEvent($testEvent);
    }
}

class DbConfig implements TenantDbConfigurationInterface
{
    private string $dbName;
    private string $dbUsername;
    private ?string $dbPassword;
    private ?string $dbPort;
    private ?string $dbHost;

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $dbName): void
    {
        $this->dbName = $dbName;
    }

    public function getDbUsername(): string
    {
        return $this->dbUsername;
    }

    public function setDbUsername(string $dbUsername): void
    {
        $this->dbUsername = $dbUsername;
    }

    public function getDbPassword(): ?string
    {
        return $this->dbPassword;
    }

    public function setDbPassword(?string $dbPassword): void
    {
        $this->dbPassword = $dbPassword;
    }

    public function getId(): ?int
    {
        return 1;
    }

    public function getDatabaseStatus(): DatabaseStatusEnum
    {
        return DatabaseStatusEnum::DATABASE_CREATED;
    }

    public function setDatabaseStatus(DatabaseStatusEnum $databaseStatus): TenantDbConfigurationInterface
    {
        return $this;
    }


    /**
     * Get the value of dbPort
     */
    public function getDbPort(): null|string
    {
        return $this->dbPort;
    }

    /**
     * Set the value of dbPort
     *
     * @return  self
     */
    public function setDbPort($dbPort)
    {
        $this->dbPort = $dbPort;

        return $this;
    }

    /**
     * Get the value of dbHost
     */
    public function getDbHost(): null|string
    {
        return $this->dbHost;
    }

    /**
     * Set the value of dbHost
     *
     * @return  self
     */
    public function setDbHost($dbHost)
    {
        $this->dbHost = $dbHost;

        return $this;
    }

    public function getDsnUrl(): string
    {
        $dbHost = $this->getDbHost() ?: '127.0.0.1';
        $dbPort = $this->getDbPort() ?: '3306';
        $dbUsername = $this->getDbUsername();
        $dbPassword = $this->getDbPassword() ? ':' . $this->getDbPassword() : '';
        return sprintf('mysql://%s%s@%s:%s', $dbUsername, $dbPassword, $dbHost, $dbPort);
    }
}
