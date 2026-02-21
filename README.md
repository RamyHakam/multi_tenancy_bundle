# Symfony Multi-Tenancy Bundle

![Multi-Tenancy Bundle (Desktop Wallpaper)](https://github.com/RamyHakam/multi_tenancy_bundle/assets/17661342/eef23e6a-881c-4817-b7b8-8b7cec913154)

![Action Status](https://github.com/RamyHakam/multi_tenancy_bundle/workflows/action_status/badge.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/hakam/multi-tenancy-bundle?style=flat-square)](https://packagist.org/packages/hakam/multi-tenancy-bundle)
[![Symfony Flex](https://img.shields.io/badge/Symfony%20Flex-Recipe%20Available-brightgreen.svg?style=flat-square)](https://github.com/symfony/recipes-contrib)
[![codecov](https://codecov.io/gh/RamyHakam/multi_tenancy_bundle/branch/master/graph/badge.svg)](https://codecov.io/gh/RamyHakam/multi_tenancy_bundle)


> 📚 **Full Documentation**: [Documentation](https://ramyhakam.github.io/multi_tenancy_bundle/intro/overview)

---

## 🧩 Overview

The **Symfony Multi-Tenancy Bundle** enables scalable, production-ready multi-tenancy for Symfony applications.

Ideal for **SaaS platforms**, **region-based services**, and **multi-vendor e-commerce systems**, this bundle is built around a **database-per-tenant architecture**, giving each tenant:

* A fully isolated database
* Independent schema and migrations
* Configurable connection parameters (host, driver, credentials)

It integrates seamlessly with Doctrine and Symfony's service container, offering:

* Automatic tenant database switching at runtime via `SwitchDbEvent`
* **Automatic tenant resolution** from HTTP requests (subdomain, path, header, or custom)
* Separate migration and fixture paths for main vs. tenant databases
* Dedicated `TenantEntityManager` service for runtime isolation

For full usage examples and advanced configuration, see the [documentation](https://ramyhakam.github.io/multi_tenancy_bundle/).

---

## 🔄 Automatic Tenant Resolution (v3.0.0+)

Automatically resolve the current tenant from incoming HTTP requests — no manual event dispatching required.

### Supported Strategies

| Strategy | Example | Description |
|----------|---------|-------------|
| `subdomain` | `tenant1.example.com` | Extracts tenant from subdomain |
| `path` | `/tenant1/dashboard` | Extracts tenant from URL path segment |
| `header` | `X-Tenant-ID: tenant1` | Extracts tenant from HTTP header |
| `host` | `client.com → tenant1` | Maps full hostname to tenant |
| `chain` | Multiple strategies | Tries resolvers in order with fallback |

### Quick Configuration

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: subdomain  # subdomain | path | header | host | chain
        options:
            base_domain: 'example.com'  # for subdomain strategy
```

### Strategy Examples

**Subdomain-based** (e.g., `acme.myapp.com`):
```yaml
resolver:
    enabled: true
    strategy: subdomain
    options:
        base_domain: 'myapp.com'
```

**Header-based** (for APIs):
```yaml
resolver:
    enabled: true
    strategy: header
    options:
        header_name: 'X-Tenant-ID'
```

**Path-based** (e.g., `/acme/dashboard`):
```yaml
resolver:
    enabled: true
    strategy: path
    excluded_paths: ['/api/public', '/health']
```

**Chain strategy** (fallback support):
```yaml
resolver:
    enabled: true
    strategy: chain
    options:
        chain_order: [header, subdomain, path]
```

### Accessing Resolved Tenant

The resolved tenant ID is available in request attributes:

```php
// In a controller
$tenantId = $request->attributes->get('_tenant');
```

### Custom Resolver

Implement `TenantResolverInterface` for custom logic:

```php
use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;

class CustomResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        // Your custom logic
        return $request->cookies->get('tenant_id');
    }

    public function supports(Request $request): bool
    {
        return $request->cookies->has('tenant_id');
    }
}
```

> **Note:** Automatic resolution is disabled by default for backward compatibility. Manual `SwitchDbEvent` dispatching continues to work.

---

## 🚀 Quick Installation

### 1. Via Symfony Flex (Recommended)

```bash
composer require hakam/multi-tenancy-bundle
```

Symfony Flex will automatically scaffold config, register the bundle, and create:

```
src/Entity/Main/
src/Entity/Tenant/
migrations/Main/
migrations/Tenant/
```

### 2. Manual Installation

```bash
composer require hakam/multi-tenancy-bundle
```

Then register in `config/bundles.php`, copy the example `hakam_multi_tenancy.yaml` from docs, and create the required directories.

---

## 📖 Examples

The [`examples/`](examples/) directory contains **15 ready-to-use code examples** covering every feature of the bundle:

| # | Example | Feature |
|---|---------|---------|
| 01 | [Entity Setup](examples/01-entity-setup.php) | `TenantDbConfig` entity with `TenantDbConfigTrait` |
| 02 | [Bundle Configuration](examples/02-bundle-configuration.php) | Full YAML config reference |
| 03 | [Tenant Entities](examples/03-tenant-entities.php) | Tenant-scoped entities (Product, Order) |
| 04 | [Database Lifecycle](examples/04-database-lifecycle.php) | Create DB, switch, CRUD, CLI commands |
| 05 | [Tenant Migrations](examples/05-tenant-migrations.php) | Platform-agnostic migrations with Schema API |
| 06 | [Resolvers](examples/06-resolvers.php) | All 5 resolution strategies + controller usage |
| 07 | [Lifecycle Events](examples/07-events.php) | All 6 events with subscriber pattern |
| 08 | [Custom Config Provider](examples/08-custom-config-provider.php) | Redis, static, and in-memory providers |
| 09 | [Tenant Fixtures](examples/09-tenant-fixtures.php) | `#[TenantFixture]` attribute + CLI |
| 10 | [Cache Isolation](examples/10-tenant-aware-cache.php) | `TenantAwareCacheDecorator` usage |
| 11 | [Tenant Context](examples/11-tenant-context.php) | `TenantContextInterface` in services, Twig, listeners |
| 12 | [Testing](examples/12-testing.php) | `TenantTestTrait` with `runInTenant()` |
| 13 | [Shared Entities](examples/13-shared-entities.php) | `#[TenantShared]` with exclusions |
| 14 | [Custom Resolver](examples/14-custom-resolver.php) | JWT, query param, API key resolvers |
| 15 | [Full Onboarding Flow](examples/15-full-onboarding-flow.php) | Complete end-to-end tenant onboarding |

> See also the [Examples documentation page](https://ramyhakam.github.io/multi_tenancy_bundle/examples/examples) for detailed explanations.

---

## 🔗 Useful Links

* **Full Documentation:** \[[https://ramyhakam.github.io/multi\_tenancy\_bundle/](https://ramyhakam.github.io/multi_tenancy_bundle/)]
* **GitHub:** [https://github.com/RamyHakam/multi\_tenancy\_bundle](https://github.com/RamyHakam/multi_tenancy_bundle)
* **Packagist:** [https://packagist.org/packages/hakam/multi-tenancy-bundle](https://packagist.org/packages/hakam/multi-tenancy-bundle)
* **Example Project:** [https://github.com/RamyHakam/multi-tenancy-project-example](https://github.com/RamyHakam/multi-tenancy-project-example)

---

## 📄 License

MIT © Ramy Hakam
