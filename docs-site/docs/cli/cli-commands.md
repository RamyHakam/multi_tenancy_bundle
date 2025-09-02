---
title: Tenant CLI Commands Reference
---

The Symfony Multi-Tenancy Bundle ships with a suite of `tenant:*` console commands to manage tenants dbs.
These commands **decorate** the existing Doctrine commands providing the same flags, options, and behavior while adding tenant database, migration, and fixture management.

Below is a consolidated reference with detailed examples and optional flags for each command.



## Tenant Database Create (`tenant:database:create`)

**Command:** `tenant:database:create`

Create one or more tenant databases (if they don’t exist) and run initial migrations.

```bash
# Create a single tenant DB by ID
php bin/console tenant:database:create --dbid=5

# Create all tenants defined in the main registry
php bin/console tenant:database:create --all

# Default behavior: create all missing tenant databases (backward compatible)
php bin/console tenant:database:create
```

**Options & Flags:**

- `--dbid=<id>`: Create database only for the tenant with the given ID.
- `--all`: Explicitly create all missing tenant databases.
- No options: Default behavior creates all missing databases (backward compatible).

**Behavior:**

- If a tenant database already exists (status `DATABASE_CREATED` or `DATABASE_MIGRATED`), the command will skip it with a notification.
- Cannot use `--dbid` and `--all` options together.
- Command will fail gracefully if tenant ID is not found or database creation fails.

## Schema Diff Generation (`tenant:migration:diff`)

**Command:** `tenant:migration:diff`

Generate a Doctrine schema diff for tenant entities, placing new migration files under `migrations/Tenant`.

```bash
# Generate diff for tenant #3
php bin/console tenant:migration:diff --dbid=3

# Generate diffs for all tenants
php bin/console tenant:migration:diff --all
```

**Options & Flags:**

* `--dbid=<id>`   	Generate diff for a specific tenant.
* `--all`         	Generate diffs for every tenant in the registry.
* `--formatted`   	Apply coding standards (PHP-CS-Fixer) to the generated file.
* `--dry-run`     	Output SQL without writing files.

## Schema Migration (`tenant:migration:migrate`)

**Command:** `tenant:migration:migrate`

Apply schema migrations to tenant databases. Supports initializing new DBs or updating existing ones.

```bash
# Initialize migrations on a new tenant DB
php bin/console tenant:migration:migrate init --dbid=4

# Update existing tenants to the latest version
php bin/console tenant:migration:migrate update --all
```

| Subcommand | Description                                 |
| ---------- | ------------------------------------------- |
| `init`     | Run pending migrations on newly created DBs |
| `update`   | Upgrade all existing DBs to latest schema   |

**Options & Flags:**

* `--dbid=<id>`   	Apply only to the specified tenant.
* `--all`         	Apply to every tenant.
* `--step=<n>`    	Limit to the next *n* migration versions.
* `--dry-run`     	Show SQL without making changes.

## Fixture Loading (`tenant:fixtures:load`)

**Command:** `tenant:fixtures:load`

Load Doctrine fixtures into tenant databases. Only classes annotated with `#[TenantFixture]` will be executed.

```bash
# Load fixtures for tenant #7, appending to existing data
php bin/console tenant:fixtures:load --dbid=7 --append

# Purge with TRUNCATE, then load for all tenants
php bin/console tenant:fixtures:load --all --purge-with-truncate
```

**Options & Flags:**

* `--dbid=<id>`             	Target a specific tenant.
* `--all`                   	Run for every tenant in the system.
* `--append`                	Do not purge existing data; append only.
* `--purge-with-truncate`   	Purge tables by TRUNCATE instead of DELETE.
* `--group=<name>`          	Load only fixtures in the specified group.
* `--no-interaction`        	Run in non-interactive mode, skipping confirmations.


With these commands and flags, you can fully automate tenant provisioning, schema evolution, and test data loading from the Symfony console or your CI/CD pipelines.
