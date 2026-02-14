<?php

namespace Hakam\MultiTenancyBundle\Command;

use Exception;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Event\TenantCreatedEvent;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'tenant:database:create',
    description: 'Proxy to create a new tenant database.',
)]
final class CreateDatabaseCommand extends Command
{
    use CommandTrait;

    public function __construct(
        private readonly TenantDatabaseManagerInterface $tenantDatabaseManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create new databases for a tenant')
            ->setAliases(['t:d:c'])
            ->addOption('dbid', 'd', InputOption::VALUE_REQUIRED, 'Create database for a specific tenant ID')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Create all missing databases')
            ->setHelp('This command allows you to create new databases for tenants. Use --dbid=<id> to create for a specific tenant, or --all to create all missing databases which is added to the main database config entity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dbId = $input->getOption('dbid');
            $all = $input->getOption('all');

            if ($dbId && $all) {
                $output->writeln('Cannot use --dbid and --all options together');
                return 1;
            }
            if ($dbId) {
                return $this->createDatabaseById((int) $dbId, $output);
            }
            return $this->createAllMissingDatabases($output);
        } catch (Exception $e) {
            $output->writeln(sprintf('Failed to create database: %s', $e->getMessage()));
            return 1;
        }
    }

    private function createAllMissingDatabases(OutputInterface $output): int
    {
        try {
            $listOfNewDbs = $this->tenantDatabaseManager->listMissingDatabases();
            if (empty($listOfNewDbs)) {
                $output->writeln('No new databases to create');
                return 0;
            }
            foreach ($listOfNewDbs as $newDb) {
                $databaseCreated = $this->createDatabase($newDb, $output);
                if (!$databaseCreated) {
                    throw new MultiTenancyException(sprintf('Failed to create database %s', $newDb->dbname));
                }
                $output->writeln(sprintf('Database %s created successfully', $newDb->dbname));
                $this->tenantDatabaseManager->updateTenantDatabaseStatus($newDb->identifier, DatabaseStatusEnum::DATABASE_CREATED);
                $this->eventDispatcher->dispatch(new TenantCreatedEvent($newDb->identifier, $newDb, $newDb->dbname));
            }
            $output->writeln('The new List of Databases created successfully');
            return 0;
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }
    }


    private function createDatabaseById(int $dbId, OutputInterface $output): int
    {
        try {
            $dbConfig = $this->tenantDatabaseManager->getTenantDatabaseById($dbId);
            if (
                $dbConfig->dbStatus === DatabaseStatusEnum::DATABASE_CREATED ||
                $dbConfig->dbStatus === DatabaseStatusEnum::DATABASE_MIGRATED
            ) {
                $output->writeln(sprintf('Database %s already exists', $dbConfig->dbname));
                return 0;
            }
            $databaseCreated = $this->createDatabase($dbConfig, $output);
            if (!$databaseCreated) {
                throw new MultiTenancyException(sprintf('Failed to create database %s', $dbConfig->dbname, $dbId));
            }
            $output->writeln(sprintf('Database %s created successfully for tenant ID %d', $dbConfig->dbname, $dbId));
            $this->tenantDatabaseManager->updateTenantDatabaseStatus($dbId, DatabaseStatusEnum::DATABASE_CREATED);
            $this->eventDispatcher->dispatch(new TenantCreatedEvent($dbId, $dbConfig, $dbConfig->dbname));
            return 0;
        } catch (Exception $e) {
            $output->writeln(sprintf('Failed to create database for tenant ID %d: %s', $dbId, $e->getMessage()));
            return 1;
        }
    }

    private function createDatabase(TenantConnectionConfigDTO $dbConfiguration, OutputInterface $output): bool
    {
        return $this->tenantDatabaseManager->createTenantDatabase($dbConfiguration);
    }
}
