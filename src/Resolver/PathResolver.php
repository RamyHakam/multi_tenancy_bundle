<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves tenant identifier from the URL path.
 *
 * Example: For "/tenant1/dashboard", extracts "tenant1" as the tenant identifier.
 *
 * Configuration options:
 * - path_segment: Which path segment to use (0 = first after leading slash, default: 0)
 * - excluded_paths: Array of path prefixes to exclude from resolution (e.g., ['/api', '/admin'])
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class PathResolver extends AbstractTenantResolver
{
    protected function getDefaultOptions(): array
    {
        return [
            'path_segment' => 0,
            'excluded_paths' => [],
        ];
    }

    public function resolve(Request $request): ?string
    {
        $path = $request->getPathInfo();
        $segment = (int) $this->getOption('path_segment');

        $parts = array_values(array_filter(explode('/', $path)));

        if (empty($parts)) {
            return null;
        }

        return $parts[$segment] ?? null;
    }

    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();
        $excludedPaths = $this->getOption('excluded_paths', []);

        foreach ($excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return false;
            }
        }

        $parts = array_filter(explode('/', $path));

        return !empty($parts);
    }
}
