<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Doctrine\Common\Annotations\AnnotationReader;
use Hakam\MultiTenancyBundle\HakamMultiTenancyBundle;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantDbConfig;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class IntegrationTestKernel extends Kernel
{
    private array $multiTenancyConfig;

    public function __construct(array $multiTenancyConfig = [])
    {
        parent::__construct('test', true);
        $this->multiTenancyConfig = $multiTenancyConfig;
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DoctrineMigrationsBundle(),
            new HakamMultiTenancyBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->register('annotation_reader', AnnotationReader::class);

            $container->loadFromExtension('framework', [
                'secret' => 'test_secret',
                'test' => true,
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'default_connection' => 'default',
                    'connections' => [
                        'default' => [
                            'driver' => 'pdo_sqlite',
                            'url' => 'sqlite:///:memory:',
                        ],
                    ],
                ],
                'orm' => [
                    'default_entity_manager' => 'default',
                    'auto_generate_proxy_classes' => true,
                    'enable_lazy_ghost_objects' => true,
                    'enable_native_lazy_objects' => true,
                    'entity_managers' => [
                        'default' => [
                            'connection' => 'default',
                            'mappings' => [
                                'TestMain' => [
                                    'type' => 'attribute',
                                    'dir' => __DIR__ . '/../Fixtures/Entity',
                                    'prefix' => 'Hakam\\MultiTenancyBundle\\Tests\\Integration\\Fixtures\\Entity',
                                    'is_bundle' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('doctrine_migrations', [
                'migrations_paths' => [
                    'DoctrineMigrations' => '%kernel.project_dir%/migrations',
                ],
            ]);

            $tenantEntityDir = realpath(__DIR__ . '/../Fixtures/Entity') ?: __DIR__ . '/../Fixtures/Entity';

            $container->loadFromExtension('hakam_multi_tenancy', array_merge([
                'tenant_database_className' => TenantDbConfig::class,
                'tenant_database_identifier' => 'id',
                'tenant_connection' => [
                    'url' => 'sqlite:///:memory:',
                    'host' => 'localhost',
                    'port' => '0',
                    'driver' => 'pdo_sqlite',
                    'charset' => 'utf8',
                    'server_version' => '3.39',
                ],
                'tenant_migration' => [
                    'tenant_migration_namespace' => 'DoctrineMigrations\\Tenant',
                    'tenant_migration_path' => '%kernel.project_dir%/tests/migrations/Tenant',
                ],
                'tenant_entity_manager' => [
                    'tenant_naming_strategy' => 'doctrine.orm.naming_strategy.default',
                    'mapping' => [
                        'type' => 'attribute',
                        'dir' => $tenantEntityDir,
                        'prefix' => 'Hakam\\MultiTenancyBundle\\Tests\\Integration\\Fixtures\\Entity',
                        'alias' => 'TestTenant',
                        'is_bundle' => false,
                    ],
                ],
            ], $this->multiTenancyConfig));
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/hakam_integration_' . spl_object_id($this) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/hakam_integration_' . spl_object_id($this) . '/log';
    }
}
