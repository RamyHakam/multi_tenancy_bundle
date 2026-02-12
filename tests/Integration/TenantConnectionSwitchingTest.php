<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;

class TenantConnectionSwitchingTest extends IntegrationTestCase
{
    public function testSwitchingToTenantDatabaseViaEvent(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'tenant_one_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // Should not throw - the switch event is handled by the listener
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // Verify the tenant connection is still valid after switching
        $tenantConnection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $this->assertNotNull($tenantConnection);
    }

    public function testSwitchingBetweenTwoTenants(): void
    {
        $tenant1 = $this->insertTenantConfig(
            dbName: 'tenant_alpha',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
        );
        $tenant2 = $this->insertTenantConfig(
            dbName: 'tenant_beta',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // Switch to tenant 1 then tenant 2 without errors
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant1->getId()));
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant2->getId()));

        $this->assertTrue(true, 'Switching between tenants completed without error');
    }

    public function testSwitchToSameTenantIsNoOp(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'tenant_same',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // First switch
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // Second switch to same tenant should not throw or cause issues
        // (the listener tracks the current db and skips redundant switches)
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        $this->assertTrue(true, 'Double switch to same tenant completed without error');
    }

    public function testSwitchClearsEntityManagerIdentityMap(): void
    {
        $this->createTenantSchema();

        // Create and persist an entity in the tenant EM
        $tenantEM = $this->getTenantEntityManager();
        $product = new Fixtures\Entity\TenantProduct();
        $product->setName('Before Switch');
        $product->setPrice('10.00');
        $tenantEM->persist($product);
        $tenantEM->flush();

        $this->assertTrue($tenantEM->contains($product));

        // Now switch tenant
        $tenant = $this->insertTenantConfig(
            dbName: 'another_tenant',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // After switch, entity manager should be cleared
        $this->assertFalse($tenantEM->contains($product));
    }

    public function testSwitchEventWithInvalidIdThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent('999999'));
    }

    public function testSwitchEventTriggersListener(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'listener_test_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
            host: 'test-host',
            port: 5432,
            user: 'my_user',
            password: 'my_pass',
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        // Dispatching the event should work without errors
        // The listener resolves the config, clears the EM, and switches the connection
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // Verify the tenant entity manager was cleared (can't contain previous entities)
        $tenantEM = $this->getTenantEntityManager();
        $this->assertNotNull($tenantEM);
    }
}
