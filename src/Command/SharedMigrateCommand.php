<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs Doctrine Migrations for the shared schema that hosts #[TenantShared] entities.
 *
 * MySQL:      migration files contain unqualified DDL; the connection points at the shared
 *             database (dbname = shared_schema_name).
 * PostgreSQL: migration files contain schema-qualified DDL ("shared"."table"); the connection
 *             points at the application database (dbname parsed from DATABASE_URL).
 * SQLite:     no separate schema concept; migrates the same database file.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[AsCommand(
    name: 'tenant:migrations:shared:migrate',
    description: 'Run migrations for the shared schema that hosts #[TenantShared] entities.',
)]
final class SharedMigrateCommand extends Command
{
    public function __construct(
        private readonly DoctrineDBALConnectionGeneratorInterface $connectionGenerator,
        private readonly string $sharedSchemaName,
        private readonly string $databaseUrl,
        private readonly array $sharedMigrationConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute as a dry run.')
            ->addOption('allow-no-migration', null, InputOption::VALUE_NONE, 'Do not throw if no migrations are available.')
            ->setHelp('Applies pending migration files from the shared migration path to the shared schema.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dto = DatabaseUrlParser::parse($this->databaseUrl);
            $connection = $this->buildSharedConnection($dto);

            $config = new ConfigurationArray($this->sharedMigrationConfig);
            $factory = DependencyFactory::fromConnection($config, new ExistingConnection($connection));

            $this->runDoctrineCommand($factory, $input, $output);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Shared migration failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * @codeCoverageIgnore Thin delegation to Doctrine MigrateCommand; overridden in unit tests.
     */
    protected function runDoctrineCommand(DependencyFactory $factory, InputInterface $input, OutputInterface $output): void
    {
        $newInput = new ArrayInput([
            '--allow-no-migration' => $input->getOption('allow-no-migration'),
            '--dry-run' => $input->getOption('dry-run'),
        ]);
        $newInput->setInteractive($input->isInteractive());
        (new \Doctrine\Migrations\Tools\Console\Command\MigrateCommand($factory))->run($newInput, $output);
    }

    private function buildSharedConnection(TenantConnectionConfigDTO $dto): Connection
    {
        if ($dto->driver === DriverTypeEnum::MYSQL) {
            // For MySQL connect directly to the shared database so that
            // unqualified DDL in migration files targets the correct database.
            $sharedDto = TenantConnectionConfigDTO::fromArgs(
                identifier: $dto->identifier,
                driver: $dto->driver,
                dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
                host: $dto->host,
                port: $dto->port,
                dbname: $this->sharedSchemaName,
                user: $dto->user,
                password: $dto->password,
            );
            return $this->connectionGenerator->generate($sharedDto);
        }

        // PostgreSQL / SQLite: connect to the application database; migration SQL
        // uses schema-qualified names (PG) or is schema-less (SQLite).
        return $this->connectionGenerator->generate($dto);
    }
}
