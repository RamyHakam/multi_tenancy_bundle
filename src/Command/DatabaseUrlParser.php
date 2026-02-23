<?php

namespace Hakam\MultiTenancyBundle\Command;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;

/**
 * Parses a DATABASE_URL string into a TenantConnectionConfigDTO.
 *
 * Supports: mysql://, postgresql://, sqlite://localhost/<path>
 * Note: the SQLite triple-slash form (sqlite:///) is NOT supported by PHP's
 * parse_url — convert it to sqlite://localhost/<path> in your DATABASE_URL.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class DatabaseUrlParser
{
    public static function parse(string $url): TenantConnectionConfigDTO
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new \InvalidArgumentException(sprintf(
                'DATABASE_URL "%s" could not be parsed. Ensure it is a valid URL. ' .
                'For SQLite use sqlite://localhost/<path> instead of sqlite:///.',
                $url
            ));
        }

        $scheme = $parsed['scheme'] ?? 'mysql';
        $driver = DriverTypeEnum::from($scheme);
        $defaultPort = $driver === DriverTypeEnum::POSTGRES ? 5432 : 3306;

        return TenantConnectionConfigDTO::fromArgs(
            identifier: null,
            driver: $driver,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: $parsed['host'] ?? '127.0.0.1',
            port: isset($parsed['port']) ? (int) $parsed['port'] : $defaultPort,
            dbname: ltrim($parsed['path'] ?? '', '/'),
            user: isset($parsed['user']) ? urldecode($parsed['user']) : 'root',
            password: isset($parsed['pass']) ? urldecode($parsed['pass']) : null,
        );
    }
}
