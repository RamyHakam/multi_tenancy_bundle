<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Services\TenantFixtureLoader;

class FixtureTaggingPassIntegrationTest extends IntegrationTestCase
{
    public function testTenantFixtureLoaderServiceExists(): void
    {
        $loader = $this->getContainer()->get('hakam_tenant_fixtures_loader.service');
        $this->assertInstanceOf(TenantFixtureLoader::class, $loader);
    }

    public function testTenantFixtureLoaderReturnsIterable(): void
    {
        /** @var TenantFixtureLoader $loader */
        $loader = $this->getContainer()->get('hakam_tenant_fixtures_loader.service');
        $fixtures = $loader->getFixtures();
        $this->assertIsIterable($fixtures);
    }

    public function testPurgerFactoryClassExists(): void
    {
        $purger = new \Hakam\MultiTenancyBundle\Purger\TenantORMPurgerFactory();
        $this->assertInstanceOf(\Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory::class, $purger);
    }
}
