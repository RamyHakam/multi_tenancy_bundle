# Troubleshooting Guide - Multi-Tenant Schema Support

This guide covers common issues and solutions when working with the multi-tenant schema support feature.

## Common Issues and Solutions

### 1. Entity Resolution Issues

#### Problem: "Entity not found for current tenant"

**Error Message:**
```
TenantEntityNotFoundException: Entity 'Product' is not available for tenant 'basic_client'
```

**Cause:** The tenant is trying to access an entity that is not configured for their schema.

**Solutions:**

1. **Check tenant configuration:**
   ```bash
   php bin/console hakam:tenant:entities:list basic_client
   ```

2. **Verify entity attributes:**
   ```php
   // Make sure the entity has the correct attribute
   #[TenantSpecific(tenants: ['ecommerce_client', 'retail_client'])]
   class Product implements TenantEntityInterface
   ```

3. **Update tenant configuration:**
   ```yaml
   # Add the tenant to the entity's allowed tenants
   basic_client:
     schema_config:
       entity_loading_strategy: hybrid
       entity_groups: ['ecommerce']
   ```

#### Problem: "Entity conflict detected"

**Error Message:**
```
EntityConflictException: Both shared and tenant-specific versions of 'User' entity exist for tenant 'enterprise_client'
```

**Cause:** Both a shared entity and tenant-specific entity with the same name exist.

**Solutions:**

1. **Use override flag:**
   ```php
   #[TenantSpecific(tenants: ['enterprise_client'], overrideShared: true)]
   class User implements TenantEntityInterface
   ```

2. **Change loading strategy:**
   ```yaml
   enterprise_client:
     schema_config:
       entity_loading_strategy: tenant_override
   ```

3. **Rename one of the entities** to avoid conflicts.

### 2. Migration Issues

#### Problem: "Migration path not found"

**Error Message:**
```
TenantMigrationException: Migration path 'migrations/Tenant/Ecommerce' not found for tenant 'ecommerce_client'
```

**Cause:** The specified migration path doesn't exist or is misconfigured.

**Solutions:**

1. **Create the migration directory:**
   ```bash
   mkdir -p migrations/Tenant/Ecommerce
   ```

2. **Check tenant configuration:**
   ```yaml
   ecommerce_client:
     schema_config:
       migration_paths:
         - 'migrations/Tenant/Ecommerce'  # Ensure path is correct
   ```

3. **Generate initial migration:**
   ```bash
   php bin/console hakam:tenant:migrations:generate ecommerce_client "InitialSchema"
   ```

#### Problem: "Migration version conflict"

**Error Message:**
```
MigrationConflictException: Migration version 'Version20240101000001' exists in both shared and tenant-specific paths
```

**Cause:** Same migration version number used in different migration paths.

**Solutions:**

1. **Use different version numbers** for different migration paths
2. **Rename conflicting migration files**
3. **Use migration groups** to organize migrations better

### 3. Database Connection Issues

#### Problem: "Cannot switch to tenant database"

**Error Message:**
```
TenantConnectionException: Failed to switch to tenant 'enterprise_client' database
```

**Cause:** Database connection configuration is incorrect or database doesn't exist.

**Solutions:**

1. **Verify database exists:**
   ```bash
   php bin/console hakam:tenant:database:list
   ```

2. **Create missing database:**
   ```bash
   php bin/console hakam:tenant:database:create enterprise_client
   ```

3. **Check connection parameters:**
   ```yaml
   enterprise_client:
     database:
       host: localhost
       port: 3306
       name: enterprise_client_db
       user: enterprise_user
       password: enterprise_pass
   ```

### 4. Fixture Loading Issues

#### Problem: "Fixture entity not found"

**Error Message:**
```
TenantFixtureException: Cannot load fixture for entity 'Product' - entity not available for tenant 'basic_client'
```

**Cause:** Trying to load fixtures for entities not available to the tenant.

**Solutions:**

1. **Check fixture entity availability:**
   ```bash
   php bin/console hakam:tenant:entities:list basic_client
   ```

