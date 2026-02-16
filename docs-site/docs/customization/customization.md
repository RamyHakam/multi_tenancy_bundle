---
id: customization
title: Custom TenantConfigProvider & TenantDatabaseManager
sidebar\_label: Customization
description: Learn how to override the default Doctrine-based provider and manager with your own logic
---

# Customizing Tenant Configuration & Management

Starting with version `3.0.0`, the Multi-Tenancy Bundle supports **plug-and-play customization** for the core tenant configuration and database management logic.

By default, the bundle uses Doctrine for:

* Storing tenant DB configurations in an entity.
* Managing databases (e.g., creation, status updates) via Doctrine DBAL.

But this is **fully overrideable** with your own custom implementations.

---

## Why Override?

Overriding is useful when:

* You store **tenant configuration outside Doctrine**, for example in:

    * Redis
    * Flat files
    * Environment variables
    * Secret management tools (e.g., Infisical, Vault, AWS Secrets Manager)
* You want to implement **custom DB credential handling**, such as:

    * Obfuscated or hashed passwords
    * Rotating credentials
    * Centralized credential storage
* You need to fetch configurations from **external APIs** or config servers.
* You want to decouple tenant database management from Doctrine (e.g., serverless environments or multi-region DBs).
* You want to support **dynamic tenant creation workflows** (e.g., provisioning on signup).

This allows maximum flexibility for integrating your own infrastructure and security model while still using the bundle's powerful multi-tenancy engine.

---

## How It Works

The bundle uses the following **interfaces** internally:

* `TenantConfigProviderInterface` — returns `TenantConnectionConfigDTO` for a given tenant.
* `TenantDatabaseManagerInterface` — responsible for creating, listing, and updating tenant DBs.

There are **two approaches** to override these services:

### Approach 1: `#[AsAlias]` Attribute (Recommended)

This is the idiomatic Symfony way. Simply implement the interface and annotate your class with `#[AsAlias]`. Symfony's autoconfigure will automatically replace the default service.

Works for **both** `TenantConfigProviderInterface` and `TenantDatabaseManagerInterface`.

```php
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantConfigProviderInterface::class)]
class MyCustomConfigProvider implements TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        // your custom logic
    }
}
```

```php
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantDatabaseManagerInterface::class)]
class MyCustomDatabaseManager implements TenantDatabaseManagerInterface
{
    // your implementation
}
```

> No need to touch `services.yaml` — just use `#[AsAlias(...)]` and your class will be auto-wired as a replacement.

### Approach 2: Config-based Override (Provider Only)

For the `TenantConfigProviderInterface`, you can also use the bundle configuration to point to a custom service ID:

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    tenant_config_provider: App\Service\MyCustomConfigProvider
    # ...
```

When set to a value other than the default `hakam_tenant_config_provider.doctrine`, the bundle will alias `TenantConfigProviderInterface` to your service. The Doctrine-specific `tenant_database_className` and `tenant_database_identifier` settings are skipped in this case.

> **Note:** Your service must be registered in the container (either via `services.yaml` or autoconfigure).

---

## Example: Config from ENV or Secrets Manager

```php
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantConfigProviderInterface::class)]
class EnvTenantConfigProvider implements TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            identifier: (int) ($_ENV['TENANT_ID'] ?? 1),
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: $_ENV['TENANT_DB_HOST'],
            port: (int) ($_ENV['TENANT_DB_PORT'] ?? 3306),
            dbname: $_ENV['TENANT_DB_NAME'],
            user: $_ENV['TENANT_DB_USER'],
            password: $_ENV['TENANT_DB_PASSWORD']
        );
    }
}
```

---

## Example: Dummy Manager (No Doctrine)

```php
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantDatabaseManagerInterface::class)]
class DummyTenantDatabaseManager implements TenantDatabaseManagerInterface
{
    public function listDatabases(): array
    {
        return [
            TenantConnectionConfigDTO::fromArgs(
                identifier: 1,
                driver: DriverTypeEnum::MYSQL,
                dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
                host: 'dummy',
                port: 3306,
                dbname: 'tenant1',
                user: 'user',
                password: 'pass'
            ),
        ];
    }

    public function listMissingDatabases(): array
    {
        return [];
    }

    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO
    {
        return $this->listDatabases()[0];
    }

    public function createTenantDatabase(TenantConnectionConfigDTO $dto): bool
    {
        return true; // or call external API to provision the DB
    }

    public function getTenantDatabaseById(mixed $identifier): TenantConnectionConfigDTO
    {
        return $this->listDatabases()[0];
    }

    public function getTenantDbListByDatabaseStatus(DatabaseStatusEnum $status): array
    {
        return [];
    }

    public function addNewTenantDbConfig(TenantConnectionConfigDTO $dto): TenantConnectionConfigDTO
    {
        return $dto;
    }

    public function updateTenantDatabaseStatus(mixed $identifier, DatabaseStatusEnum $status): bool
    {
        return true;
    }
}
```

---

## Use with Custom Security

By combining this with custom tenant resolvers or identity mapping, you can securely:

* Retrieve DB credentials from secrets
* Auto-provision on signup
* Rotate credentials dynamically
* Disable tenants without deleting data

---

## Summary

You can fully override the tenant data source and management layer by swapping two services.

| Interface | `#[AsAlias]` | Config-based |
|---|---|---|
| `TenantConfigProviderInterface` | Supported | `tenant_config_provider: your.service.id` |
| `TenantDatabaseManagerInterface` | Supported | N/A |

This keeps the bundle **flexible**, **lightweight**, and **agnostic** to your infrastructure — while still offering a powerful and scalable multi-tenant foundation.
