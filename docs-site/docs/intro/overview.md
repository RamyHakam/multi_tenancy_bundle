---
title: Overview
---

The **Symfony Multi-Tenancy Bundle** is a battle-tested toolkit for building large-scale, production-ready multi-tenant applications on Symfony. Whether you’re running a global SaaS, a country-specific service, or a multi-vendor marketplace, this bundle gives you all the wiring you need to keep each tenant’s data completely isolated, highly performant, and easy to manage.

## Why This Bundle Matters

- **True Data Isolation**  
  Every tenant lives in its own database—no shared tables, no schema hacks—so you get rock-solid security and compliance (e.g. GDPR data residency).

- **Zero-Downtime Tenant Provisioning**  
  Programmatically spin up new tenant databases (and apply migrations) on the fly, without interrupting existing tenants.

- **Seamless Runtime Switching**  
  Dispatch a single event (`SwitchDbEvent`) and the bundle automatically reconnects the EntityManager to the correct database for the duration of your request or console command.

- **Independent Migration Pipelines**  
  Keep your main application schema and each tenant’s schema in separate migration directories. Run or roll back migrations globally or per-tenant in one command.

- **Fixture Loading per Tenant**  
  Annotate fixtures with `#[TenantFixture]` and load test or demo data on a per-tenant basis, complete with dependency ordering and groups.

- **Custom Host & Credential Resolution**  
  Point different tenants to different database hosts, servers, or credentials based on data in your `TenantDbConfig`—perfect for sharding, geo-distribution, or cloud-managed clusters.
- ## ✅ New in v3.0: Custom Provider & Manager Override

In version 3.0, you can now completely override the default `TenantConfigProviderInterface` and `TenantDatabaseManagerInterface` with your own custom implementations.

This allows you to:

- Load tenant configs from APIs, ENV variables, Redis, or secret managers
- Use custom logic to manage DB credentials (e.g. hash, encrypt, or rotate passwords)
- Integrate with external service registries or dynamic provisioning tools

To override, just use the `#[AsAlias(...)]` attribute:

```php
#[AsAlias(TenantConfigProviderInterface::class)]
class MyCustomProvider implements TenantConfigProviderInterface
{
    // your custom implementation
}
```

## Key Benefits

| Benefit                         | Impact                                                                                  |
|---------------------------------|-----------------------------------------------------------------------------------------|
| Database-per-Tenant             | Strongest possible data isolation; audit-proof separation                               |
| Runtime DB Switching            | One-line event-driven switch—no manual connection hoops                                 |
| Modular Migrations & Fixtures   | Version and seed each tenant independently, or run bulk operations across all tenants  |
| Flexible Configuration          | Per-tenant overrides for host, driver, server version, credentials                     |
| Horizontal Scalability          | Distribute tenant databases across multiple servers or regions                          |
| Compliance & GDPR-Ready         | Keep data in the right legal boundary by isolating at the database level                |

## Typical Use Cases

- **B2B SaaS**  
  Each company gets its own database. Scale hundreds—or thousands—of clients without risk of crosstalk.

- **Geo-Distributed Services**  
  Keep European customers on EU-hosted DBs, APAC on Asia-Pacific clusters, etc., to meet latency and regulatory requirements.

- **White-Label Platforms**  
  Spin up branded “mini-apps” for agencies, each with completely isolated data, theme, and configuration.

- **Multi-Vendor Marketplaces**  
  Vendors share code, but each vendor’s products, orders, and analytics live in separate data stores.

## How It Works in 3 Steps

1. **Configure Your Tenant Registry**  
   Define an entity (e.g. `TenantDbConfig`) that implements `TenantDbConfigurationInterface`. This lives in your Main DB and holds each tenant’s connection details.

2. **Scaffold Your Entities & Migrations**  
   Split your code into `src/Entity/Main/` and `src/Entity/Tenant/`, and separate migrations under `migrations/Main/` and `migrations/Tenant/`.

3. **Switch & Operate**  
   Dispatch
   ```php
   $dispatcher->dispatch(new SwitchDbEvent($tenantId));
