<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves tenant identifier from the request subdomain.
 *
 * Example: For "tenant1.example.com", extracts "tenant1" as the tenant identifier.
 *
 * Configuration options:
 * - subdomain_position: Which subdomain part to use (0 = first, default: 0)
 * - base_domain: The base domain to strip (auto-detected if null)
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class SubdomainResolver extends AbstractTenantResolver
{
    protected function getDefaultOptions(): array
    {
        return [
            'subdomain_position' => 0,
            'base_domain' => null,
        ];
    }

    public function resolve(Request $request): ?string
    {
        $host = $request->getHost();

        if (empty($host)) {
            return null;
        }

        $baseDomain = $this->getOption('base_domain');
        $position = (int) $this->getOption('subdomain_position');

        if ($baseDomain !== null) {
            $baseDomain = ltrim($baseDomain, '.');
            if (str_ends_with($host, '.' . $baseDomain)) {
                $subdomain = substr($host, 0, -strlen('.' . $baseDomain));
                $parts = explode('.', $subdomain);
                return $parts[$position] ?? null;
            }
            return null;
        }

        $parts = explode('.', $host);

        // Need at least 3 parts for subdomain detection (subdomain.domain.tld)
        if (count($parts) < 3) {
            return null;
        }

        return $parts[$position] ?? null;
    }

    public function supports(Request $request): bool
    {
        $host = $request->getHost();

        if (empty($host)) {
            return false;
        }

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $baseDomain = $this->getOption('base_domain');

        if ($baseDomain !== null) {
            return str_ends_with($host, '.' . ltrim($baseDomain, '.'));
        }

        return count(explode('.', $host)) >= 3;
    }
}
