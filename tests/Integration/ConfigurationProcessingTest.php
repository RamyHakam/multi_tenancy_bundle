<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantDbConfig;

class ConfigurationProcessingTest extends IntegrationTestCase
{
    public function testTenantDbCredentialsParameterIsSet(): void
    {
        $credentials = $this->getContainer()->getParameter('hakam.tenant_db_credentials');
        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('db_url', $credentials);
    }

    public function testTenantDbListEntityParameterIsSet(): void
    {
        $entity = $this->getContainer()->getParameter('hakam.tenant_db_list_entity');
        $this->assertSame(TenantDbConfig::class, $entity);
    }

    public function testTenantDbIdentifierParameterIsSet(): void
    {
        $identifier = $this->getContainer()->getParameter('hakam.tenant_db_identifier');
        $this->assertSame('id', $identifier);
    }

    public function testPrependCreatesTenantDoctrineConnection(): void
    {
        $connection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $this->assertInstanceOf(TenantConnection::class, $connection);
    }

    public function testPrependCreatesTenantEntityManager(): void
    {
        $em = $this->getContainer()->get('doctrine')->getManager('tenant');
        $this->assertNotNull($em);
    }

    public function testPrependSetsTenantMigrationParameter(): void
    {
        $migrationConfig = $this->getContainer()->getParameter('tenant_doctrine_migration');
        $this->assertIsArray($migrationConfig);
        $this->assertArrayHasKey('migrations_paths', $migrationConfig);
    }

    public function testTenantConnectionUsesCorrectDriver(): void
    {
        $connection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $params = $connection->getParams();
        $this->assertSame('pdo_sqlite', $params['driver']);
    }

    public function testCustomTenantIdentifierConfiguration(): void
    {
        // Default identifier is 'id'
        $identifier = $this->getContainer()->getParameter('hakam.tenant_db_identifier');
        $this->assertSame('id', $identifier);
    }

    public function testDefaultEntityManagerUsesDefaultConnection(): void
    {
        $em = $this->getDefaultEntityManager();
        $connection = $em->getConnection();
        $params = $connection->getParams();
        $this->assertSame('pdo_sqlite', $params['driver']);
    }

    public function testTenantEntityManagerMappingIsConfigured(): void
    {
        $em = $this->getContainer()->get('doctrine')->getManager('tenant');
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $this->assertNotEmpty($metadata);

        $classNames = array_map(fn($m) => $m->getName(), $metadata);
        $this->assertContains(
            'Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct',
            $classNames
        );
    }
}
