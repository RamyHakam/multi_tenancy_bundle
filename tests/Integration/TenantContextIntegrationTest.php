<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Context\TenantContext;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TenantContextIntegrationTest extends IntegrationTestCase
{
    public function testTenantContextIsNullBeforeAnySwitch(): void
    {
        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);

        $this->assertNull($context->getTenantId());
    }

    public function testTenantContextReceivesTenantIdAfterSwitch(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'context_test_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);

        $this->assertSame((string) $tenant->getId(), $context->getTenantId());
    }

    public function testTenantContextUpdatesOnSecondSwitch(): void
    {
        $tenantA = $this->insertTenantConfig(
            dbName: 'context_a_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'context_b_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));

        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);

        $this->assertSame((string) $tenantB->getId(), $context->getTenantId());
    }

    public function testTenantContextResetClearsTenantId(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'context_reset_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertNotNull($context->getTenantId());

        $context->reset();

        $this->assertNull($context->getTenantId());
    }

    public function testTenantContextIsRepopulatedWhenTheSameTenantIsSwitchedToAfterAContainerReset(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'context_reset_reswitch_db',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        /** @var TenantContext $context */
        $context = $this->getContainer()->get(TenantContextInterface::class);
        $this->assertSame((string) $tenant->getId(), $context->getTenantId());

        // What a messenger worker does between two messages, and what
        // FrankenPHP worker mode does between two requests.
        $this->getContainer()->get('services_resetter')->reset();
        $this->assertNull($context->getTenantId());

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenant->getId()));

        // DbSwitchEventListener skips the switch when it is already on the
        // requested tenant, and TenantSwitchedEvent — the only thing that
        // populates TenantContext — is dispatched from that same path. Unless
        // the listener's memory is dropped by the reset too, the context stays
        // null here while the connection is on that tenant.
        $this->assertSame((string) $tenant->getId(), $context->getTenantId());
    }

    public function testTenantSwitchedEventCarriesPreviousTenantInfo(): void
    {
        $tenantA = $this->insertTenantConfig(
            dbName: 'context_prev_a',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $tenantB = $this->insertTenantConfig(
            dbName: 'context_prev_b',
            status: DatabaseStatusEnum::DATABASE_MIGRATED,
            driver: DriverTypeEnum::SQLITE,
        );

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $capturedEvents = [];
        $dispatcher->addListener(TenantSwitchedEvent::class, function (TenantSwitchedEvent $event) use (&$capturedEvents) {
            $capturedEvents[] = $event;
        });

        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantA->getId()));
        $dispatcher->dispatch(new SwitchDbEvent((string) $tenantB->getId()));

        $this->assertCount(2, $capturedEvents);

        // First switch: no previous tenant
        $this->assertFalse($capturedEvents[0]->hadPreviousTenant());
        $this->assertNull($capturedEvents[0]->getPreviousTenantIdentifier());

        // Second switch: previous tenant is A
        $this->assertTrue($capturedEvents[1]->hadPreviousTenant());
        $this->assertSame((string) $tenantA->getId(), $capturedEvents[1]->getPreviousTenantIdentifier());
        $this->assertSame('context_prev_a', $capturedEvents[1]->getPreviousDatabaseName());
    }
}
