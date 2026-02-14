<?php

namespace Hakam\MultiTenancyBundle\Port;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for resolving tenant identifier from HTTP requests.
 *
 * Implementations can extract tenant information from various sources
 * such as subdomains, paths, headers, or custom logic.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
interface TenantResolverInterface
{
    /**
     * Resolve the tenant identifier from the request.
     *
     * @param Request $request The incoming HTTP request.
     * @return string|null The tenant identifier, or null if not resolvable.
     */
    public function resolve(Request $request): ?string;

    /**
     * Check if this resolver supports the given request.
     *
     * @param Request $request The incoming HTTP request.
     * @return bool True if this resolver can handle the request.
     */
    public function supports(Request $request): bool;
}
