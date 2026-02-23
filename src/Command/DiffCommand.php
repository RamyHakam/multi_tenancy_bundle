<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class DiffCommand extends TenantCommand
{

    public function __construct(
        private readonly ManagerRegistry          $registry,
        private readonly ContainerInterface       $container,
        private readonly EventDispatcherInterface $eventDispatcher,
    )
    {
        parent::__construct(
            $registry,
            $this->container,
            $eventDispatcher
        );
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
        // getDependencyFactory dispatches SwitchDbEvent, which activates the tenant context and
        // resets the metadata cache — TenantMetadataListener then re-applies schema routing.
        $factory = $this->getDependencyFactory($input);

        // Exclude #[TenantShared] entities from the tenant-specific diff so that their DDL
        // does not appear in tenant migration files. Shared entity migrations live in a
        // dedicated path and are managed by tenant:migrations:shared:diff / :shared:migrate.
        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager('tenant');
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            $reflClass = $metadata->getReflectionClass();
            if ($reflClass && !empty($reflClass->getAttributes(TenantShared::class))) {
                $metadata->isMappedSuperclass = true;
            }
        }

        $newInput = new ArrayInput([
            '--allow-empty-diff' => $input->getOption('allow-empty-diff'),
        ]);
        $newInput->setInteractive($input->isInteractive());
        $this->runDiffCommand($factory, $newInput, $output);

        // Clear the metadata cache so subsequent tenant switches get a clean slate.
        $this->clearTenantEmMetadata($em);

        return 0;
    }

    /**
     * @codeCoverageIgnore Thin delegation to Doctrine DiffCommand; overridden in unit tests.
     */
    protected function runDiffCommand(
        \Doctrine\Migrations\DependencyFactory $factory,
        \Symfony\Component\Console\Input\InputInterface $newInput,
        OutputInterface $output,
    ): void {
        (new \Doctrine\Migrations\Tools\Console\Command\DiffCommand($factory))->run($newInput, $output);
    }
}
