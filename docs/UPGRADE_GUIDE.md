# Upgrade Guide - Multi-Tenant Schema Support

This guide helps you upgrade from existing shared entity setups to the new multi-tenant schema support feature.

## Overview

The multi-tenant schema support feature introduces attribute-based entity resolution, allowing you to define tenant-specific entities while maintaining backward compatibility with existing shared entity setups.

## Pre-Upgrade Checklist

Before starting the upgrade process:

- [ ] **Backup all databases** (main and tenant databases)
- [ ] **Review current entity structure** and identify which entities should remain shared vs become tenant-specific
- [ ] **Test the upgrade process** on a development/staging environment first
- [ ] **Document current tenant configurations** for reference
- [ ] **Ensure all tenants are using the latest shared migrations**

## Upgrade Scenarios

### Scenario 1: Keep All Entities Shared (Minimal Changes)

If you want to keep your current setup with all entities shared across tenants:

#### Step 1: Add TenantShared Attributes

Add the `#[TenantShared]` attribute to your existing tenant entities:

```php
// Before
namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;

#[ORM\Entity]
class User implements TenantEntityInterface
{
    // ... existing code
}

// After
namespace App\Entity\Tenant;

use Doctrine\ORM\Mapping as ORM;
use Hakam\MultiTenancyBundle\Model\TenantEntityInterface;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;

#[ORM\Entity]
#[TenantShared] // Add this attribute
class User implements TenantEntityInterface
{
    // ... existing code (no changes needed)
}
```

#### Step 2: Update Bundle Configuration

Update your bundle configuration to use the new schema support:

```yaml
# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    # ... existing configuration
    
    tenant_schema:
        auto_discovery: true
        default_loading_strategy: 'shared_only'  # Keep current behavior
        cache_metadata: true
```

#### Step 3: Run Migration for TenantSchemaConfig

Create and run the migration for the new TenantSchemaConfig table:

```bash
# Generate migration for main database
php bin/console doctrine:migrations:generate --configuration=config/packages/doctrine_migrations_main.yaml

# Add TenantSchemaConfig table creation to the generated migration
# Then execute it
php bin/console doctrine:migrations:migrate --configuration=config/packages/doctrine_migrations_main.yaml
```

#### Step 4: Test the Upgrade

```bash
# Verify entities are still working
php bin/console hakam:tenant:entities:list your_tenant_name

# Test tenant switching
php bin/console hakam:tenant:switch your_tenant_name
```

### Scenario 2: Migrate Some Entities to Tenant-Specific

If you want to make some entities tenant-specific while keeping others shared:

#### Step 1: Identify Entity Categories

Categorize your entities:
- **Keep Shared**: Core entities needed by all tenants (e.g., User, Settings)
- **Make Tenant-Specific**: Business-specific entities (e.g., Product, Order, CustomField)

#### Step 2: Add Appropriate Attributes

```php
// Shared entity (available to all tenants)
#[ORM\Entity]
#[TenantShared]
class User implements TenantEntityInterface
{
    // ... existing code
}

// Tenant-specific entity (only for specific tenants)
#[ORM\Entity]
#[TenantSpecific(tenants: ['ecommerce_client', 'retail_client'])]
class Product implements TenantEntityInterface
{
    // ... existing code
}
```

#### Step 3: Create Tenant Configurations

Create schema configurations for tenants that need specific entities:

```php
// In a migration or data fixture
$schemaConfig = new TenantSchemaConfig();
$schemaConfig->setTenantIdentifier('ecommerce_client');
$schemaConfig->setEntityLoadingStrategy(EntityLoadingStrategy::HYBRID);
$schemaConfig->setEntityGroups(['ecommerce']);
$schemaConfig->setMigrationPaths(['migrations/Tenant/Ecommerce']);

$entityManager->persist($schemaConfig);
$entityManager->flush();
```

#### Step 4: Organize Migration Files

Reorganize your migration files:

```
migrations/
├── Main/                           # Main database migrations
│   └── Version20240101000000.php   # TenantSchemaConfig table
├── Tenant/
│   ├── Shared/                     # Move existing tenant migrations here
│   │   └── Version20240101000001.php
│   └── Ecommerce/                  # New tenant-specific migrations
│       └── Version20240102000001.php
```

#### Step 5: Update Tenant Database Schemas

For tenants that need new entities, run the appropriate migrations:

```bash
# Apply shared migrations (existing entities)
php bin/console hakam:tenant:migrate ecommerce_client --shared

# Apply tenant-specific migrations (new entities)
php bin/console hakam:tenant:migrate ecommerce_client
```

### Scenario 3: Full Migration to Tenant-Specific Entities

If you want to give each tenant their own customized entity set:

#### Step 1: Create Tenant-Specific Entity Directories

Organize entities by tenant:

```
src/Entity/Tenant/
├── User.php                    # Shared base user entity
├── EnterpriseClient/
│   ├── User.php               # Custom user entity for enterprise
│   ├── Department.php
│   └── Project.php
├── EcommerceClient/
│   ├── Product.php
│   ├── Order.php
│   └── Category.php
└── CustomClient/
    ├── User.php               # Completely custom user entity
    └── CustomWorkflow.php
```

#### Step 2: Add Tenant-Specific Attributes

```php
// Enterprise-specific user entity that overrides shared one
#[ORM\Entity]
#[TenantSpecific(tenants: ['enterprise_client'], overrideShared: true)]
class User implements TenantEntityInterface
{
    // ... enhanced user entity with enterprise fields
}

// E-commerce specific entities
#[ORM\Entity]
#[TenantSpecific(tenants: ['ecommerce_client'], group: 'ecommerce')]
class Product implements TenantEntityInterface
{
    // ... product entity
}
```

