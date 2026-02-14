<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to kernel.request events and automatically resolves the tenant.
 *
 * This listener runs early in the request lifecycle (before controllers)
 * and dispatches SwitchDbEvent to switch the database context.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class TenantResolutionListener implements EventSubscriberInterface
{
    public const REQUEST_ATTRIBUTE_TENANT = '_tenant';
    public const REQUEST_ATTRIBUTE_TENANT_RESOLVED = '_tenant_resolved';

    public function __construct(
        private readonly TenantResolverInterface $resolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly bool $throwOnMissing = false,
        private readonly array $excludedPaths = [],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get(self::REQUEST_ATTRIBUTE_TENANT_RESOLVED, false)) {
            return;
        }

        $path = $request->getPathInfo();
        foreach ($this->excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                $request->attributes->set(self::REQUEST_ATTRIBUTE_TENANT_RESOLVED, true);
                return;
            }
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE_TENANT_RESOLVED, true);

        if (!$this->resolver->supports($request)) {
            if ($this->throwOnMissing) {
                throw new \RuntimeException('Unable to resolve tenant: resolver does not support this request.');
            }
            return;
        }

        $tenantId = $this->resolver->resolve($request);

        if ($tenantId === null) {
            if ($this->throwOnMissing) {
                throw new \RuntimeException('Unable to resolve tenant: no tenant identifier found.');
            }
            return;
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE_TENANT, $tenantId);

        $this->eventDispatcher->dispatch(new SwitchDbEvent($tenantId));
    }
}
