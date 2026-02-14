---
id: lifecycle-events
title: Tenant Lifecycle Events
sidebar\_label: Lifecycle Events
description: Hook into tenant lifecycle operations with events for billing, monitoring, and external integrations
---

# 🎯 Tenant Lifecycle Events

The Multi-Tenancy Bundle provides a comprehensive event system that fires during key tenant lifecycle operations. This enables extensibility and integration with external services without modifying the bundle's core code.

---

## 📋 Overview

All tenant events extend the `AbstractTenantEvent` base class and provide:

* **Tenant Identifier**: The unique identifier of the affected tenant
* **Tenant Configuration**: The `TenantConnectionConfigDTO` when available
* **Timestamp**: When the event occurred (`DateTimeImmutable`)

---

## 🚀 Available Events

### TenantCreatedEvent

Dispatched when a new tenant database is created.

**Fired from**: `CreateDatabaseCommand` after successful database creation

**Properties**:
* `getTenantIdentifier()`: The tenant's unique identifier
* `getTenantConfig()`: The tenant connection configuration DTO
* `getDatabaseName()`: Name of the created database
* `getOccurredAt()`: Timestamp of the event

**Use Cases**:
* Trigger billing/subscription setup
* Send welcome notifications
* Initialize external services for the tenant
* Log tenant creation for auditing

```php
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TenantCreatedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [TenantCreatedEvent::class => 'onTenantCreated'];
    }

    public function onTenantCreated(TenantCreatedEvent $event): void
    {
        $tenantId = $event->getTenantIdentifier();
        $dbName = $event->getDatabaseName();
        
        // Initialize billing for the new tenant
        $this->billingService->createSubscription($tenantId);
        
        // Send welcome email
        $this->mailer->sendWelcomeEmail($tenantId);
    }
}
```

---

### TenantDeletedEvent

Dispatched when a tenant database is dropped/deleted.

**Fired from**: `DbService::dropDatabase()` after successful database deletion

**Properties**:
* `getTenantIdentifier()`: The tenant's unique identifier
* `getDatabaseName()`: Name of the deleted database
* `getOccurredAt()`: Timestamp of the event

**Use Cases**:
* Clean up external resources
* Cancel billing/subscriptions
* Archive tenant data
* Send deletion notifications
* Update monitoring/logging systems

```php
use Hakam\MultiTenancyBundle\Event\TenantDeletedEvent;

class TenantDeletedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [TenantDeletedEvent::class => 'onTenantDeleted'];
    }

    public function onTenantDeleted(TenantDeletedEvent $event): void
    {
        $tenantId = $event->getTenantIdentifier();
        
        // Cancel subscription
        $this->billingService->cancelSubscription($tenantId);
        
        // Clean up external storage
        $this->storageService->deleteTenantFiles($tenantId);
    }
}
```

---

### TenantMigratedEvent

Dispatched when migrations have been executed on a tenant database.

**Fired from**: `MigrateCommand` after successful migration execution

**Properties**:
* `getTenantIdentifier()`: The tenant's unique identifier
* `getTenantConfig()`: The tenant connection configuration DTO
* `getMigrationType()`: Either `'init'` or `'update'`
* `isInitialMigration()`: Returns `true` if this is the first migration
* `isUpdateMigration()`: Returns `true` if this is an update migration
* `getToVersion()`: Target migration version (if specified)
* `getOccurredAt()`: Timestamp of the event

**Constants**:
* `TenantMigratedEvent::TYPE_INIT` = `'init'`
* `TenantMigratedEvent::TYPE_UPDATE` = `'update'`

**Use Cases**:
* Log migration history
* Trigger post-migration tasks (cache warming, etc.)
* Notify administrators of schema changes
* Update tenant status in external systems

```php
use Hakam\MultiTenancyBundle\Event\TenantMigratedEvent;

class TenantMigratedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [TenantMigratedEvent::class => 'onTenantMigrated'];
    }

    public function onTenantMigrated(TenantMigratedEvent $event): void
    {
        if ($event->isInitialMigration()) {
            $this->logger->info('Tenant database initialized', [
                'tenant' => $event->getTenantIdentifier(),
                'version' => $event->getToVersion(),
            ]);
        }
        
        // Clear cached schema information
        $this->cacheService->clearTenantSchemaCache($event->getTenantIdentifier());
    }
}
```

---

### TenantBootstrappedEvent

Dispatched when a tenant database has been bootstrapped with initial fixture data.

**Fired from**: `LoadTenantFixtureCommand` after successful fixture loading

**Properties**:
* `getTenantIdentifier()`: The tenant's unique identifier
* `getTenantConfig()`: The tenant connection configuration DTO (may be null)
* `getLoadedFixtures()`: Array of loaded fixture class names
* `getOccurredAt()`: Timestamp of the event

**Use Cases**:
* Notify tenant that environment is ready
* Trigger post-setup tasks
* Initialize default configurations
* Send onboarding emails

```php
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;

class TenantBootstrappedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [TenantBootstrappedEvent::class => 'onTenantBootstrapped'];
    }

    public function onTenantBootstrapped(TenantBootstrappedEvent $event): void
    {
        $tenantId = $event->getTenantIdentifier();
        $fixtures = $event->getLoadedFixtures();
        
        $this->logger->info('Tenant environment ready', [
            'tenant' => $tenantId,
            'fixtures_loaded' => count($fixtures),
        ]);
        
        // Send "Your account is ready" notification
        $this->notificationService->sendTenantReadyNotification($tenantId);
    }
}
```

