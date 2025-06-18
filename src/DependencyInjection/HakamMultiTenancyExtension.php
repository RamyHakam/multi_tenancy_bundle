<?php

namespace Hakam\MultiTenancyBundle\DependencyInjection;

use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

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

        $definition = $container->getDefinition('hakam_db_config.service');
        $definition->setArgument(1, $configs['tenant_database_className']);
        $definition->setArgument(2, $configs['tenant_database_identifier']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $dbSwitcherConfig = $this->processConfiguration(new Configuration(), $configs);
        if (5 === count($dbSwitcherConfig)) {
            $bundles = $container->getParameter('kernel.bundles');

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
                $container->setParameter('tenant_doctrine_migration', ['migrations_paths' => $tenantDoctrineMigrationPath]);
            } else {
                throw new InvalidConfigurationException('You need to enable Doctrine Migration Bundle to be able to use MultiTenancy Bundle');
            }
        }
    }

    private function injectTenantDqlFunctions(array &$tenantEntityManagerConfig, array $dbSwitchConfig): void
    {
        if (isset($dbSwitchConfig['tenant_entity_manager']['dql'])) {
            $tenantEntityManagerConfig['entity_managers']['tenant']['dql'] = $dbSwitchConfig['tenant_entity_manager']['dql'];
        }
    }
}
