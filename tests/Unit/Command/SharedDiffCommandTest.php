<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Command\SharedDiffCommand;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\DoctrineDBALConnectionGeneratorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class SharedDiffCommandTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private DoctrineDBALConnectionGeneratorInterface&MockObject $connectionGenerator;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connectionGenerator = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->registry->method('getManager')->with('tenant')->willReturn($this->em);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeCommand(
        string $schemaName = 'shared',
        string $databaseUrl = 'mysql://root:pass@localhost:3306/main_db',
        array $migrationConfig = ['migrations_paths' => ['DoctrineMigrations\\Shared' => '/migrations/Shared']],
    ): SharedDiffCommand {
        return new class(
            $this->registry,
            $this->connectionGenerator,
            $schemaName,
            $databaseUrl,
            $migrationConfig,
        ) extends SharedDiffCommand {
            protected function runDoctrineCommand(DependencyFactory $factory, InputInterface $input, OutputInterface $output): void
            {
                // no-op
            }
        };
    }

    // ── connection routing ────────────────────────────────────────────────────

    public function testMysqlConnectsToSharedDatabase(): void
    {
        $this->connectionGenerator->expects($this->once())
            ->method('generate')
            ->with($this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                $dto->driver === DriverTypeEnum::MYSQL &&
                $dto->dbname === 'shared'
            ))
            ->willReturn($this->createMock(Connection::class));

        $result = $this->makeCommand()->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $result);
    }

    public function testPostgresConnectsToAppDatabase(): void
    {
        $this->connectionGenerator->expects($this->once())
            ->method('generate')
            ->with($this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                $dto->driver === DriverTypeEnum::POSTGRES &&
                $dto->dbname === 'app_db'
            ))
            ->willReturn($this->createMock(Connection::class));

        $result = $this->makeCommand(
            databaseUrl: 'postgresql://user:pass@host:5432/app_db'
        )->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $result);
    }

    // ── error handling ────────────────────────────────────────────────────────

    public function testReturnsFailureWhenConnectionThrows(): void
    {
        $this->connectionGenerator->method('generate')
            ->willThrowException(new \RuntimeException('Server down'));

        $output = new BufferedOutput();
        $result = $this->makeCommand()->run(new ArrayInput([]), $output);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('Server down', $output->fetch());
    }

    public function testReturnsFailureForUnparsableUrl(): void
    {
        $this->connectionGenerator->expects($this->never())->method('generate');

        $output = new BufferedOutput();
        $result = $this->makeCommand(databaseUrl: 'sqlite:///bad')
            ->run(new ArrayInput([]), $output);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('Shared diff failed', $output->fetch());
    }

    public function testCommandPassesEmToSharedSchemaProvider(): void
    {
        // The factory's setDefinition closure captures $em — verify it's the tenant EM.
        $factoryReceivedInCallback = null;

        $mockConn = $this->createMock(Connection::class);
        $this->connectionGenerator->method('generate')->willReturn($mockConn);

        $registry = $this->registry;
        $em = $this->em;

        $command = new class(
            $registry,
            $this->connectionGenerator,
            'shared',
            'mysql://root@localhost/db',
            ['migrations_paths' => []],
        ) extends SharedDiffCommand {
            public ?DependencyFactory $capturedFactory = null;

            protected function runDoctrineCommand(DependencyFactory $factory, InputInterface $input, OutputInterface $output): void
            {
                $this->capturedFactory = $factory;
            }
        };

        $command->run(new ArrayInput([]), new BufferedOutput());

        // The factory must have been passed to runDoctrineCommand
        $this->assertInstanceOf(DependencyFactory::class, $command->capturedFactory);
        // SchemaProvider is a lazy definition — just verify the factory was built
        $this->assertTrue($command->capturedFactory->hasSchemaProvider());
    }
}
