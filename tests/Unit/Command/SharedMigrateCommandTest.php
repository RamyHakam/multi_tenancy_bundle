<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Hakam\MultiTenancyBundle\Command\SharedMigrateCommand;
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

class SharedMigrateCommandTest extends TestCase
{
    private DoctrineDBALConnectionGeneratorInterface&MockObject $connectionGenerator;

    protected function setUp(): void
    {
        $this->connectionGenerator = $this->createMock(DoctrineDBALConnectionGeneratorInterface::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeCommand(
        string $schemaName = 'shared',
        string $databaseUrl = 'mysql://root:pass@localhost:3306/main_db',
        array $migrationConfig = ['migrations_paths' => ['DoctrineMigrations\\Shared' => '/migrations/Shared']],
    ): SharedMigrateCommand {
        return new class(
            $this->connectionGenerator,
            $schemaName,
            $databaseUrl,
            $migrationConfig,
        ) extends SharedMigrateCommand {
            protected function runDoctrineCommand(DependencyFactory $factory, InputInterface $input, OutputInterface $output): void
            {
                // no-op: avoid real Doctrine migration execution
            }
        };
    }

    // ── connection routing ────────────────────────────────────────────────────

    public function testMysqlConnectsToSharedDatabase(): void
    {
        $this->connectionGenerator->expects($this->once())
            ->method('generate')
            ->with($this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                $dto->driver  === DriverTypeEnum::MYSQL &&
                $dto->dbname  === 'shared' // not 'main_db' — switched to shared DB
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
                $dto->driver  === DriverTypeEnum::POSTGRES &&
                $dto->dbname  === 'app_db' // unchanged from DATABASE_URL
            ))
            ->willReturn($this->createMock(Connection::class));

        $result = $this->makeCommand(
            databaseUrl: 'postgresql://user:pass@host:5432/app_db'
        )->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $result);
    }

    public function testMysqlUsesCustomSharedSchemaName(): void
    {
        $this->connectionGenerator->expects($this->once())
            ->method('generate')
            ->with($this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                $dto->dbname === 'acme_shared'
            ))
            ->willReturn($this->createMock(Connection::class));

        $result = $this->makeCommand(schemaName: 'acme_shared')->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $result);
    }

    // ── error handling ────────────────────────────────────────────────────────

    public function testReturnsFailureWhenConnectionThrows(): void
    {
        $this->connectionGenerator->method('generate')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $output = new BufferedOutput();
        $result = $this->makeCommand()->run(new ArrayInput([]), $output);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('Connection refused', $output->fetch());
    }

    public function testReturnsFailureForUnparsableUrl(): void
    {
        $this->connectionGenerator->expects($this->never())->method('generate');

        $output = new BufferedOutput();
        $result = $this->makeCommand(databaseUrl: 'sqlite:///bad')
            ->run(new ArrayInput([]), $output);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('Shared migration failed', $output->fetch());
    }
}
