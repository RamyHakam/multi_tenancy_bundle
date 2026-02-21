<?php

/**
 * Example 6: Tenant Resolvers
 *
 * Resolvers automatically detect the tenant from incoming HTTP requests.
 * When enabled, the TenantResolutionListener fires on kernel.request
 * and dispatches SwitchDbEvent — no manual switching needed in controllers.
 *
 * Five strategies are available: header, subdomain, path, host, chain.
 */

// ──────────────────────────────────────────────
// Strategy 1: HEADER (recommended for APIs)
// Tenant ID comes from an HTTP header.
// ──────────────────────────────────────────────

/*
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: header
        options:
            header_name: X-Tenant-ID    # default header name

# Usage:
# curl -H "X-Tenant-ID: 42" https://api.example.com/products
*/


// ──────────────────────────────────────────────
// Strategy 2: SUBDOMAIN
// Tenant ID extracted from subdomain.
// e.g., "acme.example.com" → tenant "acme"
// ──────────────────────────────────────────────

/*
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: subdomain
        options:
            subdomain_position: 0       # 0 = first subdomain part
            base_domain: example.com    # optional, auto-detected if null
*/


// ──────────────────────────────────────────────
// Strategy 3: PATH
// Tenant ID extracted from URL path segment.
// e.g., "/tenant-42/dashboard" → tenant "tenant-42"
// ──────────────────────────────────────────────

/*
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: path
        options:
            path_segment: 0             # 0 = first path segment after /
            excluded_paths: ['/api']    # paths to skip resolution
*/


// ──────────────────────────────────────────────
// Strategy 4: HOST
// Full hostname mapped to tenant ID via a lookup table.
// e.g., "client1.com" → "tenant_1"
// ──────────────────────────────────────────────

/*
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: host
        options:
            host_map:
                client1.com: tenant_1
                client2.com: tenant_2
                custom-domain.org: tenant_3
*/


// ──────────────────────────────────────────────
// Strategy 5: CHAIN (combines multiple resolvers)
// Tries resolvers in order until one succeeds.
// ──────────────────────────────────────────────

/*
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: chain
        options:
            chain_order: [header, path]  # try header first, fall back to path
*/


// ──────────────────────────────────────────────
// Common options (work with all strategies)
// ──────────────────────────────────────────────

/*
hakam_multi_tenancy:
    resolver:
        enabled: true
        strategy: header
        throw_on_missing: true          # throw RuntimeException if tenant can't be resolved
        excluded_paths:                 # skip resolution for these path prefixes
            - /health
            - /api/public
            - /_profiler
            - /login
*/


// ──────────────────────────────────────────────
// Using resolver with controllers
// When resolver is enabled, controllers don't need to dispatch
// SwitchDbEvent manually — it happens automatically.
// ──────────────────────────────────────────────

namespace App\Controller;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly TenantEntityManager $tenantEntityManager,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function list(): JsonResponse
    {
        // No need to dispatch SwitchDbEvent — the resolver already did it!
        // The tenant context knows which tenant is active.
        $tenantId = $this->tenantContext->getTenantId();

        $products = $this->tenantEntityManager
            ->getRepository(\App\Entity\Tenant\Product::class)
            ->findAll();

        return new JsonResponse([
            'tenant' => $tenantId,
            'products' => array_map(fn($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
            ], $products),
        ]);
    }

    /**
     * The tenant ID is also available as a request attribute.
     */
    public function show(Request $request, int $productId): JsonResponse
    {
        $tenantId = $request->attributes->get('_tenant');

        $product = $this->tenantEntityManager->find(
            \App\Entity\Tenant\Product::class,
            $productId
        );

        return new JsonResponse([
            'tenant' => $tenantId,
            'product' => $product?->getName(),
        ]);
    }
}