2. **Use tenant-specific fixtures:**
   ```php
   #[TenantFixture(tenants: ['ecommerce_client', 'retail_client'])]
   class ProductFixture extends Fixture
   ```

3. **Load fixtures for correct tenant:**
   ```bash
   php bin/console hakam:tenant:fixtures:load ecommerce_client
   ```

### 5. Performance Issues

#### Problem: "Slow entity resolution"

**Symptoms:** Application becomes slow when switching between tenants or loading entities.

**Cause:** Entity metadata is being regenerated on every request.

**Solutions:**

1. **Enable metadata caching:**
   ```yaml
   hakam_multi_tenancy:
     tenant_schema:
       cache_metadata: true
   ```

2. **Use production cache:**
   ```bash
   php bin/console cache:clear --env=prod
   ```

3. **Optimize entity discovery:**
   ```yaml
   hakam_multi_tenancy:
     tenant_schema:
       auto_discovery: false  # Disable if not needed
   ```

### 6. Configuration Issues

#### Problem: "Invalid tenant schema configuration"

**Error Message:**
```
InvalidTenantSchemaConfigException: Entity loading strategy 'invalid_strategy' is not supported
```

**Cause:** Invalid configuration values in tenant schema config.

**Solutions:**

1. **Use valid loading strategies:**
   - `shared_only`
   - `tenant_specific_only`
   - `hybrid`
   - `tenant_override`

2. **Validate configuration:**
   ```bash
   php bin/console hakam:tenant:config:validate enterprise_client
   ```

3. **Check configuration syntax:**
   ```yaml
   enterprise_client:
     schema_config:
       entity_loading_strategy: hybrid  # Must be valid enum value
   ```

## Debugging Commands

### Entity Information

```bash
# List all entities for a tenant
php bin/console hakam:tenant:entities:list enterprise_client

# Show entity resolution details
php bin/console hakam:tenant:entities:resolve enterprise_client User

# Validate entity configuration
php bin/console hakam:tenant:entities:validate enterprise_client
```

### Migration Information

```bash
# Check migration status
php bin/console hakam:tenant:migrations:status enterprise_client

# List available migration paths
php bin/console hakam:tenant:migrations:paths enterprise_client

# Show migration conflicts
php bin/console hakam:tenant:migrations:conflicts
```

### Configuration Debugging

```bash
# Show tenant configuration
php bin/console hakam:tenant:config:show enterprise_client

# Validate tenant configuration
php bin/console hakam:tenant:config:validate enterprise_client

# List all configured tenants
php bin/console hakam:tenant:list
```

## Performance Optimization

### 1. Entity Metadata Caching

Enable metadata caching to improve performance:

```yaml
hakam_multi_tenancy:
  tenant_schema:
    cache_metadata: true
    cache_driver: redis  # or apcu, filesystem
```

### 2. Lazy Entity Loading

Use lazy loading for tenant-specific entities:

```yaml
hakam_multi_tenancy:
  tenant_entity_manager:
    lazy_loading: true
```

### 3. Connection Pooling

Configure connection pooling for better database performance:

```yaml
doctrine:
  dbal:
    connections:
      tenant:
        pool_size: 10
        max_connections: 50
```

## Best Practices for Troubleshooting

1. **Always check logs first** - Enable debug logging to see detailed error information
2. **Use validation commands** - Run validation commands before deploying changes
3. **Test with minimal configuration** - Start with basic setup and add complexity gradually
4. **Keep backups** - Always backup databases before making schema changes
5. **Monitor performance** - Use profiling tools to identify bottlenecks
6. **Document custom configurations** - Keep track of tenant-specific customizations

## Getting Help

If you encounter issues not covered in this guide:

1. **Check the bundle documentation** for detailed configuration options
2. **Review the example implementations** in the `docs/examples/` directory
3. **Enable debug mode** to get more detailed error messages
4. **Create a minimal reproduction case** to isolate the issue
5. **Check for known issues** in the project's issue tracker