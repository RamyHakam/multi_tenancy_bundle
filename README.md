# Symfony Multi-Tenancy Bundle  ![](https://github.com/RamyHakam/multi_tenancy_bundle/workflows/action_status/badge.svg)
![Multi-Tenancy Bundle (Desktop Wallpaper)](https://github.com/RamyHakam/multi_tenancy_bundle/assets/17661342/eef23e6a-881c-4817-b7b8-8b7cec913154)


The Multi Tenancy Bundle for Symfony is a convenient solution for incorporating multi-tenant databases in your Symfony application. It simplifies the process of using Doctrine to handle multiple databases with a single entity manager, allowing you to switch between them during runtime.

This bundle comes with a range of features, including the ability to effortlessly switch between tenant databases by triggering an event. Additionally, it supports distinct entity mapping for both the main and tenant entities. It also includes custom extended Doctrine commands for managing tenant databases independently, as well as the capability to generate and execute migrations for each database separately.

In the near future, you will also be able to execute bulk migrations for all tenant databases with a single command. Additionally, the bundle allows you to create and prepare a tenant database if it doesn't already exist

### Supported Databases:
- MySQL / MariaDB   
- PostgreSQL
- SQLite

  

### Installation

This bundle requires 
- [Symfony](https://symfony.org/) v5+ to run.
- [Doctrine Bundle](https://github.com/doctrine/DoctrineBundle)
- [Doctrine Migration Bundle](https://github.com/doctrine/DoctrineMigrationsBundle) v3+ to run 


Install using Composer

```sh
$ composer require hakam/multi-tenancy-bundle
``` 
 ### Using the Bundle
 ###### The idea behind this bundle is simple,You have a main database and  multi-tenant databases So: 
 1. Create specific entity witch should implement `TenantDbConfigurationInterface`. In your main database to save all tenant databases configurations. 
 2. You can use the `TenantDbConfigTrait` to implement the full required  db config entity fields .
3. You can use the `TimestampableTrait` to add `createdAt` and `updatedAt` fields to your entity then you should add `#[ORM\HasLifecycleCallbacks]` attribute to your entity class.
4. Split your entities in two directories, one for the main database and one for the tenant databases.
          For example  `Main and Tenant `.
5. Split the migrations in two directories, one for the main database and one for the tenant databases.
          For example  `Main and Tenant `.
6. Update your doctrine config to use the new directories for entities and migrations.See the example below.
```yaml
# config/packages/doctrine.yaml
doctrine:
  dbal:
    default_connection: default
    url: '%env(resolve:DATABASE_URL)%'
    orm:
      default_entity_manager: default #set the default entity manager to use the main database 
      entity_managers:   
          default:
            connection: default #set the default entity manager  to use the default connection
            naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
            auto_mapping: true
            mappings:
              App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity/Main'
                prefix: 'App\Entity\Main'
                alias: App
                
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations\Main': '%kernel.project_dir%/src/Migrations/Main'
``` 
#### You MUST  config the default connection and the default entity manager to use the main database. see the example above, for the complete configuration
####  You just need to update the config for the main EntityManager , Then the bundle will handle the rest for you.
7. Add  the `TenantEntityManager` to your service or controller arguments.  
8. Dispatch `SwitchDbEvent` with a custom value for your tenant db Identifier.
    `Example new SwitchDbEvent(1)`
9. You can switch between all tenants dbs just by dispatch the same event with different db identifier.
10. Now your instance from `TenantEntityManager` is connected to the tenant db with Identifier = 1.
11. ts recommended having your tenant entities in a different directory from your Main entities.
12. You can execute doctrine migration commands using our proxy commands for tenant database.

        php bin/console tenant:database:create   # t:d:c  for short , To create and exceute  migraiton for non exist tenant db

        php bin/console tenant:migration:diff 1   # t:m:d 1 for short , To generate migraiton for tenant db  => 1
        
        php bin/console tenant:migration:migrate 1  # t:m:m 1, To run migraitons for tenant db  => 1
        
        # Pass tenant identifier is optional and if it null the command will be executed on the defualt tenant db. 
        # You can use the same options here for the same doctrine commands.
  ### Example
 You can check  this  project example   [Multi-Tenant Bundle Example](https://github.com/RamyHakam/multi-tenancy-project-example) to see how to use the bundle.

### Notes:
  All the doctrine migration commands and files is generated and executed especially for tenant databases independent of the main db migrations, 
   Thanks for Doctrine migration bundle v3+ .

   All the tenant databases migrations will be saved in the same directory you configured for tenant entities.

   All the tenant databases has the same host ,username and password as the main database. we will support different host, username and password for  tenant dbs in the near future.

### Get Started Example
1. After configure the bundle as we mentioned above , you can use the following steps to get started.
2. You need to generate the main database migration with the new entity first.
        example  ` php bin/console doctrine:migrations:diff`
3. Then migrate the main database.
        example  ` php bin/console doctrine:migrations:migrate`
4. Now you are ready to create new tenant databases and switch between them.
5.  To create a new tenant database and execute the migration for it. Add the `TenantDbConfig` entity to your main database. then you can use the following command.
        example  ` php bin/console tenant:database:create` this command will create a new tenant database and execute the migration for it.
6. To switch to a tenant database.
        example  ` $this->dispatcher->dispatch(new SwitchDbEvent($tenantDb->getId()));` then you can use the `TenantEntityManager` to execute your queries.
7.  You can add a relation between your tenant entity  and the `TenantDbConfig` entity to get the tenant db config easily. 
```php
      <?php
      public Class AppController extends AbstractController
      {
      public function __construct(
        private EntityManagerInterface $mainEntityManager,
        private TenantEntityManager $tenantEntityManager,
        private EventDispatcherInterface $dispatcher
    ) {
    }
     public function switchToLoggedInUserTenantDb(): void
     {
        $this->dispatcher->dispatch(new SwitchDbEvent($this->getUser()->getTenantDbConfig()->getId()));
        // Now you can use the tenant entity manager to execute your queries.
     }
    }
```      
8. You can use the custom doctrine commands for tenant databases to perform the same doctrine commands on tenant databases.
        example  ` php bin/console tenant:migration:diff 1` to generate migration for tenant db with id = 1.
        example  ` php bin/console tenant:migration:migrate 1` to execute migration for tenant db with id = 1.
9. Now You can work with the `TenantEntityManager` as you work with the main entity manager.
10. You can now add or delete entities from the main or tenant databases and generate migrations for each database separately.
```php
      <?php

namespace App\Controller;

use App\Entity\Main\TenantDbConfig;
use Hakam\MultiTenancyBundle\Services\DbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Tenant\TestEntity;
use App\Entity\Main\MainEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MultiTenantController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $mainEntityManager,
        private TenantEntityManager $tenantEntityManager,
        private EventDispatcherInterface $dispatcher,
        private DbService $dbService,
    ) {
    }
    /**
     * An example of how to switch and update tenant databases
     */
    #[Route('/test_db', name: 'app_test_db')]
    public function testDb(EntityManagerInterface $entityManager)
    {

        $tenantDbConfigs = $this->mainEntityManager->getRepository(TenantDbConfig::class)->findAll();

        foreach ($tenantDbConfigs as $tenantDbConfig) {
            // Dispatch an event with the index ID for the entity that contains the tenant database connection details.
            $switchEvent = new SwitchDbEvent($tenantDbConfig->getId());
            $this->dispatcher->dispatch($switchEvent);

            $tenantEntity1 = new TestEntity();
            $tenantEntity1->setName($tenantDbConfig->getDbName());

            $this->tenantEntityManager->persist($tenantEntity1);
            $this->tenantEntityManager->flush();
        }

        // Add a new entity to the main database.
        $mainLog = new MainEntity();
        $mainLog->setName('mainTtest');
        $this->mainEntityManager->persist($mainLog);
        $this->mainEntityManager->flush();

        return new JsonResponse();
    }
}

   ```
 ### Configuration
 
 In this example below you can find the list of all configuration parameters required witch you should create in
   `config/packages/hakam_multi_tenancy.yaml` with this configuration:
 ``` yaml 
hakam_multi_tenancy:
  tenant_database_className:  App\Entity\Main\TenantDbConfig    # tenant dbs configuration Class Name
  tenant_database_identifier: id                                # tenant db column name to get db configuration
  tenant_connection:                                            # tenant entity manager connection configuration
    host:     127.0.0.1
    port:     3306                                              # default is 3306
    driver:   pdo_mysql 
    charset:  utf8 
    server_version: 5.7                                         # mysql server version

  tenant_migration:                                             # tenant db migration configurations, Its recommended to have a different migration for tenants dbs than you main migration config
    tenant_migration_namespace: Application\Migrations\Tenant
    tenant_migration_path: migrations/Tenant
  tenant_entity_manager:                                        # tenant entity manger configuration , which is used to manage tenant entities
    tenant_naming_strategy:                                       # tenant entity manager naming strategy
    mapping:                                                  
      type:   attribute                                          # mapping type default annotation                                                       
      dir:   '%kernel.project_dir%/src/Entity/Tenant'           # directory of tenant entities, it could be different from main directory                                           
      prefix: App\Entity\Tenant                                 # tenant entities prefix  ex "App\Entity\Tenant"
      alias:   Tenant                                           # tenant entities alias  ex "Tenant"
 ```
### Suggested Patterns For Use

#### User
Store the users current tenant ID in the session or the User entity.
This allows you to get the current tenant at any time.

### Tenant Interface
Implement an interface on all your tenant entities. This allows you to identify if an entity is assicated with a
tenant object. Then you can get the current tenant ID from the current user, and switch the entity manager to the tenant DB.
```php

namespace App\Entity\Tenant;

use App\Model\OrgActivitySuperclass;
use App\Repository\Tenant\OrgActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;

#[ORM\Entity(repositoryClass: OrgActivityRepository::class)]
class OrgActivity extends OrgActivitySuperclass implements TenantEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
           
}

```

#### Custom Controller
Extend your base controller class to overwrite some of the normal controler functions for presistance.
```php
<?php

namespace App\Controller;

use App\Entity\Main\Tenant;
use App\Entity\Tenant\Organisation;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Umbrella\CoreBundle\Controller\BaseController;

abstract class DbSwitcherController extends BaseController
{
    public function __construct(private EventDispatcherInterface $eventDispatcher, private TenantEntityManager $tenantEntityManager)
    {
    }
    
    protected function switchDb(Tenant $tenant): Organisation
    {

        // Switch the TenantEntityManager to the current tenant.
        $event = new SwitchDbEvent($tenant->getId());
        $this->eventDispatcher->dispatch($event);

        // Optional depending on your usage, here we return the top entity whenever we switch to a new Tenant DB.
        $organisation = $this->tenantEntityManager->getRepository(Organisation::class)
            ->findOneIdByXeroOrganisationId($tenant->getXeroOrganisationId());

        return $organisation;
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function findOrNotFound(string $className, $id)
    {

        $em = $this->em();

        $reflection = new \ReflectionClass($className);

        if ($reflection instanceof TenantEntityInterface) {
            $em = $this->tenantEntityManager;
        }

        $e = $em->find($className, $id);
        $this->throwNotFoundExceptionIfNull($e);

        return $e;
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function persistAndFlush($entity): void
    {
        if ($entity instanceof TenantEntityInterface) {
            $this->tenantEntityManager->persist($entity);
            $this->tenantEntityManager->flush();
            return;
        }
        $this->em()->persist($entity);
        $this->em()->flush();
    }

    /**
     * Override parent method to check if the entity is a Tenant entity or main entity. Return which ever is appropriate.
     */  
    protected function removeAndFlush($entity): void
    {
        if ($entity instanceof TenantEntityInterface) {
            $this->tenantEntityManager->remove($entity);
            $this->tenantEntityManager->flush();
            return;
        }
        $this->em()->remove($entity);
        $this->em()->flush();
    }
}
```

#### Custom Value Resolver
Symfony uses an Entity Value Resolver to load entities assicated with parameters passed to controller actions.
https://symfony.com/doc/current/controller/value_resolver.html

This resolver is essential to making sure your IsGranted and other Security actions work in the controllers.

Create a custom entity value resolver to switch based on a ReflectionClass of the entity (based on the TenantEntityInterface).

You can copy the value rosolver here: https://github.com/symfony/symfony/blob/6.2/src/Symfony/Bridge/Doctrine/ArgumentResolver/EntityValueResolver.php

And modify it to exclude non TenantEntityIntrface objects. Then switch DB based on the current user tenant.

```php
<?php

namespace App\ValueResolver;

namespace App\ValueResolver;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
        private TenantEntityManager $tenantEntityManager,
        private MapEntity $defaults = new MapEntity(),
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->withDefaults($this->defaults, $argument->getType());

        if (!$options->class || $options->disabled) {
            return [];
        }

        $reflectionClass = new \ReflectionClass($options->class);

        if(!$reflectionClass instanceof TenantEntityInterface){
            return [];
        }

        $currentTenant = $this->security->getUser()->getCurrentTenant();

        $switchEvent = new SwitchDbEvent($currentTenant->getId());
        $this->eventDispatcher->dispatch($switchEvent);

        $manager = $this->tenantEntityManager;

        if (!$manager instanceof TenantEntityManager) {
            return [];
        }

        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($manager, $request, $options)) {
                $message = sprintf(' The expression "%s" returned null.', $options->expr);
            }
            // find by identifier?
        } elseif (false === $object = $this->find($manager, $request, $options, $argument->getName())) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $manager)) {
                return [];
            }
            try {
                $object = $manager->getRepository($options->class)->findOneBy($criteria);
            } catch (NoResultException|ConversionException) {
                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException(sprintf('"%s" object not found by "%s".', $options->class, self::class).$message);
        }

        return [$object];
    }

    private function getManager(?string $name, string $class): ?ObjectManager
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        try {
            $manager = $this->registry->getManager($name);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $manager->getMetadataFactory()->isTransient($class) ? null : $manager;
    }

    private function find(ObjectManager $manager, Request $request, MapEntity $options, string $name): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);
        if (false === $id || null === $id) {
            return $id;
        }

        if ($options->evictCache && $manager instanceof EntityManagerInterface) {
            $cacheProvider = $manager->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($options->class, $id)) {
                $cacheProvider->evictEntity($options->class, $id);
            }
        }

        try {
            return $manager->getRepository($options->class)->find($id);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }

    private function getIdentifier(Request $request, MapEntity $options, string $name): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = sprintf($field, $name);
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if (null !== $options->id) {
            $name = $options->id;
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name) ?? ($options->stripNull ? false : null);
        }

        if (!$options->id && $request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ObjectManager $manager): array
    {
        if (null === $mapping = $options->mapping) {
            $mapping = $request->attributes->keys();
        }

        if ($mapping && \is_array($mapping) && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }

        if (!$mapping) {
            return [];
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if (\is_string($options->id) && null === $request->attributes->get($options->id)) {
            return [];
        }

        $criteria = [];
        $metadata = $manager->getClassMetadata($options->class);

        foreach ($mapping as $attribute => $field) {
            if (!$metadata->hasField($field) && (!$metadata->hasAssociation($field) || !$metadata->isSingleValuedAssociation($field))) {
                continue;
            }

            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(ObjectManager $manager, Request $request, MapEntity $options): ?object
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $repository = $manager->getRepository($options->class);
        $variables = array_merge($request->attributes->all(), ['repository' => $repository]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (NoResultException|ConversionException) {
            return null;
        }
    }
}
```

Add the value resolver as a service.
```yaml
services:
    # Priority should fire before the default EntityValueResolver
    App\ValueResolver\TenantEntityValueResolver:
        tags:
            - { name: controller.argument_value_resolver, priority: 150 }
```

### Contribution

Want to contribute? Great!
 - Fork your copy from the repository
 - Add your new Awesome features 
 - Write MORE Tests
 - Create a new Pull request 

License
----

# MIT
**Free Software, Hell Yeah!**
