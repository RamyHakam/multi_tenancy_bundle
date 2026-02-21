<?php

/**
 * Example 11: Tenant Context
 *
 * TenantContext holds the currently active tenant ID.
 * It's updated automatically when SwitchDbEvent fires (either manually
 * or through the resolver).
 *
 * Inject TenantContextInterface wherever you need to know which tenant
 * is currently active.
 */

namespace App\Service;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Psr\Log\LoggerInterface;

class AuditService
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext,
        private readonly LoggerInterface $logger,
    ) {}

    public function log(string $action, array $data = []): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        $this->logger->info('Audit log', [
            'tenant' => $tenantId,
            'action' => $action,
            'data' => $data,
        ]);
    }
}


// ──────────────────────────────────────────────
// Using TenantContext in Twig templates
// ──────────────────────────────────────────────

/*
{# Register TenantContext as a Twig global in config/packages/twig.yaml #}
twig:
    globals:
        tenant_context: '@Hakam\MultiTenancyBundle\Context\TenantContext'

{# Then use it in templates: #}
{% if tenant_context.tenantId %}
    <p>Current tenant: {{ tenant_context.tenantId }}</p>
{% endif %}
*/


// ──────────────────────────────────────────────
// Using TenantContext in middleware / event listeners
// ──────────────────────────────────────────────

namespace App\EventListener;

use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class TenantResponseHeaderListener
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext,
    ) {}

    /**
     * Add the active tenant ID to response headers for debugging.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if ($tenantId !== null) {
            $event->getResponse()->headers->set('X-Active-Tenant', $tenantId);
        }
    }
}
