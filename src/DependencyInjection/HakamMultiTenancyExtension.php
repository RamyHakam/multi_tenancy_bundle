<?php

namespace Hakam\MultiTenancyBundle\DependencyInjection;

use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnection;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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

        if ($configs['tenant_config_provider'] == 'hakam_tenant_config_provider.doctrine') {
            // check if the tenant database className and identifier are set
            if (empty($configs['tenant_database_className']) || empty($configs['tenant_database_identifier'])) {
                throw new InvalidConfigurationException('You need to set tenant_database_className and tenant_database_identifier in your configuration');
            }
            $tenantProviderDefinition = $container->getDefinition('hakam_tenant_config_provider.doctrine');
            $tenantProviderDefinition->setArgument(1, $configs['tenant_database_className']);
            $tenantProviderDefinition->setArgument(2, $configs['tenant_database_identifier']);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $dbSwitcherConfig = $this->processConfiguration(new Configuration(), $configs);
        if (6 === count($dbSwitcherConfig)) {
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
