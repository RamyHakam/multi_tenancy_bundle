---
title:  "Getting Started"
---
Get up and running with the Symfony Multi-Tenancy Bundle in just a few steps. This guide combines installation, configuration, and first-steps usage into one cohesive workflow.

## 1. Install the Bundle

### With Symfony Flex (Recommended)

```bash
composer require hakam/multi-tenancy-bundle
```

* Flex auto-registers the bundle in `config/bundles.php`.
* It publishes a default config to `config/packages/hakam_multi_tenancy.yaml`.
* Scaffolds key directories:

```text
src/Entity/Main/
src/Entity/Tenant/
migrations/Main/
migrations/Tenant/
```

### Manual Setup (Without Flex)

```bash
composer require hakam/multi-tenancy-bundle
```

1. Register in `config/bundles.php`:

```php
return [
// ...
Hakam\MultiTenancyBundle\HakamMultiTenancyBundle::class => ['all' => true],
];
```
2. Create `config/packages/hakam_multi_tenancy.yaml` and add:

```yaml
hakam_multi_tenancy:
  tenant_database_className:  App\Entity\Main\TenantDbConfig
  tenant_database_identifier: id

  tenant_connection:
    host: 127.0.0.1
    port: 3306
    driver: pdo_mysql
    charset: utf8
    server_version: 5.7

  tenant_migration:
    tenant_migration_namespace: DoctrineMigrations\Tenant
    tenant_migration_path: migrations/Tenant

  tenant_entity_manager:
    tenant_naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
    mapping:
      type: attribute
      dir: '%kernel.project_dir%/src/Entity/Tenant'
      prefix: App\Entity\Tenant
      alias: Tenant
```
3. Create the scaffold directories manually if not present:

```text
src/Entity/Main/
src/Entity/Tenant/
migrations/Main/
migrations/Tenant/
```

## 2. Configure Doctrine

In `config/packages/doctrine.yaml`, ensure separate mapping for Main and default EM:

```yaml
doctrine:
  dbal:
    default_connection: default
    url: '%env(resolve:DATABASE_URL)%'

  orm:
    default_entity_manager: default
    entity_managers:
      default:
        connection: default
        auto_mapping: false
        mappings:
          Main:
            is_bundle: false
            dir: '%kernel.project_dir%/src/Entity/Main'
            prefix: 'App\\Entity\\Main'
            alias: App
```

And migrations in `doctrine_migrations.yaml`:

```yaml
doctrine_migrations:
  migrations_paths:
    'DoctrineMigrations\\Main': '%kernel.project_dir%/src/Migrations/Main'
```

## 3. Run Main Database Migrations

Generate and apply the initial schema for your main database:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Fetch the new `tenant_db_config` table to store tenant connection details.

## 4. Create and Migrate Tenant Databases

### Create a Tenant DB

```bash
# Create database for a specific tenant
php bin/console tenant:database:create --dbid=<id>

# Create all missing tenant databases
php bin/console tenant:database:create --all

# Default: create all missing databases (backward compatible)
php bin/console tenant:database:create
```

**Options:**

- `--dbid=<id>`: Create database for a specific tenant ID
- `--all`: Explicitly create all missing tenant databases
- No options: Default behavior (creates all missing databases)

The command uses the `TenantDbConfig` record with the specified ID to provision a new database and update its status to `DATABASE_CREATED`. If the database already exists, it will be skipped.

### Apply Migrations to Tenants

```bash
# For a specific tenant:
php bin/console tenant:migration:migrate update --dbid=<id>

# Bulk update all tenants:
php bin/console tenant:migration:migrate update --all
```

## 5. Load Tenant Fixtures

After migrations, seed demo or test data:

```bash
php bin/console tenant:fixtures:load --dbid=<id> --append
php bin/console tenant:fixtures:load --all
```

Supports options: `--group=`, `--purge-with-truncate`, `--no-interaction`.

## 6. Scaffold and Persist Tenant Entities

In your controller or service:

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

## Troubleshooting & Tips

* **Connection Refused**: Verify your tenant host/port credentials in `TenantDbConfig`.
* **Migration Not Found**: Ensure your `migrations/Tenant` directory is configured in `hakam_multi_tenancy.yaml`.
* **Fixture Errors**: Only classes with `#[TenantFixture]` will run under `tenant:fixtures:load`.

You’re now ready to build and scale multi-tenant functionality in Symfony using the Multi-Tenancy Bundle!