---

### TenantSwitchedEvent

Dispatched when the active tenant database connection is switched.

**Fired from**: `DbSwitchEventListener` after successful connection switch

**Properties**:
* `getTenantIdentifier()`: The new tenant's identifier
* `getTenantConfig()`: The new tenant's connection configuration DTO
* `getPreviousTenantIdentifier()`: Previous tenant identifier (or null)
* `getPreviousDatabaseName()`: Previous database name (or null)
* `hadPreviousTenant()`: Returns `true` if there was a previous tenant
* `getOccurredAt()`: Timestamp of the event

**Use Cases**:
* Track tenant access for analytics
* Update request context
* Clear tenant-specific caches
* Log tenant access for auditing/security

```php
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;

class TenantSwitchedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [TenantSwitchedEvent::class => 'onTenantSwitched'];
    }

    public function onTenantSwitched(TenantSwitchedEvent $event): void
    {
        // Log tenant access
        $this->analyticsService->trackTenantAccess($event->getTenantIdentifier());
        
        // Clear previous tenant's cache if switching
        if ($event->hadPreviousTenant()) {
            $this->cacheService->clearTenantCache($event->getPreviousTenantIdentifier());
        }
    }
}
```

---

## 💡 Complete Example: Multi-Purpose Subscriber

Here's a complete example of a subscriber that handles all tenant lifecycle events:

```php
<?php

namespace App\EventSubscriber;

use Hakam\MultiTenancyBundle\Event\AbstractTenantEvent;
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Hakam\MultiTenancyBundle\Event\TenantDeletedEvent;
use Hakam\MultiTenancyBundle\Event\TenantMigratedEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TenantLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BillingServiceInterface $billingService,
        private readonly NotificationServiceInterface $notificationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TenantCreatedEvent::class => ['onTenantCreated', 0],
            TenantDeletedEvent::class => ['onTenantDeleted', 0],
            TenantMigratedEvent::class => ['onTenantMigrated', 0],
            TenantBootstrappedEvent::class => ['onTenantBootstrapped', 0],
            TenantSwitchedEvent::class => ['onTenantSwitched', -10],
        ];
    }

    public function onTenantCreated(TenantCreatedEvent $event): void
    {
        $this->log('tenant.created', $event, [
            'database_name' => $event->getDatabaseName(),
        ]);

        $this->billingService->createSubscription(
            $event->getTenantIdentifier(),
            $event->getTenantConfig()
        );
    }

    public function onTenantDeleted(TenantDeletedEvent $event): void
    {
        $this->log('tenant.deleted', $event, [
            'database_name' => $event->getDatabaseName(),
        ]);

        $this->billingService->cancelSubscription($event->getTenantIdentifier());
    }

    public function onTenantMigrated(TenantMigratedEvent $event): void
    {
        $this->log('tenant.migrated', $event, [
            'migration_type' => $event->getMigrationType(),
            'version' => $event->getToVersion(),
        ]);

        if ($event->isInitialMigration()) {
            $this->notificationService->notifyTenantReady($event->getTenantIdentifier());
        }
    }

    public function onTenantBootstrapped(TenantBootstrappedEvent $event): void
    {
        $this->log('tenant.bootstrapped', $event, [
            'fixtures_count' => count($event->getLoadedFixtures()),
        ]);
    }

    public function onTenantSwitched(TenantSwitchedEvent $event): void
    {
        if ($event->hadPreviousTenant()) {
            $this->log('tenant.switched', $event, [
                'from_tenant' => $event->getPreviousTenantIdentifier(),
            ]);
        }
    }

    private function log(string $eventName, AbstractTenantEvent $event, array $extra = []): void
    {
        $this->logger->info("Tenant lifecycle: {$eventName}", array_merge([
            'tenant_identifier' => $event->getTenantIdentifier(),
            'occurred_at' => $event->getOccurredAt()->format('c'),
        ], $extra));
    }
}
```

---

## 📊 Event Execution Order

The events fire in the following logical order during tenant setup:

1. **TenantCreatedEvent** — Database created
2. **TenantMigratedEvent** (type: init) — Initial schema applied
3. **TenantBootstrappedEvent** — Fixtures loaded
4. **TenantSwitchedEvent** — Every time the connection switches

For updates:
* **TenantMigratedEvent** (type: update) — Schema updated

For teardown:
* **TenantDeletedEvent** — Database dropped

---

## ⚡ Performance Considerations

* Events are dispatched **synchronously** by default
* Keep event listeners lightweight to avoid impacting command execution
* For heavy processing, consider dispatching async messages from your listeners
* The `TenantSwitchedEvent` fires on every context switch — use it judiciously

---

## ✅ Registering Subscribers

Subscribers are automatically registered when using Symfony's autoconfigure:

```yaml
# config/services.yaml
services:
    _defaults:
        autoconfigure: true
    
    App\EventSubscriber\:
        resource: '../src/EventSubscriber/'
```

Or explicitly:

```yaml
services:
    App\EventSubscriber\TenantLifecycleSubscriber:
        tags: ['kernel.event_subscriber']
```

---

## 🔄 Backward Compatibility

This event system is fully backward compatible. Existing applications that don't subscribe to these events will continue to work without any changes.
