<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract base class for tenant resolvers.
 *
 * Provides common functionality and configuration handling
 * for concrete resolver implementations.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
abstract class AbstractTenantResolver implements TenantResolverInterface
{
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Get the default options for this resolver.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultOptions(): array
    {
        return [];
    }

    /**
     * Get a specific option value.
     *
     * @param string $key The option key.
     * @param mixed $default The default value if not set.
     * @return mixed
     */
    protected function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function supports(Request $request): bool
    {
        return true;
    }
}
