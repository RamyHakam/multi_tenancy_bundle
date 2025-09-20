<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[AsCommand(
    name: 'tenant:migrations:migrate',
    description: 'Proxy to migrate the  existing tenant databases, or initialize the new tenant databases.',
)]
final class MigrateCommand extends TenantCommand
{

    const MIGRATE_TYPE_INIT = 'init';
    const MIGRATE_TYPE_UPDATE = 'update';

    public function __construct(
        private readonly ManagerRegistry                $registry,
        private readonly ContainerInterface             $container,
        private readonly EventDispatcherInterface       $eventDispatcher,
        private readonly TenantDatabaseManagerInterface $tenantDatabaseManager,
    )
    {
        parent::__construct($registry, $this->container, $eventDispatcher);
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:migrations:migrate')
            ->setAliases(['t:m:m'])
            ->setDescription('Proxy to launch doctrine:migrations:migrate for specific database .')
            ->addArgument('type', InputArgument::REQUIRED, 'Database Migration Type it should be either init or migrate')
            ->addArgument('dbId', InputArgument::OPTIONAL, 'Database Identifier')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version number (YYYYMMDDHHMMSS) or alias (first, prev, next, latest) to migrate to.', 'latest')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration as a dry run.')
            ->addOption('query-time', null, InputOption::VALUE_NONE, 'Time all the queries individually.')
            ->addOption('allow-no-migration', null, InputOption::VALUE_NONE, 'Do not throw an exception when no changes are detected.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbId = $input->getArgument('dbId');
        $migrationType = $input->getArgument('type');
        $io = new SymfonyStyle($input, $output);

        if ($dbId !== null) {
            $io->warning('DEPRECATION: The "dbId" argument is deprecated and will be removed in v4.0.');
            $io->note(sprintf('Migrating specific database with identifier: %s', $dbId));
            $io->newLine();

            try {
                $tenantDb = $this->tenantDatabaseManager->getTenantDatabaseById($dbId);

                if ($migrationType === self::MIGRATE_TYPE_INIT && $tenantDb->dbStatus !== DatabaseStatusEnum::DATABASE_CREATED) {
                    $io->error(sprintf('Database "%s" is not in CREATED status. Current status: %s', $dbId, $tenantDb->dbStatus->value));
                    return 1;
                }

                if ($migrationType === self::MIGRATE_TYPE_UPDATE && $tenantDb->dbStatus !== DatabaseStatusEnum::DATABASE_MIGRATED) {
                    $io->error(sprintf('Database "%s" is not in MIGRATED status. Current status: %s', $dbId, $tenantDb->dbStatus->value));
                    return 1;
                }

                return $this->migrateSingleDB($input, $output, $io, $tenantDb);

            } catch (\RuntimeException $e) {
                $io->error(sprintf('Tenant database with identifier "%s" not found: %s', $dbId, $e->getMessage()));
                return 1;
            }
        }
        // Migrate all databases based on the type
        switch ($input->getArgument('type')) {
            case self::MIGRATE_TYPE_INIT:
                $io->note('Migrating the new databases');
                $io->newLine();
                $listOfDbsToMigrate = $this->tenantDatabaseManager->getTenantDbListByDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);
                break;
            case self::MIGRATE_TYPE_UPDATE:
                $io->note('Migrating the existing databases');
                $io->newLine();
                $listOfDbsToMigrate = $this->dbService->getListOfTenantDataBases();
                break;
            default:
                $io->error('Invalid migration type');
                return 1;
        }
        $io->progressStart(count($listOfDbsToMigrate));
        $io->newLine();
        /**
         * @var int $kay
         * @var   TenantConnectionConfigDTO $db
         */
        foreach ($listOfDbsToMigrate as $kay => $db) {
            // set the dbId to the input argument
            $input->setArgument('dbId', $db->identifier);
            try {
                $io->note(sprintf('Start Migrating database #%s, Database_Name: %s, Database_Host: %s ', $kay + 1, $db->dbname, $db->host));
                $io->newLine();
                $this->runMigrateCommand($input, $output);
                if ($db->dbStatus === DatabaseStatusEnum::DATABASE_CREATED) {
                    $this->tenantDatabaseManager->updateTenantDatabaseStatus($db->identifier, DatabaseStatusEnum::DATABASE_MIGRATED);
                }
                $io->success(sprintf('Migrating database #%s, Database_Name: %s, Database_Host: %s ', $kay + 1, $db->dbname, $db->host));
                $io->newLine();
                $io->progressAdvance();
                $this->registry->getManager()->flush();
            } catch (Throwable $e) {
                $io->newLine();
                $io->error($e->getMessage());
                return 1;
            }
        }
        $io->progressFinish();
        $io->newLine();
        $io->success('All databases migrated successfully');
        return 0;
    }

    /**
     * @throws ExceptionInterface
     */
    private function runMigrateCommand(InputInterface $input, OutputInterface $output): void
    {
        $newInput = new ArrayInput([
            'version' => $input->getArgument('version'),
            '--dry-run' => $input->getOption('dry-run'),
            '--query-time' => $input->getOption('query-time'),
            '--allow-no-migration' => $input->getOption('allow-no-migration'),
        ]);
        $newInput->setInteractive($input->isInteractive());
        $otherCommand = new \Doctrine\Migrations\Tools\Console\Command\MigrateCommand($this->getDependencyFactory($input));
        $otherCommand->run($newInput, $output);
    }

    private function migrateSingleDB(InputInterface $input, OutputInterface $output, SymfonyStyle $io, TenantConnectionConfigDTO $tenantDb): int
    {
        try {
            // we already checked that dbId is not null or add it  in the loop
            $io->note(sprintf('Start Migrating database with identifier "%s" (Database: %s, Host: %s)', 
                $tenantDb->identifier, $tenantDb->dbname, $tenantDb->host));
            $io->newLine();
            
            $this->runMigrateCommand($input, $output);
            
            // Update database status if this was an init migration
            if ($tenantDb->dbStatus === DatabaseStatusEnum::DATABASE_CREATED) {
                $this->tenantDatabaseManager->updateTenantDatabaseStatus(
                    $tenantDb->identifier, 
                    DatabaseStatusEnum::DATABASE_MIGRATED
                );
                $this->registry->getManager()->flush();
            }
            
            $io->success(sprintf('Database with identifier "%s" migrated successfully.', $tenantDb->identifier));
            return 0;
        } catch (Throwable $e) {
            $io->error(sprintf('Failed to migrate database with identifier "%s": %s', $tenantDb->identifier, $e->getMessage()));
            return 1;
        }
    }
}
