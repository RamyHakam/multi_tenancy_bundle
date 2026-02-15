<?php

namespace Hakam\MultiTenancyBundle\Test;

use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\EventListener\DbSwitchEventListener;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait TenantTestTrait
{
    /**
     * Switches to the given tenant, executes the callback, and resets state automatically.
     *
     * @return mixed The callback's return value
     */
    public function runInTenant(string $tenantIdentifier, callable $callback): mixed
    {
        $container = $this->getTenantTestContainer();

        $container->get('event_dispatcher')->dispatch(new SwitchDbEvent($tenantIdentifier));

        try {
            return $callback();
        } finally {
            $this->resetTenantState();
        }
    }

    /**
     * Switches to the given tenant without automatic cleanup.
     * Call resetTenantState() manually (typically in tearDown()).
     */
    public function switchToTenant(string $tenantIdentifier): void
    {
        $container = $this->getTenantTestContainer();

        $container->get('event_dispatcher')->dispatch(new SwitchDbEvent($tenantIdentifier));
    }

    /**
     * Resets all tenant-related state: clears EM identity map, closes connection, resets listener.
     * Safe to call even when no tenant is active.
     */
    public function resetTenantState(): void
    {
        $container = $this->getTenantTestContainer();

        try {
            $container->get('tenant_entity_manager')->clear();
        } catch (\Throwable) {
        }

        try {
            $container->get('doctrine')->getConnection('tenant')->close();
        } catch (\Throwable) {
        }

        try {
            $container->get(DbSwitchEventListener::class)->reset();
        } catch (\Throwable) {
        }
    }

    public function getTenantEntityManager(): TenantEntityManager
    {
        return $this->getTenantTestContainer()->get('tenant_entity_manager');
    }

    private function getTenantTestContainer(): ContainerInterface
    {
        if (!method_exists(static::class, 'getContainer')) {
            throw new \LogicException(sprintf(
                'The trait "%s" requires the test class to extend KernelTestCase or provide a getContainer() method.',
                __TRAIT__,
            ));
        }

        return static::getContainer();
    }
}