#### Step 3: Create Migration Paths

Create separate migration paths for each tenant type:

```bash
mkdir -p migrations/Tenant/Enterprise
mkdir -p migrations/Tenant/Ecommerce
mkdir -p migrations/Tenant/Custom
```

#### Step 4: Generate Tenant-Specific Migrations

```bash
# Generate migrations for each tenant type
php bin/console hakam:tenant:migrations:generate enterprise_client "CreateEnterpriseSchema"
php bin/console hakam:tenant:migrations:generate ecommerce_client "CreateEcommerceSchema"
php bin/console hakam:tenant:migrations:generate custom_client "CreateCustomSchema"
```

#### Step 5: Configure Tenant Schema Settings

```php
// Enterprise client configuration
$enterpriseConfig = new TenantSchemaConfig();
$enterpriseConfig->setTenantIdentifier('enterprise_client');
$enterpriseConfig->setEntityLoadingStrategy(EntityLoadingStrategy::TENANT_OVERRIDE);
$enterpriseConfig->setEntityGroups(['enterprise']);
$enterpriseConfig->setMigrationPaths(['migrations/Tenant/Enterprise']);

// E-commerce client configuration
$ecommerceConfig = new TenantSchemaConfig();
$ecommerceConfig->setTenantIdentifier('ecommerce_client');
$ecommerceConfig->setEntityLoadingStrategy(EntityLoadingStrategy::HYBRID);
$ecommerceConfig->setEntityGroups(['ecommerce']);
$ecommerceConfig->setMigrationPaths(['migrations/Tenant/Ecommerce']);
```

## Data Migration Considerations

### Migrating Existing Data

When changing entity structures, you may need to migrate existing data:

#### 1. Adding Fields to Existing Entities

```php
// Migration example for adding enterprise fields to User entity
public function up(Schema $schema): void
{
    // Add new columns
    $this->addSql('ALTER TABLE users ADD employee_id VARCHAR(100) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD department VARCHAR(255) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD permissions JSON DEFAULT NULL');
}
```

#### 2. Splitting Shared Data into Tenant-Specific Tables

```php
// Migration to move data from shared table to tenant-specific table
public function up(Schema $schema): void
{
    // Create new tenant-specific table
    $this->addSql('CREATE TABLE tenant_products (...)');
    
    // Copy relevant data
    $this->addSql('INSERT INTO tenant_products SELECT * FROM shared_products WHERE tenant_id = ?', [$tenantId]);
    
    // Clean up if needed
    $this->addSql('DELETE FROM shared_products WHERE tenant_id = ?', [$tenantId]);
}
```

### Fixture Migration

Update your fixtures to work with the new entity structure:

```php
// Before
class UserFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Load for all tenants
    }
}

// After
#[TenantFixture(tenants: ['enterprise_client'])]
class EnterpriseUserFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Load only for enterprise tenants
    }
}
```

## Testing the Upgrade

### 1. Verify Entity Resolution

```bash
# Check that entities are resolved correctly for each tenant
php bin/console hakam:tenant:entities:list basic_client
php bin/console hakam:tenant:entities:list enterprise_client
php bin/console hakam:tenant:entities:list ecommerce_client
```

### 2. Test Tenant Switching

```php
// Test in your application
$tenantEntityManager->switchTenant('enterprise_client');
$users = $userRepository->findAll(); // Should use enterprise User entity

$tenantEntityManager->switchTenant('basic_client');
$users = $userRepository->findAll(); // Should use shared User entity
```

### 3. Verify Migrations

```bash
# Check migration status for each tenant
php bin/console hakam:tenant:migrations:status enterprise_client
php bin/console hakam:tenant:migrations:status ecommerce_client
```

### 4. Test Fixture Loading

```bash
# Test fixture loading for different tenant types
php bin/console hakam:tenant:fixtures:load enterprise_client
php bin/console hakam:tenant:fixtures:load ecommerce_client
```

## Rollback Plan

If you need to rollback the upgrade:

### 1. Database Rollback

```bash
# Rollback tenant-specific migrations
php bin/console hakam:tenant:migrations:rollback enterprise_client --to=previous_version

# Rollback main database migration
php bin/console doctrine:migrations:rollback --configuration=config/packages/doctrine_migrations_main.yaml
```

### 2. Code Rollback

1. Remove the new attributes from entities
2. Restore original bundle configuration
3. Remove TenantSchemaConfig related code

### 3. Data Restoration

Restore from backups if data migration was involved.

## Post-Upgrade Tasks

After successful upgrade:

1. **Update documentation** to reflect new entity structure
2. **Train team members** on new tenant-specific entity concepts
3. **Monitor performance** and adjust caching settings if needed
4. **Update deployment scripts** to handle tenant-specific migrations
5. **Create monitoring** for tenant schema configurations

## Common Upgrade Issues

### Issue: "Attribute class not found"

**Solution:** Make sure you've updated to the latest version of the bundle that includes the new attribute classes.

### Issue: "Entity metadata conflicts"

**Solution:** Clear the metadata cache and ensure entity attributes are correctly configured:

```bash
php bin/console cache:clear
php bin/console hakam:tenant:entities:validate your_tenant
```

### Issue: "Migration path not found"

**Solution:** Ensure migration directories exist and are properly configured in tenant schema config.

## Support and Resources

- **Documentation**: Check the main documentation for detailed configuration options
- **Examples**: Review the examples in `docs/examples/` directory
- **Troubleshooting**: See `docs/TROUBLESHOOTING.md` for common issues
- **Community**: Join the community discussions for help and best practices