---
title: "Core Concepts"
---

The **Core Concepts** of the Symfony Multi-Tenancy Bundle define how your application separates, manages, and operates on data for multiple tenants. This consolidated guide covers entity organization, database separation, runtime switching, and entity manager roles.

## 1. Tenant Entities

Tenant-specific entities must implement the `TenantEntityInterface` and reside in **`src/Entity/Tenant/`**. This signals to the bundle which objects belong to tenant databases:

```php
namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;

#[ORM\Entity]
class Order implements TenantEntityInterface
{
#[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
private int $id;
// ... other fields & methods
}
```

**Why it matters:**

* **Automatic Recognition**: The bundle uses the interface to determine when to switch EMs.
* **Clean Separation**: Keeps tenant models isolated from main application entities.

## 2. Main vs Tenant Databases

A clear separation between the **Main Database** and **Tenant Databases** is fundamental:

| Aspect          | Main DB                                     | Tenant DB (per-tenant)           |
| --------------- | ------------------------------------------- | -------------------------------- |
| Purpose         | Store application settings, tenant registry | Hold each tenant’s business data |
| Location        | Single shared database                      | One database per tenant instance |
| Migrations Path | `migrations/Main`                           | `migrations/Tenant`              |
| Entity Manager  | `default` EM                                | `TenantEntityManager`            |

**Benefits:**

* **Isolation & Security**: No shared tables means no accidental data leaks.
* **Scalability**: Scale out tenant databases independently (e.g., different servers or regions).
* **Compliance**: Store data in the correct geographic or legal boundary.

## 3. Entity Managers

The bundle introduces a dedicated `TenantEntityManager` alongside your standard `default` EM.

* **default EM**:

* Manages entities in `src/Entity/Main`
* Uses your default DBAL connection (e.g., `%DATABASE_URL%`)

* **TenantEntityManager**:

* Injected as a separate service
* Reconfigures its DBAL connection on each `SwitchDbEvent`
* Manages only tenant entities and schemas

**Example service injection:**

```php
public function __construct(
private EntityManagerInterface $defaultEm,
private TenantEntityManager $tenantEm
) {}
```

## 4. Switching Databases

To direct operations to a tenant database, dispatch the `SwitchDbEvent`:

```php
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;

// Using tenant ID:
$dispatcher->dispatch(new SwitchDbEvent($tenantId));

// Or using the TenantConfig entity:
$dispatcher->dispatch(new SwitchDbEvent($tenantConfigEntity));
```

Once dispatched, all subsequent calls to `$tenantEm->persist()`, `flush()`, or repository queries use the selected tenant’s connection.

**Under the hood:**

1. The bundle grabs the `TenantDbConfig` (host, credentials, schema) from the main DB.
2. It updates the `TenantEntityManager`’s connection parameters.
3. Clears any open connections and reconnects to the target tenant DB.

## Best Practices & Patterns

* **Minimal Switches**: Group tenant operations to reduce event dispatch overhead.
* **Service Wrappers**: Encapsulate tenant logic in services that handle switching internally.
* **Error Handling**: Catch and log connection exceptions to implement retries or fallback logic.
* **Testing**: Write integration tests that simulate multiple `SwitchDbEvent` dispatches in isolation.

With these core concepts, you’ll design and implement multi-tenant systems in Symfony with clear boundaries, robust isolation, and flexible scalability.
