<?php

/**
 * Example 7: Lifecycle Events
 *
 * The bundle dispatches events at key points in the tenant lifecycle.
 * Use #[AsEventListener] attributes to hook in your own business logic.
 *
 * Events:
 * - SwitchDbEvent          → triggers a database switch
 * - TenantSwitchedEvent    → fires AFTER a successful switch
 * - TenantCreatedEvent     → fires AFTER a database is created
 * - TenantMigratedEvent    → fires AFTER migrations run (init or update)
 * - TenantBootstrappedEvent→ fires AFTER fixtures are loaded
 * - TenantDeletedEvent     → fires AFTER a database is dropped
 */

namespace App\EventListener;

use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Hakam\MultiTenancyBundle\Event\TenantDeletedEvent;
use Hakam\MultiTenancyBundle\Event\TenantMigratedEvent;
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class TenantLifecycleListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fires after tenant:database:create successfully creates a database.
     */
    #[AsEventListener(event: TenantCreatedEvent::class)]
    public function onTenantCreated(TenantCreatedEvent $event): void
    {
        $this->logger->info('Tenant database created', [
            'tenant_id' => $event->getTenantIdentifier(),
            'database' => $event->getDatabaseName(),
            'occurred_at' => $event->getOccurredAt()->format('c'),
        ]);

        // Example: send welcome email, create billing subscription, etc.
    }

    /**
     * Fires after tenant:migrations:migrate completes.
     * Check migrationType to distinguish init vs update.
     */
    #[AsEventListener(event: TenantMigratedEvent::class)]
    public function onTenantMigrated(TenantMigratedEvent $event): void
    {
        if ($event->isInitialMigration()) {
            $this->logger->info('Tenant schema initialized', [
                'tenant_id' => $event->getTenantIdentifier(),
                'version' => $event->getToVersion(),
            ]);
        }

        if ($event->isUpdateMigration()) {
            $this->logger->info('Tenant schema updated', [
                'tenant_id' => $event->getTenantIdentifier(),
                'version' => $event->getToVersion(),
            ]);

            // Example: warm caches, notify tenant about maintenance, etc.
        }
    }

    /**
     * Fires after tenant:fixtures:load completes.
     */
    #[AsEventListener(event: TenantBootstrappedEvent::class)]
    public function onTenantBootstrapped(TenantBootstrappedEvent $event): void
    {
        $this->logger->info('Tenant fixtures loaded', [
            'tenant_id' => $event->getTenantIdentifier(),
            'fixtures' => $event->getLoadedFixtures(),
        ]);

        // Example: mark tenant as "ready" in external system
    }

    /**
     * Fires every time the active tenant changes.
     * Useful for per-request tracking and audit logging.
     */
    #[AsEventListener(event: TenantSwitchedEvent::class)]
    public function onTenantSwitched(TenantSwitchedEvent $event): void
    {
        $this->logger->debug('Tenant switched', [
            'from' => $event->getPreviousTenantIdentifier(),
            'to' => $event->getTenantIdentifier(),
            'from_db' => $event->getPreviousDatabaseName(),
            'to_db' => $event->getTenantConfig()->dbname,
            'had_previous' => $event->hadPreviousTenant(),
        ]);
    }

    /**
     * Fires after a tenant database is dropped.
     */
    #[AsEventListener(event: TenantDeletedEvent::class)]
    public function onTenantDeleted(TenantDeletedEvent $event): void
    {
        $this->logger->warning('Tenant database deleted', [
            'tenant_id' => $event->getTenantIdentifier(),
            'database' => $event->getDatabaseName(),
        ]);

        // Example: cancel billing, archive data, clean up external resources
    }
}
