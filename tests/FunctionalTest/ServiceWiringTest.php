<?php


namespace Hakam\MultiTenancyBundle\Tests\FunctionalTest;



use Hakam\MultiTenancyBundle\Services\DbConfigService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ServiceWiringTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    private  $container;

    public function testDbConfigServiceWiring(): void
    {
        /** @var DbConfigService $dbConfigService */
        $dbConfigService = $this->container->get('tenant.diff.command');

        self::assertInstanceOf(DbConfigService::class, $dbConfigService);
    }

    protected function setUp(): void
    {
        $config = [
            'tenant_database_repository' => 'test',
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
                    'tenant_migration_namespace' => 'Application\Migrations\Tenant',
                    'tenant_migration_path' => 'migrations/Tenant'
                ],
            'tenant_entity_manager' =>
                [
                    'mapping' =>
                        [
                            'type' => 'annotation',
                            'dir' => '%kernel.project_dir%/src/Entity/Tenant',
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