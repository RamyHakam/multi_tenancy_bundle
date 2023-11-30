<?php

namespace Hakam\MultiTenancyBundle\Tests\Functional;

use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ServiceWiringTest extends TestCase
{
    private ContainerInterface $container;

    public function testDbConfigServiceWiring(): void
    {
        $tenantEntityManager = $this->container->get('tenant_entity_manager');

        self::assertInstanceOf(TenantEntityManager::class, $tenantEntityManager);
    }

    protected function setUp(): void
    {
        $config = [
            'tenant_database_identifier' => 'id',

            'tenant_connection' => [
                'host' => '127.0.0.1',
                'driver' => 'pdo_mysql',
                'charset' => 'utf8',
                'dbname' => 'tenant0',
                'user' => 'root',
                'password' => null
            ],
            'tenant_migration' =>
                [
                    'tenant_migration_namespace' => 'Test\Application\Migrations\Tenant',
                    'tenant_migration_path' => 'tests/migrations/Tenant'
                ],
            'tenant_entity_manager' =>
                [
                    'tenant_naming_strategy' => 'doctrine.orm.naming_strategy.default',
                    'dql' =>
                        [
                            'string_functions' =>
                                [
                                    'FIELD' => 'Tenant\Functions\FieldFunction'
                                ]
                        ],
                    'mapping' =>
                        [
                            'type' => 'annotation',
                            'dir' => '%kernel.project_dir%/tests',
                            'prefix' => 'Tenant',
                            'alias' => 'Tenant'
                        ]
                ]
        ];
        $kernel = new MultiTenancyBundleTestingKernel($config);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }
}
