<?php


namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
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
class DiffCommand extends Command
{
    use CommandTrait;

    /**
     * @var ManagerRegistry
     */
    private $registry;
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(ManagerRegistry $registry, ContainerInterface $container, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
        $this->registry = $registry;
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function configure(): void
    {
        $this
            ->setName('tenant:migrations:diff')
            ->setAliases(['t:m:d'])
            ->setDescription('Proxy to launch doctrine:migrations:diff with custom database .')
            ->addArgument('dbId', InputArgument::OPTIONAL, 'Tenant Db Identifier to create migration.')
            ->addOption('allow-empty-diff', null, InputOption::VALUE_NONE, 'Don\'t throw an exception if no migration is available (CI).');

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newInput = new ArrayInput([
            '--allow-empty-diff' => $input->getOption('allow-empty-diff'),
        ]);
        $newInput->setInteractive($input->isInteractive());
        $otherCommand = new \Doctrine\Migrations\Tools\Console\Command\DiffCommand($this->getDependencyFactory($input));
        $otherCommand->run($newInput, $output);

        return 0;
    }
}