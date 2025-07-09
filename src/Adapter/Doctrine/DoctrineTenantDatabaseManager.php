<?php

namespace Hakam\MultiTenancyBundle\Adapter\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

class DoctrineTenantDatabaseManager implements TenantDatabaseManagerInterface
{
    private readonly ObjectRepository $tenantDatabaseRepository;
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        #[Autowire(service:TenantDBALConnectionGenerator::class)]
        private readonly DoctrineDBALConnectionGeneratorInterface $doctrineDBALConnectionGenerator,
        #[Autowire('%hakam.tenant_db_list_entity%')]
        private readonly string                   $tenantDbEntityClassName,
        #[Autowire('%hakam.tenant_db_identifier%')]
        private readonly string                   $tenantDbIdentifier
    )
    {
        $this->tenantDatabaseRepository = $this->entityManager->getRepository($this->tenantDbEntityClassName);
    }

    public function listDatabases(): array
    {
       $databases = $this->tenantDatabaseRepository->findBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_MIGRATED]);
       if (count($databases) === 0) {
              throw new RuntimeException(sprintf('No tenant databases found in repository "%s"', get_class($this->tenantDatabaseRepository)));
       }
       return
              array_map(
                fn($db) => TenantConnectionConfigDTO::fromArray(
                     [
                         'identifier' => $db->getId() ?? '',
                          'driver' => $db->getDriverType(),
                          'host' => $db->getDbHost(),
                          'port' => $db->getDbPort(),
                          'dbname' => $db->getDbName(),
                          'user' => $db->getDbUserName(),
                          'password' => $db->getDbPassword()
                     ]
                ),
                $databases
              );
    }

    public function listMissingDatabases(): array
    {
        $databases = $this->tenantDatabaseRepository->findBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_NOT_CREATED]);
        if (count($databases) === 0){
            throw new RuntimeException(sprintf('No tenant databases found in repository "%s"', get_class($this->tenantDatabaseRepository)));
        }
        return
            array_map(
                fn($db) => TenantConnectionConfigDTO::fromArray(
                    [
                        'identifier' => $db->getId() ?? '',
                        'driver' => $db->getDriverType(),
                        'host' => $db->getDbHost(),
                        'port' => $db->getDbPort(),
                        'dbname' => $db->getDbName(),
                        'user' => $db->getDbUserName(),
                        'password' => $db->getDbPassword()
                    ]
                ),
                $databases
            );
    }

    public function getDefaultTenantIDatabase(): TenantConnectionConfigDTO
    {
        $tenantDbConfig = $this->tenantDatabaseRepository->findOneBy(['databaseStatus' => DatabaseStatusEnum::DATABASE_CREATED]);
        if (null === $tenantDbConfig) {
            throw new RuntimeException(sprintf('No default tenant database found in repository "%s"', get_class($this->tenantDatabaseRepository)));
        }
        return TenantConnectionConfigDTO::fromArray(
            [
                'identifier' => $tenantDbConfig->getId() ?? '',
                'driver' => $tenantDbConfig->getDriverType(),
                'host' => $tenantDbConfig->getDbHost(),
                'port' => $tenantDbConfig->getDbPort(),
                'dbname' => $tenantDbConfig->getDbName(),
                'user' => $tenantDbConfig->getDbUserName(),
                'password' => $tenantDbConfig->getDbPassword()
            ]
        );
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

    public function updateTenantDatabaseStatus(string $identifier, DatabaseStatusEnum $status): bool
    {
        $tenantDbConfig = $this->tenantDatabaseRepository->findOneBy([$this->tenantDbIdentifier => $identifier]);
        if (null === $tenantDbConfig) {
            throw new RuntimeException(sprintf('Tenant database with identifier "%s" not found in repository "%s"', $identifier, get_class($this->tenantDatabaseRepository)));
        }
        $tenantDbConfig->setDatabaseStatus($status);
        $this->entityManager->persist($tenantDbConfig);
        $this->entityManager->flush();
        return true;
    }
}
