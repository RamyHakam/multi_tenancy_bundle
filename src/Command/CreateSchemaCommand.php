<?php

namespace Hakam\MultiTenancyBundle\Command;

use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Creates the shared schema (MySQL: a separate database; PostgreSQL: a schema inside the
 * application database) that hosts all #[TenantShared] entities.
 *
 * The command is idempotent: running it multiple times is safe.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[AsCommand(
    name: 'tenant:schema:create',
    description: 'Create the shared schema that hosts #[TenantShared] entities.',
)]
final class CreateSchemaCommand extends Command
{
    public function __construct(
        private readonly TenantDatabaseManagerInterface $tenantDatabaseManager,
        private readonly string $sharedSchemaName,
        private readonly string $databaseUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            'Creates the shared schema (or database for MySQL) that stores entities marked ' .
            'with #[TenantShared]. Safe to run multiple times — existing schemas are not touched.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dto = DatabaseUrlParser::parse($this->databaseUrl);
            $this->tenantDatabaseManager->createSharedSchema($dto, $this->sharedSchemaName);
            $output->writeln(sprintf('<info>Shared schema "%s" is ready.</info>', $this->sharedSchemaName));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Failed to create shared schema: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

}
