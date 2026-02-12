<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves tenant identifier from an HTTP header.
 *
 * Example: Extracts tenant from "X-Tenant-ID" header.
 *
 * Configuration options:
 * - header_name: The name of the header to read (default: 'X-Tenant-ID')
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class HeaderResolver extends AbstractTenantResolver
{
    protected function getDefaultOptions(): array
    {
        return [
            'header_name' => 'X-Tenant-ID',
        ];
    }

    public function resolve(Request $request): ?string
    {
        $headerName = $this->getOption('header_name');
        $value = $request->headers->get($headerName);

        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    public function supports(Request $request): bool
    {
        $headerName = $this->getOption('header_name');

        return $request->headers->has($headerName);
    }
}
