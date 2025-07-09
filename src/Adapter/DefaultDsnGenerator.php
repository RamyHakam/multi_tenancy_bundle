<?php

namespace Hakam\MultiTenancyBundle\Adapter;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\DsnGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Default implementation of DsnGeneratorInterface.
 *
 * Generates DSN strings based on the provided TenantConnectionConfigDTO.
 *
 * @author Ramy Hakam < pencilsoft1@gmail.com>
 */
#[AsAlias(DsnGeneratorInterface::class)]
final  class DefaultDsnGenerator implements DsnGeneratorInterface
{
    public function generate(TenantConnectionConfigDTO $cfg): string
    {
        // Generate a db DSN string based on the driver type
        return match($cfg->driver) {
            DriverTypeEnum::MYSQL      => $this->generateMysqlDsn($cfg),
            DriverTypeEnum::POSTGRES => $this->generatePgsqlDsn($cfg),
            DriverTypeEnum::SQLITE     => $this->generateSqliteDsn($cfg),
        };
    }

    public function generateMaintenanceDsn(TenantConnectionConfigDTO $cfg): string
    {
        // DSN to connect *to the server* without the tenant DB
        return match($cfg->driver) {
            DriverTypeEnum::MYSQL      => $this->generateMysqlMaintenanceDsn($cfg),
            DriverTypeEnum::POSTGRES => $this->generatePgsqlMaintenanceDsn($cfg),
            DriverTypeEnum::SQLITE     => $this->generateSqliteMaintenanceDsn($cfg),
        };
    }

    private function generateMysqlDsn(TenantConnectionConfigDTO $cfg): string
    {
        $pass = $cfg->password ? ':' . $cfg->password : '';
        return sprintf(
            'mysql://%s%s@%s:%d/%s',
            $cfg->user, $pass,
            $cfg->host, $cfg->port,
            $cfg->dbname
        );
    }

    private function generateMysqlMaintenanceDsn(TenantConnectionConfigDTO $cfg): string
    {
        $pass = $cfg->password ? ':' . $cfg->password : '';
        return sprintf(
            'mysql://%s%s@%s:%d',
            $cfg->user, $pass,
            $cfg->host, $cfg->port
        );
    }

    private function generatePgsqlDsn(TenantConnectionConfigDTO $cfg): string
    {
        $pass = $cfg->password ? ':' . $cfg->password : '';
        return sprintf(
            'pgsql://%s%s@%s:%d/%s',
            $cfg->user, $pass,
            $cfg->host, $cfg->port,
            $cfg->dbname
        );
    }

    private function generatePgsqlMaintenanceDsn(TenantConnectionConfigDTO $cfg): string
    {
        $pass = $cfg->password ? ':' . $cfg->password : '';
        return sprintf(
            'pgsql://%s%s@%s:%d/postgres',
            $cfg->user, $pass,
            $cfg->host, $cfg->port
        );
    }

    private function generateSqliteDsn(TenantConnectionConfigDTO $cfg): string
    {
        return sprintf('sqlite:///%s', ltrim($cfg->dbname, '/'));
    }

    private function generateSqliteMaintenanceDsn(TenantConnectionConfigDTO $cfg): string
    {
        return $this->generateSqliteDsn($cfg);
    }
}