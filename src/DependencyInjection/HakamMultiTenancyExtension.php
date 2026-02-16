<?php

namespace Hakam\MultiTenancyBundle\DependencyInjection;

use Hakam\MultiTenancyBundle\Cache\TenantAwareCacheDecorator;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Hakam\MultiTenancyBundle\EventListener\TenantResolutionListener;
use Hakam\MultiTenancyBundle\Port\TenantResolverInterface;
use Hakam\MultiTenancyBundle\Resolver\ChainResolver;
use Hakam\MultiTenancyBundle\Resolver\HeaderResolver;
use Hakam\MultiTenancyBundle\Resolver\HostResolver;
use Hakam\MultiTenancyBundle\Resolver\PathResolver;
use Hakam\MultiTenancyBundle\Resolver\SubdomainResolver;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class HakamMultiTenancyExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);

        $configs = $this->processConfiguration($configuration, $configs);

        // set the required parameter
        $container->setParameter('hakam.tenant_db_credentials', ['db_url' => $configs['tenant_connection']['url']]);
        $container->setParameter('hakam.tenant_db_list_entity', $configs['tenant_database_className']);
        $container->setParameter('hakam.tenant_db_identifier', $configs['tenant_database_identifier']);

        if ($configs['tenant_config_provider'] === 'hakam_tenant_config_provider.doctrine') {
            // check if the tenant database className and identifier are set
            if (empty($configs['tenant_database_className']) || empty($configs['tenant_database_identifier'])) {
                throw new InvalidConfigurationException('You need to set tenant_database_className and tenant_database_identifier in your configuration');
            }
            $tenantProviderDefinition = $container->getDefinition('hakam_tenant_config_provider.doctrine');
            $tenantProviderDefinition->setArgument(1, $configs['tenant_database_className']);
            $tenantProviderDefinition->setArgument(2, $configs['tenant_database_identifier']);
        } else {
            $container->setAlias(TenantConfigProviderInterface::class, $configs['tenant_config_provider'])
                ->setPublic(false);
        }

        // Configure tenant resolver if enabled
        $this->configureResolver($configs, $container);

        // Configure tenant-aware cache if enabled
        $this->configureCache($configs, $container);
    }

    private function configureResolver(array $configs, ContainerBuilder $container): void
    {
        $resolverConfig = $configs['resolver'] ?? [];

        if (!($resolverConfig['enabled'] ?? false)) {
            return;
        }

        $strategy = $resolverConfig['strategy'] ?? 'subdomain';
        $options = $resolverConfig['options'] ?? [];
        $throwOnMissing = $resolverConfig['throw_on_missing'] ?? false;
        $excludedPaths = $resolverConfig['excluded_paths'] ?? [];

        // Create resolver based on strategy
        $resolverServiceId = $this->createResolverService($strategy, $options, $container);

        // Create the TenantResolutionListener
        $listenerDefinition = new Definition(TenantResolutionListener::class);
        $listenerDefinition->setArguments([
            new Reference($resolverServiceId),
            new Reference('event_dispatcher'),
            $throwOnMissing,
            $excludedPaths,
        ]);
        $listenerDefinition->addTag('kernel.event_subscriber');
        $container->setDefinition(TenantResolutionListener::class, $listenerDefinition);

        // Create alias for TenantResolverInterface
        $container->setAlias(TenantResolverInterface::class, $resolverServiceId)->setPublic(false);
    }

    private function createResolverService(string $strategy, array $options, ContainerBuilder $container): string
    {
        $serviceId = 'hakam.tenant_resolver.' . $strategy;

        $resolverClass = match ($strategy) {
            'subdomain' => SubdomainResolver::class,
            'host' => HostResolver::class,
            'path' => PathResolver::class,
            'header' => HeaderResolver::class,
            'chain' => ChainResolver::class,
            default => throw new InvalidConfigurationException(sprintf('Unknown resolver strategy "%s"', $strategy)),
        };

        if ($strategy === 'chain') {
            $definition = $this->createChainResolverDefinition($options, $container);
        } else {
            $resolverOptions = $this->getResolverOptions($strategy, $options);
            $definition = new Definition($resolverClass);
            $definition->setArgument(0, $resolverOptions);
        }

        $definition->setPublic(false);
        $container->setDefinition($serviceId, $definition);

        return $serviceId;
    }

    private function createChainResolverDefinition(array $options, ContainerBuilder $container): Definition
    {
        $chainOrder = $options['chain_order'] ?? ['header', 'subdomain', 'path'];
        $resolvers = [];

        foreach ($chainOrder as $subStrategy) {
            $subServiceId = 'hakam.tenant_resolver.' . $subStrategy . '.sub';
            $subOptions = $this->getResolverOptions($subStrategy, $options);

            $subClass = match ($subStrategy) {
                'subdomain' => SubdomainResolver::class,
                'host' => HostResolver::class,
                'path' => PathResolver::class,
                'header' => HeaderResolver::class,
                default => throw new InvalidConfigurationException(sprintf('Unknown resolver strategy "%s" in chain', $subStrategy)),
            };

            $subDefinition = new Definition($subClass);
            $subDefinition->setArgument(0, $subOptions);
            $subDefinition->setPublic(false);
            $container->setDefinition($subServiceId, $subDefinition);

            $resolvers[] = new Reference($subServiceId);
        }

        $definition = new Definition(ChainResolver::class);
        $definition->setArgument(0, $resolvers);

        return $definition;
    }

    private function getResolverOptions(string $strategy, array $options): array
    {
        return match ($strategy) {
            'subdomain' => [
                'subdomain_position' => $options['subdomain_position'] ?? 0,
                'base_domain' => $options['base_domain'] ?? null,
            ],
            'host' => [
                'host_map' => $options['host_map'] ?? [],
            ],
            'path' => [
                'path_segment' => $options['path_segment'] ?? 0,
                'excluded_paths' => $options['excluded_paths'] ?? [],
            ],
            'header' => [
                'header_name' => $options['header_name'] ?? 'X-Tenant-ID',
            ],
            default => [],
        };
    }

    private function configureCache(array $configs, ContainerBuilder $container): void
    {
        $cacheConfig = $configs['cache'] ?? [];

        if (!($cacheConfig['enabled'] ?? false)) {
            return;
        }

        $separator = $cacheConfig['prefix_separator'] ?? '__';

        $definition = new Definition(TenantAwareCacheDecorator::class);
        $definition->setDecoratedService('cache.app');
        $definition->setArguments([
            new Reference(TenantAwareCacheDecorator::class . '.inner'),
            new Reference(TenantContextInterface::class),
            $separator,
        ]);
        $container->setDefinition(TenantAwareCacheDecorator::class, $definition);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $dbSwitcherConfig = $this->processConfiguration(new Configuration(), $configs);
        $requiredKeys = ['tenant_database_className', 'tenant_database_identifier',
            'tenant_config_provider', 'tenant_connection',
            'tenant_migration', 'tenant_entity_manager'];
        $hasAllRequired = count(array_intersect_key($dbSwitcherConfig, array_flip($requiredKeys))) === 6;
        if ($hasAllRequired) {
            $bundles = $container->getParameter('kernel.bundles');

            $this->checkDir($container->getParameter('kernel.project_dir'), $dbSwitcherConfig['tenant_entity_manager']['mapping']['dir']);

            $tenantConnectionConfig = [
                'connections' => [
                    'tenant' => [
                        'driver' => $dbSwitcherConfig['tenant_connection']['driver'],
                        'url' => $dbSwitcherConfig['tenant_connection']['url'],
                        'host' => $dbSwitcherConfig['tenant_connection']['host'],
                        'port' => $dbSwitcherConfig['tenant_connection']['port'],
                        'charset' => $dbSwitcherConfig['tenant_connection']['charset'],
                        'server_version' => $dbSwitcherConfig['tenant_connection']['server_version'],
                        'wrapper_class' => TenantConnection::class,
                    ],
                ],
            ];
            $tenantEntityManagerConfig = [
                'entity_managers' => [
                    'tenant' => [
                        'connection' => 'tenant',
                        'naming_strategy' => $dbSwitcherConfig['tenant_entity_manager']['tenant_naming_strategy'],
                        'mappings' => [
                            'HakamMultiTenancyBundle' => [
                                'type' => $dbSwitcherConfig['tenant_entity_manager']['mapping']['type'],
                                'dir' => $dbSwitcherConfig['tenant_entity_manager']['mapping']['dir'],
                                'prefix' => $dbSwitcherConfig['tenant_entity_manager']['mapping']['prefix'] ?? null,
                                'alias' => $dbSwitcherConfig['tenant_entity_manager']['mapping']['alias'] ?? null,
                                'is_bundle' => $dbSwitcherConfig['tenant_entity_manager']['mapping']['is_bundle'] ?? true,
                            ],
                        ],
                    ],
                ],
            ];

            $this->injectTenantDqlFunctions($tenantEntityManagerConfig, $dbSwitcherConfig);
            $this->checkDir($container->getParameter('kernel.project_dir'), $dbSwitcherConfig['tenant_migration']['tenant_migration_path']);
            $tenantDoctrineMigrationPath =
                [
                    $dbSwitcherConfig['tenant_migration']['tenant_migration_namespace'] => $dbSwitcherConfig['tenant_migration']['tenant_migration_path'],
                ];

            if (!isset($bundles['doctrine'])) {
                $container->prependExtensionConfig('doctrine', ['dbal' => $tenantConnectionConfig, 'orm' => $tenantEntityManagerConfig]);
            } else {
                throw new InvalidConfigurationException('You need to enable Doctrine Bundle to be able to use db switch bundle');
            }

            if (!isset($bundles['doctrine_migrations'])) {
                //    $container->prependExtensionConfig('doctrine_migrations', ['migrations_paths' => $tenantDoctrineMigrationPath]);
                $container->setParameter('tenant_doctrine_migration', ['migrations_paths' => $tenantDoctrineMigrationPath]);
            } else {
                throw new InvalidConfigurationException('You need to enable Doctrine Migration Bundle to be able to use MultiTenancy Bundle');
            }
        }
    }

    private function checkDir(string $projectDir, string $dir): void
    {
        $fileSystem = new Filesystem();
        $dir = str_replace('%kernel.project_dir%', '', $dir);
        $dir = sprintf("%s/%s", $projectDir, $dir);
        if (!$fileSystem->exists($dir)) {
            $fileSystem->mkdir($dir);
        }
    }

    private function injectTenantDqlFunctions(array &$tenantEntityManagerConfig, array $dbSwitchConfig): void
    {
        if (isset($dbSwitchConfig['tenant_entity_manager']['dql'])) {
            $tenantEntityManagerConfig['entity_managers']['tenant']['dql'] = $dbSwitchConfig['tenant_entity_manager']['dql'];
        }
    }
}
