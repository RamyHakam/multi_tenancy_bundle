<?php

namespace Hakam\MultiTenancyBundle\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

class DoctrineTenantDatabaseManager implements TenantDatabaseManagerInterface
{

    public function __construct(
        private readonly EntityManagerInterface                   $entityManager,
        #[Autowire(service: TenantDBALConnectionGenerator::class)]
        private readonly DoctrineDBALConnectionGeneratorInterface $doctrineDBALConnectionGenerator,
    )
    {
    }


    /**
     * @throws MultiTenancyException
     */
    public function createTenantDatabase(TenantConnectionConfigDTO $tenantConnectionConfigDTO): bool
    {
        try {
            $tenantConnection = $this->doctrineDBALConnectionGenerator->generateMaintenanceConnection($tenantConnectionConfigDTO);
            $schemaManager = method_exists($tenantConnection, 'createSchemaManager')
                ? $tenantConnection->createSchemaManager()
                : $tenantConnection->getSchemaManager();
            $schemaManager->createDatabase($tenantConnectionConfigDTO->dbname);
            $tenantConnection->close();
            return 1;

        } catch (Throwable $e) {
            throw new MultiTenancyException(sprintf('Unable to create new tenant database %s: %s',
                $tenantConnectionConfigDTO->dbname
                , $e->getMessage()), $e->getCode(), $e);
        }
    }
}
