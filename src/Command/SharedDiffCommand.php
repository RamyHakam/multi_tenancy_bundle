<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
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
 * Generates a Doctrine migration diff for the shared schema that hosts #[TenantShared] entities.
 *
 * Only entities marked with #[TenantShared] contribute to the expected schema; all other
 * entities in the tenant entity manager are temporarily hidden for this comparison.
 *
 * MySQL:      connects to the shared database (dbname = shared_schema_name); generated DDL
 *             is unqualified so it targets that database directly.
 * PostgreSQL: connects to the application database; generated DDL is schema-qualified
 *             ("shared"."table_name").
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
#[AsCommand(
    name: 'tenant:migrations:shared:diff',
    description: 'Generate a migration diff for the shared schema that hosts #[TenantShared] entities.',
)]
final class SharedDiffCommand extends Command
{
    public function __construct(
        private readonly ManagerRegistry $registry,
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
            ->addOption('allow-empty-diff', null, InputOption::VALUE_NONE, "Don't throw if no changes are detected.")
            ->setHelp(
                'Compares the ORM mapping of #[TenantShared] entities against the actual shared ' .
                'schema and generates a migration file with the required DDL changes.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dto = DatabaseUrlParser::parse($this->databaseUrl);
            $connection = $this->buildSharedConnection($dto);

            /** @var EntityManagerInterface $em */
            $em = $this->registry->getManager('tenant');

            $config = new ConfigurationArray($this->sharedMigrationConfig);
            $factory = DependencyFactory::fromConnection($config, new ExistingConnection($connection));

            // Override the schema provider so the diff compares only #[TenantShared] entities.
            $driver = $dto->driver;
            $schemaName = $this->sharedSchemaName;
            $factory->setDefinition(
                SchemaProvider::class,
                static fn (): SchemaProvider => new SharedSchemaProvider($em, $schemaName, $driver)
            );

            $newInput = new ArrayInput([
                '--allow-empty-diff' => $input->getOption('allow-empty-diff'),
            ]);
            $newInput->setInteractive($input->isInteractive());
            $this->runDoctrineCommand($factory, $newInput, $output);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Shared diff failed: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    /**
     * @codeCoverageIgnore Thin delegation to Doctrine DiffCommand; overridden in unit tests.
     */
    protected function runDoctrineCommand(DependencyFactory $factory, InputInterface $input, OutputInterface $output): void
    {
        (new \Doctrine\Migrations\Tools\Console\Command\DiffCommand($factory))->run($input, $output);
    }

    private function buildSharedConnection(TenantConnectionConfigDTO $dto): Connection
    {
        if ($dto->driver === DriverTypeEnum::MYSQL) {
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

        return $this->connectionGenerator->generate($dto);
    }
}
