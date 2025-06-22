<?php

namespace Hakam\MultiTenancyBundle\MessageHandler;

use Doctrine\DBAL\Exception;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Message\CreateTenantDatabaseMessage;
use Hakam\MultiTenancyBundle\Message\MigrateTenantDatabaseMessage;
use Hakam\MultiTenancyBundle\Services\DbService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CreateTenantDatabaseHandler
{
    public function __construct(private readonly  DbService $dbService,private  readonly MessageBusInterface $bus)
    {
    }

    /**
     * @throws MultiTenancyException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(CreateTenantDatabaseMessage $message): void
    {
        $tenantDbConfig = $this->dbService->getTenantDbConfigById($message->tenantId);
        if($tenantDbConfig->getDatabaseStatus() === DatabaseStatusEnum::DATABASE_CREATED) {
            throw new MultiTenancyException(sprintf('Tenant database %s already created', $tenantDbConfig->getDbName()));
        }
        $result = $this->dbService->createDatabase($tenantDbConfig);
        if ($result === 0) {
            throw new MultiTenancyException(sprintf('Failed to create tenant database %s', $tenantDbConfig->getDbName()));
        }
        // Dispatch a message to run migrations for the newly created tenant database
        $this->bus->dispatch(new MigrateTenantDatabaseMessage($tenantDbConfig->getId()));
    }
}