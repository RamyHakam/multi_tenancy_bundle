<?php

namespace Hakam\MultiTenancyBundle\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com
 */
class DoctrineTenantConfigProvider implements TenantConfigProviderInterface
{
    private EntityRepository $entityRepository;

    public function __construct(EntityManagerInterface $entityManager, private string $dbClassName, private string $dbIdentifier)
    {
        $this->entityRepository = $entityManager->getRepository($this->dbClassName);
    }

    public function getTenantConnectionConfig(?int $identifier): TenantConnectionConfigDTO
    {
        $tenantDbConfig = $identifier ? $this->entityRepository->findOneBy([$this->dbIdentifier => $identifier]) :
            $this->entityRepository->findOneBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED]);
        if (null === $tenantDbConfig) {
            throw new \RuntimeException(sprintf('Tenant db repository " %s " returns NULL for identifier " %s = %s " ', get_class($this->entityRepository), $this->dbIdentifier, $identifier));
        }

        if (!$tenantDbConfig instanceof TenantDbConfigurationInterface) {
            throw new \LogicException(sprintf('The tenant db entity  " %s ". Should implement " Hakam\MultiTenancyBundle\TenantDbConfigurationInterface " ', get_class($dbConfigObject)));
        }

        return TenantConnectionConfigDTO::fromArray(
            [
                'identifier' => $tenantDbConfig->getId() ?? '',
                'driver' => $tenantDbConfig->getDriverType(),
                'dbStatus' => $tenantDbConfig->getDatabaseStatus(),
                'host' => $tenantDbConfig->getDbHost(),
                'port' => $tenantDbConfig->getDbPort(),
                'dbname' => $tenantDbConfig->getDbName(),
                'user' => $tenantDbConfig->getDbUserName(),
                'password' => $tenantDbConfig->getDbPassword()
            ]
        );
    }
}
