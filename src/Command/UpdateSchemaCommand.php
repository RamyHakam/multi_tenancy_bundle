<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Command\Proxy\UpdateSchemaDoctrineCommand;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class UpdateSchemaCommand extends Command
{
    private ManagerRegistry $registry;

    private ContainerInterface $container;

    private EventDispatcherInterface $eventDispatcher;

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
            ->setName('tenant:schema:update')
            ->setDescription('Proxy to launch doctrine:schema:update with custom database .')
            ->addArgument('dbId', InputArgument::REQUIRED, 'Tenant Db Identifier to create migration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $newInput = new ArrayInput([
            'command' => 'doctrine:schema:update',
            '--force' => true
        ]);

        $newInput->setInteractive($input->isInteractive());
        $otherCommand = new UpdateSchemaDoctrineCommand();
        $this->getDependencyFactory($input);
        $otherCommand->setApplication(new Application($this->container->get('kernel')));
        $otherCommand->run($newInput, $output);

        return 0;
    }

    private function getDependencyFactory(InputInterface $input): DependencyFactory
    {
        $switchEvent = new SwitchDbEvent($input->getArgument('dbId'));
        $this->eventDispatcher->dispatch($switchEvent);
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager('tenant');

        $tenantMigrationConfig = new ConfigurationArray(
            $this->container->getParameter('tenant_doctrine_migration')
        );

        return DependencyFactory::fromEntityManager($tenantMigrationConfig, new ExistingEntityManager($em));
    }
}
