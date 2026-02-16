<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\EventListener\DbSwitchEventListener;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service\InMemoryTenantConfigProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomProviderOverrideTest extends IntegrationTestCase
{
    protected function getKernelConfig(): array
    {
        return [
            'tenant_config_provider' => 'test.in_memory_config_provider',
        ];
    }

    protected function getServiceRegistrar(): ?callable
    {
        return function (ContainerBuilder $container): void {
            $container->register('test.in_memory_config_provider', InMemoryTenantConfigProvider::class)
                ->setPublic(true);
        };
    }

    public function testTenantConfigProviderInterfaceResolvesToCustomProvider(): void
    {
        $provider = static::$container->get(TenantConfigProviderInterface::class);

        $this->assertInstanceOf(InMemoryTenantConfigProvider::class, $provider);
    }

    public function testDbSwitchEventListenerReceivesCustomProvider(): void
    {
        $listener = static::$container->get(DbSwitchEventListener::class);

        $reflection = new \ReflectionClass($listener);
        $property = $reflection->getProperty('tenantConfigProvider');

        $this->assertInstanceOf(InMemoryTenantConfigProvider::class, $property->getValue($listener));
    }
}
