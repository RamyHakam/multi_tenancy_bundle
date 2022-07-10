# Symfony Multi-Tenancy Bundle  


Multi tenancy bundle is an easy way to support multi-tenant databases in your symfony application, Which is a very helpful to extend doctrine to manage multiple databases with one doctrine entity manager where you can switch between all of them in the Runtime
 
 ##### This bundle provides this list of features :  
 
  - Switch between the tenant databases on  the runtime easily by dispatch an event.
  - Supporting different entities mapping for  the main and tenant entities.
  - Provide custom extended doctrine commands to manage tenant databases independently. 
  - Generate and run migrations independently of your main database.
  - Execute bulk migrations for all tenants dbs with one command (soon).
  - Create and prepare tenant database if not exist

  

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
 3. Split your entities in two directories, one for the main database and one for the tenant databases.
          For example  `Main and Tenant `.
 4. Add  the `TenantEntityManager` to your service or controller arguments.  
 5. Dispatch `SwitchDbEvent` with a custom value for your tenant db Identifier.
    `Example new SwitchDbEvent(1)`
 6. You can switch between all tenants dbs just by dispatch the same event with different db identifier.
 7. Now your instance from `TenantEntityManager` is connected to the tenant db with Identifier = 1.
 8. Its recommended having your tenant entities in a different directory from your Main entities.
 9. You can execute doctrine migration commands using our proxy commands for tenant database.
 
        php bin/console tenant:migration:diff 1   # t:m:d 1 for short , To generate migraiton for tenant db  => 1
        
        php bin/console tenant:migration:migrate 1  # t:m:m 1, To run migraitons for tenant db  => 1
        
        # Pass tenant identifier is optional and if it null the command will be executed on the defualt tenant db. 
        # You can use the same options here for the same doctrine commands.
        
### Note:
  All the doctrine migration commands and files is generated and executed especially for tenant databases independent from the main db migrations, 
   Thanks for Doctrine migration bundle v3+ .
   
### Usage Example 
 You can dispatch the event where ever you want to switch to a custom db
   
   ```php
      namespace App\Controller;
    
    
      use Symfony\Component\EventDispatcher\EventDispatcherInterface;  
      use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
      use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
      use Doctrine\ORM\EntityManagerInterface;
      use App\Entity\Tenant\TenantEntityExample;
      use App\Entity\Main\MainLog;


       public class AccountController extends AbstractController
       {
    
           /**
            * @var EntityManagerInterface
            */
           private $mainEntityManager;
           /**
            * @var TenantEntityManager
            */
           private $tenantEntityManager;
           /**
            * @var EventDispatcherInterface
            */
           private $dispatcher;
    
        public function __construct(
                EntityManagerInterface $entityManager,
                TenantEntityManager $tenantEntityManager,
                EventDispatcherInterface $dispatcher)
            {
                $this->mainEntityManager = $entityManager;
                $this->tenantEntityManager = $tenantEntityManager;
                $this->dispatcher = $dispatcher;
            }
    
        public function updateTenantAccount(TenantEntityExample $tenantEntityExample)
            {
                   // switch connection to tenant account database
    
                  $switchEvent = new SwitchDbEvent($tenantEntityExample->getDbConfigId());
                  $this->dispatcher->dispatch($switchEvent);
    
                  // now $tenantEntityManager is connected to custom tenant db
    
                  $tenantEntityExample->updateSomthing();
                  $this->tenantEntityManager->persist($tenantEntityExample);
                  $this->tenantEntityManager->persist();
    
                  //log update action in our main db 
    
                  $mainLog =new MainLog($tenantEntityExample->getId());
                  $this->mainEntityManager->persist($mainLog);
                  $this->mainEntityManager->flush();
            }
    
           //..
       }
   ```
 ### Configuration
 
 In this example below you can find the list of all configuration parameters required witch you should create in
   `config/packages/hakam_multi_tenancy.yaml` with this configuration:
 ``` yaml 
hakam_multi_tenancy:
  tenant_database_className:  App\Entity\Main\TenantDbConfig                # tenant dbs configuration Class Name
  tenant_database_identifier: id                                            # tenant db column name to get db configuration
  tenant_connection:                                                        # tenant entity manager connection configuration
    url:      mysql://root:null@127.0.0.1:3306/tenant1?server_version=5.7   # tenant mysql connection URL (overrides all independent parameters below)
    host:     127.0.0.1
    port:     3306
    driver:   pdo_mysql
    charset:  utf8 
    dbname:   tanent1                                                       # default tenant database to init the tenant connection
    user:     root                                                          # default tenant database username
    password: null                                                          # default tenant database password
    server_version: 5.7                                                     # mysql server version

  tenant_migration:                                                         # tenant db migration configurations, Its recommended to have a different migration for tenants dbs than you main migration config
    tenant_migration_namespace: Application\Migrations\Tenant
    tenant_migration_path: migrations/Tenant
  tenant_entity_manager:                                                    # tenant entity manger configuration , which is used to manage tenant entities
    mapping:                                                  
      type:   annotation                                                    # mapping type default annotation                                                       
      dir:   '%kernel.project_dir%/src/Entity/Tenant'                       # directory of tenant entities, it could be different from main directory                                           
      prefix: App\Entity\Tenant                                             # tenant entities prefix  ex "App\Entity\Tenant"
      alias:   Tenant                                                       # tenant entities alias  ex "Tenant"
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
