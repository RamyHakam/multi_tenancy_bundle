<?php

namespace Hakam\MultiTenancyBundle\Adapter\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Hakam\MultiTenancyBundle\Adapter\DefaultDsnGenerator;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Port\DsnGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(DoctrineDBALConnectionGeneratorInterface::class)]
final class TenantDBALConnectionGenerator implements DoctrineDBALConnectionGeneratorInterface
{
    private readonly DsnParser $dsnParser;
    public function __construct(
        #[Autowire(service: DefaultDsnGenerator::class)]
        private readonly DsnGeneratorInterface $dsnGenerator,
    )
    {
        $this->dsnParser = new DsnParser([
            'mysql' => 'pdo_mysql',
            'postgresql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
        ]);
    }

    /**
     * @throws Exception
     */
    public function generate(TenantConnectionConfigDTO $cfg): Connection
    {
        return DriverManager::getConnection($this->dsnParser->parse(
            $this->dsnGenerator->generate($cfg)
        ));
    }

    /**
     * @throws Exception
     */
    public function generateMaintenanceConnection(TenantConnectionConfigDTO $cfg): Connection
    {
        return DriverManager::getConnection($this->dsnParser->parse(
            $this->dsnGenerator->generateMaintenanceDsn($cfg)
        ));
    }
}
