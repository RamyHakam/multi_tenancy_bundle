---
title: "Automatic Tenant Resolution"
sidebar_position: 1
---

# Automatic Tenant Resolution

**Available since v3.0.0**

The Automatic Tenant Resolution feature eliminates the need to manually dispatch `SwitchDbEvent` in your controllers. The bundle automatically determines the current tenant from incoming HTTP requests and switches the database context before your controller executes.

## Why Use Automatic Resolution?

| Before (Manual) | After (Automatic) |
|-----------------|-------------------|
| Dispatch `SwitchDbEvent` in every controller | Configure once, works everywhere |
| Boilerplate code in each tenant-aware action | Zero boilerplate |
| Easy to forget switching in some routes | Consistent tenant context |

## Quick Start

Enable automatic resolution in your configuration:

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: subdomain
        options:
            base_domain: 'myapp.com'
```

That's it! Requests to `tenant1.myapp.com` will automatically switch to the `tenant1` database.

---

## Resolution Strategies

The bundle provides five built-in strategies for extracting tenant identifiers from requests.

### Subdomain Strategy

Extracts the tenant identifier from the subdomain portion of the hostname.

**Example:** `acme.myapp.com` → tenant ID: `acme`

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: subdomain
        options:
            base_domain: 'myapp.com'      # Required: your base domain
            subdomain_position: 0          # Optional: which subdomain part (default: 0)
```

**Use cases:**
- SaaS applications with custom subdomains per customer
- Regional deployments (`us.app.com`, `eu.app.com`)

**Multi-level subdomains:**

For `api.acme.myapp.com`:
- `subdomain_position: 0` → `api`
- `subdomain_position: 1` → `acme`

---

### Header Strategy

Extracts the tenant identifier from an HTTP header. Ideal for API-first applications.

**Example:** `X-Tenant-ID: acme` → tenant ID: `acme`

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: header
        options:
            header_name: 'X-Tenant-ID'    # Optional: default is 'X-Tenant-ID'
```

**Use cases:**
- REST/GraphQL APIs where clients specify their tenant
- Mobile applications with tenant selection
- Microservices communicating tenant context

---

### Path Strategy

Extracts the tenant identifier from a URL path segment.

**Example:** `/acme/dashboard` → tenant ID: `acme`

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: path
        options:
            path_segment: 0               # Optional: which segment (default: 0)
        excluded_paths:                   # Optional: paths to skip
            - '/api/public'
            - '/health'
            - '/_profiler'
```

**Use cases:**
- Applications where tenants are part of the URL structure
- Legacy systems migrating to multi-tenancy
- Shared hosting environments

---

### Host Strategy

Maps complete hostnames to tenant identifiers. Useful when tenants have their own custom domains.

**Example:** `acme-corp.com` → tenant ID: `acme`

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: host
        options:
            host_map:
                'acme-corp.com': 'acme'
                'beta-inc.com': 'beta'
                'gamma.example.com': 'gamma'
```

**Use cases:**
- White-label applications with custom domains
- Enterprise customers with vanity URLs

:::tip Dynamic Host Mapping
For dynamic host mapping, implement a custom resolver that queries your database.
:::

---

### Chain Strategy

Combines multiple strategies with fallback support. Tries each resolver in order until one succeeds.

```yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: chain
        options:
            chain_order:
                - header      # Try header first (for API clients)
                - subdomain   # Then subdomain (for web users)
                - path        # Finally path (fallback)
            # Include options for each sub-strategy
            header_name: 'X-Tenant-ID'
            base_domain: 'myapp.com'
            path_segment: 0
```

**Use cases:**
- Applications serving both API and web clients
- Gradual migration from one strategy to another
- Supporting multiple tenant identification methods

---

## Configuration Reference

### Full Configuration Example

```yaml
hakam_multi_tenancy:
    # ... other configuration ...
    
    resolver:
        # Enable/disable automatic resolution (default: false)
        enabled: true
        
        # Resolution strategy: subdomain | header | path | host | chain
        strategy: subdomain
        
        # Throw exception if tenant cannot be resolved (default: false)
        throw_on_missing: false
        
        # Paths to exclude from resolution
        excluded_paths:
            - '/health'
            - '/metrics'
            - '/_profiler'
            - '/api/public'
        
        # Strategy-specific options
        options:
            # Subdomain options
            subdomain_position: 0
            base_domain: 'myapp.com'
            
            # Header options
            header_name: 'X-Tenant-ID'
            
            # Path options
            path_segment: 0
            
            # Host options
            host_map:
                'custom-domain.com': 'tenant1'
            
            # Chain options
            chain_order:
                - header
                - subdomain
                - path
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable automatic resolution |
| `strategy` | string | `subdomain` | Resolution strategy to use |
| `throw_on_missing` | bool | `false` | Throw exception if tenant not found |
| `excluded_paths` | array | `[]` | Paths to skip resolution |

