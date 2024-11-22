<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Services\DbService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
final class MigrateCommand extends Command
{
    use CommandTrait;

    const MIGRATE_TYPE_INIT = 'init';
    const MIGRATE_TYPE_UPDATE = 'update';

    public function __construct(
        private readonly ManagerRegistry          $registry,
        private readonly ContainerInterface       $container,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DbService                $dbService,
    )
    {
        parent::__construct();
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
        $io = new SymfonyStyle($input, $output);
        switch ($input->getArgument('type')) {
            case self::MIGRATE_TYPE_INIT:
                $io->note('Migrating the new databases');
                $io->newLine();
                $listOfDbsToMigrate = $this->dbService->getListOfNewCreatedDataBases();
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
        foreach ($listOfDbsToMigrate as $kay => $db) {
            // set the dbId to the input argument
            $input->setArgument('dbId', $db->getId());
            try {
                $io->note(sprintf('Start Migrating database #%s, Database_Name: %s, Database_Host: %s '  ,$kay+1,$db->getDbName(), $db->getDbHost()));
                $io->newLine();
                $this->runMigrateCommand($input, $output);
                if ($db->getDatabaseStatus() === DatabaseStatusEnum::DATABASE_CREATED) {
                    $db->setDatabaseStatus(DatabaseStatusEnum::DATABASE_MIGRATED);
                    $this->registry->getManager()->persist($db);
                }
                $io->success(sprintf('Migrating database #%s, Database_Name: %s, Database_Host: %s '  ,$kay+1,$db->getDbName(), $db->getDbHost()));
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
}
