<?php

/**
 * Example 8: Custom Tenant Config Provider
 *
 * By default, the bundle reads tenant connection configs from a Doctrine entity.
 * You can replace this with your own provider that fetches configs from
 * any source: Redis, an external API, a YAML file, etc.
 *
 * Implement TenantConfigProviderInterface and register it as a service.
 */

namespace App\Service;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;

// ──────────────────────────────────────────────
// Option A: Config provider backed by Redis/cache
// ──────────────────────────────────────────────

class RedisTenantConfigProvider implements TenantConfigProviderInterface
{
    public function __construct(
        private readonly \Redis $redis,
    ) {}

    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        $data = $this->redis->hGetAll("tenant:{$identifier}");

        if (empty($data)) {
            throw new \RuntimeException("Tenant '{$identifier}' not found in Redis.");
        }

        return TenantConnectionConfigDTO::fromArgs(
            identifier: $identifier,
            driver: DriverTypeEnum::from($data['driver']),
            dbStatus: DatabaseStatusEnum::from($data['status']),
            host: $data['host'],
            port: (int) $data['port'],
            dbname: $data['dbname'],
            user: $data['user'],
            password: $data['password'] ?? null,
        );
    }
}


// ──────────────────────────────────────────────
// Option B: Config provider backed by environment/static config
// Useful for dev/staging with a fixed set of tenants.
// ──────────────────────────────────────────────

class StaticTenantConfigProvider implements TenantConfigProviderInterface
{
    private array $tenants;

    public function __construct()
    {
        $this->tenants = [
            'acme' => TenantConnectionConfigDTO::fromArgs(
                identifier: 'acme',
                driver: DriverTypeEnum::MYSQL,
                dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
                host: '127.0.0.1',
                port: 3306,
                dbname: 'tenant_acme',
                user: 'root',
                password: 'secret',
            ),
            'globex' => TenantConnectionConfigDTO::fromArgs(
                identifier: 'globex',
                driver: DriverTypeEnum::MYSQL,
                dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED,
                host: '127.0.0.1',
                port: 3306,
                dbname: 'tenant_globex',
                user: 'root',
                password: 'secret',
            ),
        ];
    }

    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        if (!isset($this->tenants[$identifier])) {
            throw new \RuntimeException("Tenant '{$identifier}' not configured.");
        }

        return $this->tenants[$identifier];
    }
}


// ──────────────────────────────────────────────
// Option C: In-memory provider (useful for tests)
// ──────────────────────────────────────────────

class InMemoryTenantConfigProvider implements TenantConfigProviderInterface
{
    /** @var array<string|int, TenantConnectionConfigDTO> */
    private array $tenants = [];

    public function addTenant(mixed $identifier, TenantConnectionConfigDTO $config): void
    {
        $this->tenants[$identifier] = $config;
    }

    public function getTenantConnectionConfig(mixed $identifier): TenantConnectionConfigDTO
    {
        if (!isset($this->tenants[$identifier])) {
            throw new \RuntimeException("Tenant '{$identifier}' not found.");
        }

        return $this->tenants[$identifier];
    }
}


// ──────────────────────────────────────────────
// Registration
// ──────────────────────────────────────────────

/*
# config/services.yaml
services:
    App\Service\RedisTenantConfigProvider:
        arguments:
            $redis: '@snc_redis.default'

# config/packages/hakam_multi_tenancy.yaml
hakam_multi_tenancy:
    tenant_config_provider: App\Service\RedisTenantConfigProvider
*/
