<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Hakam\MultiTenancyBundle\Command\CreateSchemaCommand;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Exception\MultiTenancyException;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CreateSchemaCommandTest extends TestCase
{
    private TenantDatabaseManagerInterface&MockObject $manager;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(TenantDatabaseManagerInterface::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeCommand(
        string $schemaName = 'shared',
        string $databaseUrl = 'mysql://root:secret@localhost:3306/main_db'
    ): CreateSchemaCommand {
        return new CreateSchemaCommand($this->manager, $schemaName, $databaseUrl);
    }

    private function execute(CreateSchemaCommand $command): array
    {
        $output = new BufferedOutput();
        $status = $command->run(new ArrayInput([]), $output);
        return [$status, $output->fetch()];
    }

    // ── happy-path: URL parsing ───────────────────────────────────────────────

    public function testExecuteSuccessWithMysqlUrl(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->driver   === DriverTypeEnum::MYSQL &&
                    $dto->host     === 'localhost' &&
                    $dto->port     === 3306 &&
                    $dto->dbname   === 'main_db' &&
                    $dto->user     === 'root' &&
                    $dto->password === 'secret'
                ),
                'shared'
            )
            ->willReturn(true);

        [$status, $text] = $this->execute($this->makeCommand());

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('shared', $text);
        $this->assertStringContainsString('ready', $text);
    }

    public function testExecuteSuccessWithPostgresUrl(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->driver === DriverTypeEnum::POSTGRES &&
                    $dto->host   === 'pg-host' &&
                    $dto->port   === 5432 &&
                    $dto->dbname === 'app_db' &&
                    $dto->user   === 'pguser'
                ),
                'shared'
            )
            ->willReturn(true);

        [$status, $text] = $this->execute(
            $this->makeCommand('shared', 'postgresql://pguser:pgpass@pg-host:5432/app_db')
        );

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('ready', $text);
    }

    public function testExecuteSuccessWithSqliteUrl(): void
    {
        // PHP's parse_url cannot handle the triple-slash sqlite:/// form;
        // use sqlite://localhost/<path> instead.
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->driver === DriverTypeEnum::SQLITE
                ),
                'shared'
            )
            ->willReturn(true);

        [$status] = $this->execute(
            $this->makeCommand('shared', 'sqlite://localhost/var/data/app.sqlite')
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteReturnsFailureForUnparsableUrl(): void
    {
        // sqlite:/// triple-slash form returns false from parse_url
        $this->manager->expects($this->never())->method('createSharedSchema');

        [$status, $text] = $this->execute(
            $this->makeCommand('shared', 'sqlite:///var/data/app.sqlite')
        );

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Failed to create shared schema', $text);
    }

    // ── URL parsing edge cases ────────────────────────────────────────────────

    public function testDefaultMysqlPortWhenAbsentFromUrl(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->port === 3306
                ),
                'shared'
            )
            ->willReturn(true);

        [$status] = $this->execute(
            $this->makeCommand('shared', 'mysql://root@localhost/mydb')
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testDefaultPostgresPortWhenAbsentFromUrl(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->port === 5432
                ),
                'shared'
            )
            ->willReturn(true);

        [$status] = $this->execute(
            $this->makeCommand('shared', 'postgresql://user@host/db')
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testNullPasswordWhenAbsentFromUrl(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->password === null
                ),
                'shared'
            )
            ->willReturn(true);

        [$status] = $this->execute(
            $this->makeCommand('shared', 'mysql://root@localhost/mydb')
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testUrlEncodedPasswordIsDecoded(): void
    {
        // Password contains "@" encoded as %40
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with(
                $this->callback(fn (TenantConnectionConfigDTO $dto): bool =>
                    $dto->password === 'p@ss!word'
                ),
                'shared'
            )
            ->willReturn(true);

        [$status] = $this->execute(
            $this->makeCommand('shared', 'mysql://root:p%40ss%21word@localhost/db')
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testCustomSchemaNameIsPassedToManager(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->with($this->anything(), 'acme_shared')
            ->willReturn(true);

        [$status, $text] = $this->execute(
            $this->makeCommand('acme_shared')
        );

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('acme_shared', $text);
    }

    // ── error handling ────────────────────────────────────────────────────────

    public function testExecuteReturnsFailureWhenManagerThrows(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->willThrowException(new MultiTenancyException('Cannot connect to server'));

        [$status, $text] = $this->execute($this->makeCommand());

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Failed to create shared schema', $text);
        $this->assertStringContainsString('Cannot connect to server', $text);
    }

    public function testExecuteReturnsFailureOnGenericException(): void
    {
        $this->manager->expects($this->once())
            ->method('createSharedSchema')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        [$status, $text] = $this->execute($this->makeCommand());

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Unexpected error', $text);
    }
}
