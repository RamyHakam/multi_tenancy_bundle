<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\EventListener;

use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
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
        $mockDbConfigService = $this->createMock(DbConfigService::class);

        // create a test instance of the listener
        $listener = new DbSwitchEventListener($mockContainer, $mockDbConfigService, 'test_database_url');

        // create a test event
        $testDbIndex = 1;
        $testEvent = new SwitchDbEvent($testDbIndex);

        // mock the expected behavior of the DbConfigService and ContainerInterface
        $mockDbConfig = new DbConfig();
        $mockDbConfig->setDbName('test_db_name');
        $mockDbConfig->setDbUsername('test_username');
        $mockDbConfig->setDbPassword('test_password');
        $mockDbConfigService->expects($this->once())
            ->method('findDbConfig')
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

        // trigger the event and test the result
        $listener->onHakamMultiTenancyBundleEventSwitchDbEvent($testEvent);
    }
}

class DbConfig implements TenantDbConfigurationInterface
{
    private string $dbName;
    private string $dbUsername;
    private ?string $dbPassword;

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
}
