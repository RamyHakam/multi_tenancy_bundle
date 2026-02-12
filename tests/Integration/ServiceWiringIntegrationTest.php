<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager;
use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;

class ServiceWiringIntegrationTest extends IntegrationTestCase
{
    public function testTenantEntityManagerIsRegistered(): void
    {
        $em = $this->getContainer()->get('tenant_entity_manager');
        $this->assertInstanceOf(TenantEntityManager::class, $em);
    }

    public function testTenantEntityManagerDecoratesRealDoctrineEM(): void
    {
        $em = $this->getTenantEntityManager();
        $connection = $em->getConnection();
        $this->assertNotNull($connection);
    }

    public function testDbSwitchEventListenerIsRegistered(): void
    {
        $this->assertTrue(
            $this->getContainer()->has('Hakam\MultiTenancyBundle\EventListener\DbSwitchEventListener')
        );
    }

    public function testDoctrineTenantDatabaseManagerIsRegistered(): void
    {
        $manager = $this->getContainer()->get('Hakam\MultiTenancyBundle\Adapter\Doctrine\DoctrineTenantDatabaseManager');
        $this->assertInstanceOf(DoctrineTenantDatabaseManager::class, $manager);
        $this->assertInstanceOf(TenantDatabaseManagerInterface::class, $manager);
    }

    public function testDoctrineTenantConfigProviderIsRegistered(): void
    {
        $this->assertTrue(
            $this->getContainer()->has('hakam_tenant_config_provider.doctrine')
        );
    }

    public function testTenantDBALConnectionGeneratorIsRegistered(): void
    {
        $this->assertTrue(
            $this->getContainer()->has('Hakam\MultiTenancyBundle\Adapter\Doctrine\TenantDBALConnectionGenerator')
        );
    }

    public function testDefaultDsnGeneratorIsRegistered(): void
    {
        $this->assertTrue(
            $this->getContainer()->has('Hakam\MultiTenancyBundle\Adapter\DefaultDsnGenerator')
        );
    }

    public function testTenantFixtureLoaderIsRegistered(): void
    {
        $loader = $this->getContainer()->get('hakam_tenant_fixtures_loader.service');
        $this->assertInstanceOf(\Hakam\MultiTenancyBundle\Services\TenantFixtureLoader::class, $loader);
    }

    public function testTenantConnectionIsOfCorrectWrapperClass(): void
    {
        $connection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $this->assertInstanceOf(TenantConnection::class, $connection);
    }

    public function testCommandsAreRegistered(): void
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel);
        $this->assertTrue($application->has('tenant:database:create'));
        $this->assertTrue($application->has('tenant:migrations:migrate'));
        $this->assertTrue($application->has('tenant:fixtures:load'));
        $this->assertTrue($application->has('tenant:migrations:diff'));
    }

    public function testTenantEntityManagerConnectionIsTenantConnection(): void
    {
        $tenantEM = $this->getTenantEntityManager();
        $tenantConnection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $this->assertSame($tenantConnection, $tenantEM->getConnection());
    }
}
