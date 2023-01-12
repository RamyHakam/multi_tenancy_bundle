<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class MigrateCommand extends Command
{
    use CommandTrait;

    private ManagerRegistry $registry;

    private ContainerInterface $container;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ManagerRegistry $registry,
        ContainerInterface $container,
        EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
        $this->registry = $registry;
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:migrations:migrate')
            ->setAliases(['t:m:m'])
            ->setDescription('Proxy to launch doctrine:migrations:migrate for specific database .')
            ->addArgument('dbId', InputArgument::OPTIONAL, 'Database Identifier')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version number (YYYYMMDDHHMMSS) or alias (first, prev, next, latest) to migrate to.', 'latest')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration as a dry run.')
            ->addOption('query-time', null, InputOption::VALUE_NONE, 'Time all the queries individually.')
            ->addOption('allow-no-migration', null, InputOption::VALUE_NONE, 'Do not throw an exception when no changes are detected.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        return 0;
    }
}
