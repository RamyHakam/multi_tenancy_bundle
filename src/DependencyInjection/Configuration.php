<?php


namespace Hakam\DoctrineDbSwitcherBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @category Database
 * @author   Ramy Hakam <ramyhakam1@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('hakam_doctrine_db_switcher');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->variableNode('tenant_database_className')->info('Tenant dbs configuration Class Name')->defaultValue('TenantDb')->end()
            ->variableNode('tenant_database_identifier')->info('tenant db column name to get db configuration')->defaultValue('id')->end()
            ->end()
            ->children()
            ->arrayNode('tenant_connection')->info('tenant entity manager connection configuration')
            ->children()
            ->variableNode('host')->defaultValue('127.0.0.1')->end()
            ->variableNode('driver')->defaultValue('pdo_mysql')->end()
            ->variableNode('charset')->defaultValue('utf8')->end()
            ->variableNode('server_version')->defaultValue('5.7')->end()
            ->variableNode('dbname')->info('default tenant database to init the tenant connection')->end()
            ->variableNode('user')->info('default tenant database username')->end()
            ->variableNode('password')->info('default tenant database password')->defaultNull()->end()
            ->end()
            ->end()
            ->end()
            ->children()
            ->arrayNode('tenant_migration')
            ->info('tenant db migration configurations, Its recommended to have a different migration for tenants dbs than you main migration config ')
            ->children()
            ->variableNode('tenant_migration_namespace')->end()
            ->variableNode('tenant_migration_path')->end()
            ->end()
            ->end()
            ->end()
            ->children()
            ->arrayNode('tenant_entity_manager')
            ->info('tenant entity manger configuration , which is used to manage tenant entities')
            ->children()
            ->arrayNode('mapping')
            ->info('tenant Entity Manager mapping configuration, Its recommended to have a different mapping config than your main entity config')
            ->children()
            ->variableNode('type')->defaultValue('annotation')->info('mapping type default annotation')->end()
            ->variableNode('dir')->defaultValue('%kernel.project_dir%/src/Entity/Tenant')->info('directory of tenant entities, it could be different from main directory')->end()
            ->variableNode('prefix')->info('Tenant entities prefix example " #App\Entity\Tenant" ')->end()
            ->variableNode('alias')->info('Tenant entities alias example " Tenant " ')->end()
            ->end()
            ->end()
            ->end();
        return $treeBuilder;
    }
}