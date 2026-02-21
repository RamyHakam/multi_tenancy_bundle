<?php

/**
 * Example 2: Bundle Configuration (config/packages/hakam_multi_tenancy.yaml)
 *
 * Below is the full YAML configuration with all available options.
 * Copy and adapt the sections you need.
 */

/*
# config/packages/hakam_multi_tenancy.yaml

hakam_multi_tenancy:

    # ──────────────────────────────────────────────
    # 1. REQUIRED: Tenant entity class and identifier
    # ──────────────────────────────────────────────
    tenant_database_className: App\Entity\TenantDbConfig
    tenant_database_identifier: id     # column used to look up tenant config

    # ──────────────────────────────────────────────
    # 2. REQUIRED: Tenant database connection defaults
    #    These are the boot-time connection parameters.
    #    They get overridden per-tenant when SwitchDbEvent fires.
    # ──────────────────────────────────────────────
    tenant_connection:
        url: '%env(DATABASE_URL)%'
        host: '127.0.0.1'
        port: '3306'
        driver: pdo_mysql          # pdo_mysql | pdo_pgsql | pdo_sqlite
        charset: utf8
        server_version: '8.0'

    # ──────────────────────────────────────────────
    # 3. REQUIRED: Tenant migration configuration
    # ──────────────────────────────────────────────
    tenant_migration:
        tenant_migration_namespace: DoctrineMigrations\Tenant
        tenant_migration_path: '%kernel.project_dir%/migrations/Tenant'

    # ──────────────────────────────────────────────
    # 4. REQUIRED: Tenant entity manager mapping
    #    Points to the directory containing your tenant entities.
    # ──────────────────────────────────────────────
    tenant_entity_manager:
        tenant_naming_strategy: doctrine.orm.naming_strategy.default
        mapping:
            type: attribute
            dir: '%kernel.project_dir%/src/Entity/Tenant'
            prefix: App\Entity\Tenant
            alias: Tenant
            is_bundle: false

        # Optional: Custom DQL functions for the tenant entity manager
        # dql:
        #     string_functions:
        #         JSON_EXTRACT: App\DQL\JsonExtract
        #     numeric_functions: {}
        #     datetime_functions: {}

    # ──────────────────────────────────────────────
    # 5. OPTIONAL: Custom tenant config provider
    #    Override the default Doctrine-based provider with your own.
    #    See example 08-custom-config-provider.php
    # ──────────────────────────────────────────────
    # tenant_config_provider: app.my_custom_tenant_provider

    # ──────────────────────────────────────────────
    # 6. OPTIONAL: Automatic tenant resolution from HTTP requests
    #    See example 06-resolvers.php for details on each strategy.
    # ──────────────────────────────────────────────
    resolver:
        enabled: true
        strategy: header           # subdomain | host | path | header | chain
        throw_on_missing: false    # throw RuntimeException if tenant can't be resolved
        excluded_paths:            # paths that skip tenant resolution
            - /health
            - /api/public
            - /_profiler
        options:
            # Strategy-specific options (only the relevant ones are used):
            header_name: X-Tenant-ID           # for 'header' strategy
            # subdomain_position: 0            # for 'subdomain' strategy
            # base_domain: example.com         # for 'subdomain' strategy
            # path_segment: 0                  # for 'path' strategy
            # host_map:                        # for 'host' strategy
            #     client1.com: tenant_1
            #     client2.com: tenant_2
            # chain_order: [header, path]      # for 'chain' strategy

    # ──────────────────────────────────────────────
    # 7. OPTIONAL: Tenant-aware cache isolation
    #    Automatically prefixes cache keys with the current tenant ID.
    # ──────────────────────────────────────────────
    cache:
        enabled: true
        prefix_separator: '__'     # separator between tenant ID and cache key

*/
