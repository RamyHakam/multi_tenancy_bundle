<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Purger\TenantORMPurgerFactory;
use Hakam\MultiTenancyBundle\Services\TenantFixtureLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(name: 'tenant:fixtures:load', description: 'Load tenant fixtures', aliases: ['t:f:l'])]
class LoadTenantFixtureCommand  extends TenantCommand
{
    use CommandTrait;
    private  SymfonyFixturesLoader  $fixturesLoader;
    private EntityManagerInterface $tenantEntityManager;

    private  array $purgerFactories= [];

    public function __construct(
    private readonly ManagerRegistry          $registry,
   private readonly ContainerInterface        $container,
    private readonly EventDispatcherInterface $eventDispatcher,
   private    readonly TenantFixtureLoader    $tenantFixtureLoader,
    )
    {
        parent::__construct($registry, $container, $eventDispatcher);
        $this->fixturesLoader = new SymfonyFixturesLoader();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Load tenant fixtures to the tenant database')
            ->addArgument('dbId', InputArgument::OPTIONAL, 'Tenant DB Identifier to load fixtures into.')
            ->addOption('append', null, InputOption::VALUE_NONE)
            ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)
            ->addOption('purger', null, InputOption::VALUE_REQUIRED, 'The purger to use for this command', 'tenant')
            ->addOption('purge-exclusions', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'List of database tables to ignore while purging')
            ->addOption('purge-with-truncate', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $doctrineFixturesCommand = new LoadDataFixturesDoctrineCommand(
            $this->fixturesLoader,
            $this->registry,
            $this->purgerFactories
        );

        $args = [
            '--append' => $input->getOption('append'),
            '--group' => $input->getOption('group'),
            '--purger' => $input->getOption('purger'),
            '--purge-exclusions' => $input->getOption('purge-exclusions'),
            '--purge-with-truncate' => $input->getOption('purge-with-truncate'),
            '--em' => 'tenant',
        ];

        $newInput = new ArrayInput($args);
        $newInput->setInteractive($input->isInteractive());

        return $doctrineFixturesCommand->run($newInput, $output);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->tenantEntityManager = $this->getDependencyFactory($input)->getEntityManager();
        foreach ($this->tenantFixtureLoader->getFixtures() as $fixture) {
            $this->fixturesLoader->addFixture($fixture);
        }
        $this->purgerFactories = [
            'tenant' => new TenantORMPurgerFactory(),
        ];
    }
}