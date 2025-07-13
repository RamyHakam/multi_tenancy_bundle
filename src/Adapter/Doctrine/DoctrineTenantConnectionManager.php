<?php

namespace Hakam\MultiTenancyBundle\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\DTO\TenantDatabaseRegistrationDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Port\TenantConnectionManagerInterface;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com
 */
class DoctrineTenantConnectionManager implements TenantConnectionManagerInterface
{
    private EntityRepository $entityRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%hakam.tenant_db_list_entity%')]
        private readonly string                 $dbClassName)
    {
        $this->entityRepository = $this->entityManager->getRepository($this->dbClassName);
    }

    public function getTenantConnectionConfig(TenantDatabaseIdentifier $identifier): TenantConnectionConfigDTO
    {
        $tenantDbConfig =  $this->entityRepository->findOneBy(['tenantIdentifier' => $identifier]);
        if (null === $tenantDbConfig) {
            throw new \RuntimeException(sprintf('Tenant db repository " %s " returns NULL for identifier " %s " ', get_class($this->entityRepository), $identifier));
        }

        if (!$tenantDbConfig instanceof TenantDbConfigurationInterface) {
            throw new \LogicException(sprintf('The tenant db entity  " %s ". Should implement " Hakam\MultiTenancyBundle\TenantDbConfigurationInterface " ', get_class($dbConfigObject)));
        }

        return TenantConnectionConfigDTO::fromArgs(
                identifier: $tenantDbConfig->getTenantIdentifier(),
                driver: $tenantDbConfig->getDriverType(),
                dbStatus: $tenantDbConfig->getDatabaseStatus(),
                host: $tenantDbConfig->getDbHost(),
                port: $tenantDbConfig->getDbPort(),
                dbname : $tenantDbConfig->getDbName(),
                user: $tenantDbConfig->getDbUserName(),
                password: $tenantDbConfig->getDbPassword()
        );
    }

    public function registerTenantDatabase(TenantDatabaseRegistrationDTO $registrationDTO): TenantConnectionConfigDTO
    {
        $connectionConfigDTO = TenantConnectionConfigDTO::fromRegistrationDTO($registrationDTO);
        $tenantDbConfig = new $this->dbClassName(
            identifier: $connectionConfigDTO->identifier,
            driverType: $connectionConfigDTO->driver,
            databaseStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            dbHost: $connectionConfigDTO->host,
            dbPort: $connectionConfigDTO->port,
            dbName: $connectionConfigDTO->dbname,
            dbUserName: $connectionConfigDTO->user,
            dbPassword: $connectionConfigDTO->password
        );

        $this->entityManager->persist($tenantDbConfig);
        $this->entityManager->flush();
        return $connectionConfigDTO;
    }

    public function updateTenantDatabaseStatus(TenantDatabaseIdentifier $identifier, DatabaseStatusEnum $status): bool
    {
        $tenantDbConfig =  $this->entityRepository->findOneBy(['tenantIdentifier' => $identifier]);
        if (null === $tenantDbConfig) {
            throw new \RuntimeException(sprintf('Tenant db repository " %s " returns NULL for identifier " %s " ', get_class($this->entityRepository), $identifier));
        }
        $tenantDbConfig->setDatabaseStatus($status);
        $this->entityManager->persist($tenantDbConfig);
        $this->entityManager->flush();
        return true;
    }
}
