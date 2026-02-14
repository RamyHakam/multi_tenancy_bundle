<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves tenant identifier from the full hostname.
 *
 * Uses a mapping of hostnames to tenant identifiers.
 * Example: Maps "client1.com" => "tenant1"
 *
 * Configuration options:
 * - host_map: Array mapping hostnames to tenant identifiers
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class HostResolver extends AbstractTenantResolver
{
    protected function getDefaultOptions(): array
    {
        return [
            'host_map' => [],
        ];
    }

    public function resolve(Request $request): ?string
    {
        $host = $request->getHost();
        $hostMap = $this->getOption('host_map', []);

        return $hostMap[$host] ?? null;
    }

    public function supports(Request $request): bool
    {
        $host = $request->getHost();
        $hostMap = $this->getOption('host_map', []);

        return isset($hostMap[$host]);
    }
}
