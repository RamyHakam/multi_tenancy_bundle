<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service\AsAliasConfigProvider;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Service\AsAliasDatabaseManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class AsAliasOverrideTest extends IntegrationTestCase
{
    protected function getServiceRegistrar(): ?callable
    {
        return function (ContainerBuilder $container): void {
            $loader = new PhpFileLoader($container, new FileLocator());
            $prototype = (new Definition())
                ->setAutoconfigured(true)
                ->setAutowired(true)
                ->setPublic(true);

            $loader->registerClasses(
                $prototype,
                'Hakam\\MultiTenancyBundle\\Tests\\Integration\\Fixtures\\Service\\',
                __DIR__ . '/Fixtures/Service/AsAlias*.php'
            );
        };
    }

    public function testAsAliasOverridesTenantConfigProvider(): void
    {
        $provider = static::$container->get(TenantConfigProviderInterface::class);

        $this->assertInstanceOf(AsAliasConfigProvider::class, $provider);
    }

    public function testAsAliasOverridesTenantDatabaseManager(): void
    {
        $manager = static::$container->get(TenantDatabaseManagerInterface::class);

        $this->assertInstanceOf(AsAliasDatabaseManager::class, $manager);
    }
}
