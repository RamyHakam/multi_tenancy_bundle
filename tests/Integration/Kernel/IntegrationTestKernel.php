<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Hakam\MultiTenancyBundle\HakamMultiTenancyBundle;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantDbConfig;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class IntegrationTestKernel extends Kernel
{
    private static int $instanceCounter = 0;

    /**
     * A token that is unique per PHP process (PID + a high-resolution start
     * time). It anchors every kernel's cache dir to *this* run, so a fresh run
     * can never reuse a directory left on disk by a previous run — which would
     * otherwise leak persisted filesystem cache (e.g. cache.app pools) between
     * runs and break tenant cache-isolation assertions.
     */
    private static ?string $runToken = null;

    private int $instanceId;
    private array $multiTenancyConfig;
    /** @var callable|null */
    private $serviceRegistrar;

    public function __construct(array $multiTenancyConfig = [], ?callable $serviceRegistrar = null)
    {
        parent::__construct('test', false);
        $this->instanceId = ++self::$instanceCounter;
        $this->multiTenancyConfig = $multiTenancyConfig;
        $this->serviceRegistrar = $serviceRegistrar;
    }

    private static function runToken(): string
    {
        if (self::$runToken === null) {
            self::$runToken = getmypid() . '_' . hrtime(true);
        }

        return self::$runToken;
    }

    public function shutdown(): void
    {
        $instanceDir = $this->getInstanceDir();

        parent::shutdown();

        // Remove this kernel's whole working dir (cache + log) so persisted
        // pools (FilesystemAdapter behind cache.app) never leak into a later
        // kernel or a later run, and temp dirs don't accumulate across runs.
        if (is_dir($instanceDir)) {
            (new Filesystem())->remove($instanceDir);
        }
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
                    'driver' => 'pdo_sqlite',
                    'charset' => 'utf8',
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

            if ($this->serviceRegistrar !== null) {
                ($this->serviceRegistrar)($container);
            }
        });
    }

    public function getCacheDir(): string
    {
        return $this->getInstanceDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getInstanceDir() . '/log';
    }

    private function getInstanceDir(): string
    {
        return sys_get_temp_dir() . '/hakam_integration_' . $this->getConfigHash();
    }

    private function getConfigHash(): string
    {
        // Anchor to a per-process run token plus a monotonic per-instance id
        // (not spl_object_id, which PHP reuses after an object is destroyed), so
        // each booted kernel gets its own cache dir that is never shared with
        // another kernel in this run nor reused from a previous run.
        return self::runToken() . '_' . $this->instanceId
            . '_' . substr(md5(serialize($this->multiTenancyConfig)), 0, 8);
    }
}
