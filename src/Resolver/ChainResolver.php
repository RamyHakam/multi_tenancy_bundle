<?php

namespace Hakam\MultiTenancyBundle\Resolver;

use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Composite resolver that chains multiple resolvers.
 *
 * Tries each resolver in order until one successfully resolves the tenant.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class ChainResolver implements TenantResolverInterface
{
    /**
     * @var TenantResolverInterface[]
     */
    private array $resolvers;

    /**
     * @param TenantResolverInterface[] $resolvers Resolvers to chain, in priority order.
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * Add a resolver to the chain.
     *
     * @param TenantResolverInterface $resolver
     * @param int $priority Higher priority resolvers are tried first.
     * @return void
     */
    public function addResolver(TenantResolverInterface $resolver, int $priority = 0): void
    {
        $this->resolvers[] = ['resolver' => $resolver, 'priority' => $priority];
        usort($this->resolvers, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
    }

    public function resolve(Request $request): ?string
    {
        foreach ($this->resolvers as $item) {
            $resolver = $item instanceof TenantResolverInterface ? $item : $item['resolver'];

            if ($resolver->supports($request)) {
                $tenant = $resolver->resolve($request);
                if ($tenant !== null) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    public function supports(Request $request): bool
    {
        foreach ($this->resolvers as $item) {
            $resolver = $item instanceof TenantResolverInterface ? $item : $item['resolver'];

            if ($resolver->supports($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered resolvers.
     *
     * @return TenantResolverInterface[]
     */
    public function getResolvers(): array
    {
        return array_map(
            fn($item) => $item instanceof TenantResolverInterface ? $item : $item['resolver'],
            $this->resolvers
        );
    }
}
