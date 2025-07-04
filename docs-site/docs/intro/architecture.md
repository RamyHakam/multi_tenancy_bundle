---
title: Architecture Overview
---
```text
+----------------------+      +----------------------+
|      Main DB         |      |     Tenant DB(s)     |
| +------------------+ |      | +------------------+ |
| | TenantConfig     | |<---->| | Schema & Data     | |
| +------------------+ |      | +------------------+ |
+----------------------+      +----------------------+

EventDispatcher -> SwitchDbEvent -> TenantEntityManager switches connection
```

The Symfony Multi-Tenancy Bundle is built on a clear, event-driven architecture that keeps tenant data isolated while sharing a single application codebase.

1. **Tenant Registry & Configuration**

* The **Main Database** stores a `TenantDbConfig` entity for each tenant.
* This entity holds connection parameters (host, driver, credentials, schema name) and lifecycle timestamps.

2. **Dynamic Connection Switching**

* At runtime—whether in an HTTP request or console command—you dispatch:

```php
$dispatcher->dispatch(new SwitchDbEvent($tenantId));
```
* The bundle listens for `SwitchDbEvent`, resolves the associated `TenantDbConfig`, and reconfigures the `TenantEntityManager` connection parameters on the fly.

3. **Isolated Entity Managers**

* **Main EntityManager**: Manages your global and shared entities (e.g., tenants registry, application settings).
* **TenantEntityManager**: A separate service injected wherever you need tenant-specific operations. It automatically reconnects to the selected tenant’s database after the event is dispatched.

4. **Separate Migration & Fixture Paths**

* Migrations for the main schema and tenant schemas live in different directories (`migrations/Main` vs `migrations/Tenant`).
* Fixtures decorated with `#[TenantFixture]` only run against tenant DBs, ensuring seeding and test data remain isolated.

5. **Lifecycle & Error Handling**

* If a tenant database doesn’t exist, you can enable **on-the-fly creation**.
* Connection failures throw clear exceptions, allowing you to implement retry logic or fallback strategies.

## How to Use This Architecture

1. **Define Your TenantConfig**
Create an entity in `src/Entity/Main` implementing `TenantDbConfigurationInterface` (use the provided trait for convenience).

2. **Dispatch the Switch Event**
In controllers or services, dispatch `SwitchDbEvent` before any tenant operations:

```php
public function index(EventDispatcherInterface $dispatcher, TenantEntityManager $tem)
{
$dispatcher->dispatch(new SwitchDbEvent($tenantId));
$user = (new User())->setName('Demo');
$tem->persist($user);
$tem->flush();
}
```

3. **Manage Migrations & Fixtures**

* Generate tenant migrations: `php bin/console tenant:migration:diff`

* Apply them: `php bin/console tenant:migration:migrate update`

* Load fixtures: `php bin/console tenant:fixtures:load --append`

With this architecture, you maintain a single codebase but fully isolated data layers for each tenant, combining the best of multi-tenancy and Symfony’s power.
