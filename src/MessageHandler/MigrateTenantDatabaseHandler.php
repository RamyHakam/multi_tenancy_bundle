<?php

namespace Hakam\MultiTenancyBundle\MessageHandler;

use Hakam\MultiTenancyBundle\Message\MigrateTenantDatabaseMessage;
use Hakam\MultiTenancyBundle\Services\TenantMigrationRunner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MigrateTenantDatabaseHandler
{
    public function __construct(private  readonly  TenantMigrationRunner $tenantMigrationRunner)
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(MigrateTenantDatabaseMessage $message): void
    {
        // Run the migrations for the tenant database
        $this->tenantMigrationRunner->runMigrations($message->tenantId);
    }
}