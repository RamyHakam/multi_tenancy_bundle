---
id: customization
title: Custom TenantConfigProvider & TenantDatabaseManager
sidebar\_label: Customization
description: Learn how to override the default Doctrine-based provider and manager with your own logic
---

# 🔧 Customizing Tenant Configuration & Management

Starting with version `3.0.0`, the Multi-Tenancy Bundle supports **plug-and-play customization** for the core tenant configuration and database management logic.

By default, the bundle uses Doctrine for:

* Storing tenant DB configurations in an entity.
* Managing databases (e.g., creation, status updates) via Doctrine DBAL.

But this is **fully overrideable** with your own custom implementations.

---

## 🥉 Why Override?

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

This allows maximum flexibility for integrating your own infrastructure and security model while still using the bundle’s powerful multi-tenancy engine.

---

## ✅ How It Works

The bundle uses the following **interfaces** internally:

* `TenantConfigProviderInterface` — returns `TenantConnectionConfigDTO` for a given tenant.
* `TenantDatabaseManagerInterface` — responsible for creating, listing, and updating tenant DBs.

The default implementation uses Doctrine, but you can override it by implementing your own services and using:

```php
#[AsAlias(TenantConfigProviderInterface::class)]
class MyCustomConfigProvider implements TenantConfigProviderInterface
{
    // your implementation
}
```

```php
#[AsAlias(TenantDatabaseManagerInterface::class)]
class MyCustomDatabaseManager implements TenantDatabaseManagerInterface
{
    // your implementation
}
```

> ✅ No need to use `services.yaml` — just use `#[AsAlias(...)]` and your class will be auto-wired as a replacement.

---

## 💡 Example: Config from ENV or Secrets Manager

```php
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Config\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(TenantConfigProviderInterface::class)]
class EnvTenantConfigProvider implements TenantConfigProviderInterface
{
    public function getTenantConnectionConfig(?string $identifier): TenantConnectionConfigDTO
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

## 💡 Example: Dummy Manager (No Doctrine)

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

    public function updateTenantDatabaseStatus(string $identifier, DatabaseStatusEnum $status): bool
    {
        return true;
    }
}
```

---

## 🥪 Use with Custom Security

By combining this with custom tenant resolvers or identity mapping, you can securely:

* Retrieve DB credentials from secrets
* Auto-provision on signup
* Rotate credentials dynamically
* Disable tenants without deleting data

---

## 🧵 Summary

You can now fully override the tenant data source and management layer by swapping two services.

This keeps the bundle **flexible**, **lightweight**, and **agnostic** to your infrastructure — while still offering a powerful and scalable multi-tenant foundation.