---

## Accessing the Resolved Tenant

Once resolved, the tenant identifier is available in the request attributes:

```php
// In a controller
public function dashboard(Request $request): Response
{
    $tenantId = $request->attributes->get('_tenant');
    
    if ($tenantId === null) {
        // No tenant resolved (public route?)
    }
    
    // The database is already switched - just use TenantEntityManager
    $orders = $this->tenantEm->getRepository(Order::class)->findAll();
}
```

### Request Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `_tenant` | `string\|null` | The resolved tenant identifier |
| `_tenant_resolved` | `bool` | Whether resolution was attempted |

---

## Custom Resolvers

Implement `TenantResolverInterface` for custom resolution logic:

```php
<?php

namespace App\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

class CookieResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return $request->cookies->get('tenant_id');
    }

    public function supports(Request $request): bool
    {
        return $request->cookies->has('tenant_id');
    }
}
```

### Register Your Custom Resolver

```yaml
# config/services.yaml
services:
    App\Resolver\CookieResolver:
        tags: ['hakam.tenant_resolver']
```

### Using Custom Resolver with Chain Strategy

```php
<?php

namespace App\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class DatabaseHostResolver implements TenantResolverInterface
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function resolve(Request $request): ?string
    {
        $host = $request->getHost();
        
        // Query your database for host-to-tenant mapping
        $mapping = $this->em->getRepository(DomainMapping::class)
            ->findOneBy(['domain' => $host]);
        
        return $mapping?->getTenantId();
    }

    public function supports(Request $request): bool
    {
        return true; // Always try to resolve
    }
}
```

---

## Error Handling

### Graceful Degradation (Default)

By default, if no tenant can be resolved, the request continues without switching databases:

```yaml
resolver:
    enabled: true
    throw_on_missing: false  # Default
```

This is useful for applications with public routes.

### Strict Mode

Enable `throw_on_missing` to throw an exception when resolution fails:

```yaml
resolver:
    enabled: true
    throw_on_missing: true
```

Handle the exception in your error handler:

```php
// src/EventListener/TenantExceptionListener.php
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class TenantExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        if (str_contains($exception->getMessage(), 'Unable to resolve tenant')) {
            $event->setResponse(new Response('Tenant not found', 404));
        }
    }
}
```

---

## Best Practices

### 1. Exclude Public Routes

Always exclude routes that don't require tenant context:

```yaml
resolver:
    excluded_paths:
        - '/health'
        - '/login'
        - '/api/public'
        - '/_profiler'
        - '/_wdt'
```

### 2. Use Header Strategy for APIs

For API-first applications, the header strategy provides the most flexibility:

```yaml
resolver:
    strategy: header
    options:
        header_name: 'X-Tenant-ID'
```

### 3. Combine with Security

Validate that the authenticated user has access to the resolved tenant:

```php
public function onKernelController(ControllerEvent $event): void
{
    $request = $event->getRequest();
    $tenantId = $request->attributes->get('_tenant');
    $user = $this->security->getUser();
    
    if ($tenantId && $user && !$user->hasAccessTo($tenantId)) {
        throw new AccessDeniedException('No access to this tenant');
    }
}
```

### 4. Cache Host Mappings

For the host strategy with many mappings, consider caching:

```php
class CachedHostResolver implements TenantResolverInterface
{
    public function __construct(
        private CacheInterface $cache,
        private EntityManagerInterface $em
    ) {}

    public function resolve(Request $request): ?string
    {
        $host = $request->getHost();
        
        return $this->cache->get('tenant_host_' . $host, function () use ($host) {
            $mapping = $this->em->getRepository(DomainMapping::class)
                ->findOneBy(['domain' => $host]);
            return $mapping?->getTenantId();
        });
    }
}
```

---

## Backward Compatibility

Automatic resolution is **disabled by default**. Your existing code using manual `SwitchDbEvent` dispatching continues to work:

```php
// This still works, even with resolver enabled
$this->dispatcher->dispatch(new SwitchDbEvent($tenantId));
```

You can also override automatic resolution by dispatching `SwitchDbEvent` manually after the automatic resolution has occurred.

---

## Troubleshooting

### Tenant Not Being Resolved

1. **Check configuration:** Ensure `resolver.enabled: true`
2. **Verify strategy options:** For subdomain, ensure `base_domain` matches your host
3. **Check excluded paths:** Make sure your route isn't excluded
4. **Debug:** Check request attributes for `_tenant_resolved`

### Wrong Tenant Resolved

1. **Check subdomain_position:** For multi-level subdomains, verify the position
2. **Verify path_segment:** Ensure you're extracting the correct segment
3. **Debug:** Log the resolved tenant in a listener

### Resolution Happening Too Late

The listener runs with priority `32` (after router, before controller). If you need earlier resolution, create a custom listener with higher priority.
