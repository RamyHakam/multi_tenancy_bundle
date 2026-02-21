<?php

/**
 * Example 14: Custom Tenant Resolver
 *
 * Implement TenantResolverInterface to resolve tenants from any source:
 * JWT tokens, cookies, query parameters, API keys, etc.
 */

namespace App\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

// ──────────────────────────────────────────────
// Option A: Resolve from JWT token claims
// ──────────────────────────────────────────────

class JwtTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $payload = $this->decodeJwt($token);

        return $payload['tenant_id'] ?? null;
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }

    private function decodeJwt(string $token): array
    {
        // Your JWT decoding logic here
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }
        return json_decode(base64_decode($parts[1]), true) ?: [];
    }
}


// ──────────────────────────────────────────────
// Option B: Resolve from query parameter
// ──────────────────────────────────────────────

class QueryParamTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return $request->query->get('tenant');
    }

    public function supports(Request $request): bool
    {
        return $request->query->has('tenant');
    }
}


// ──────────────────────────────────────────────
// Option C: Resolve from API key lookup
// ──────────────────────────────────────────────

class ApiKeyTenantResolver implements TenantResolverInterface
{
    /** @var array<string, string> api_key => tenant_id */
    private array $apiKeyMap;

    public function __construct(array $apiKeyMap)
    {
        $this->apiKeyMap = $apiKeyMap;
    }

    public function resolve(Request $request): ?string
    {
        $apiKey = $request->headers->get('X-API-Key');
        return $this->apiKeyMap[$apiKey] ?? null;
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('X-API-Key');
    }
}


// ──────────────────────────────────────────────
// Registration
// ──────────────────────────────────────────────

/*
# config/services.yaml
services:
    App\Resolver\JwtTenantResolver:
        tags:
            - { name: 'hakam.tenant_resolver' }

# If you want to replace the default resolver entirely,
# alias TenantResolverInterface to your custom implementation:

    Hakam\MultiTenancyBundle\Port\TenantResolverInterface:
        alias: App\Resolver\JwtTenantResolver

# Or use it in a chain:
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: chain
        options:
            chain_order: [header, path]
*/
