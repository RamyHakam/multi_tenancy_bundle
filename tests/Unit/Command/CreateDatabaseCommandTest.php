<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Hakam\MultiTenancyBundle\Command\CreateDatabaseCommand;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Exception;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CreateDatabaseCommandTest extends TestCase
{
    private TenantDatabaseManagerInterface $mockManager;
    private EventDispatcherInterface $mockEventDispatcher;
    private CreateDatabaseCommand $command;

    protected function setUp(): void
    {
        $this->mockManager = $this->createMock(TenantDatabaseManagerInterface::class);
        $this->mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->command = new CreateDatabaseCommand($this->mockManager, $this->mockEventDispatcher);
    }

    public function testCreateDatabaseWithDbidOption(): void
    {
        $dbConfig = $this->createTenantConfig(5, DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $this->mockManager->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with(5)
            ->willReturn($dbConfig);

        $this->mockManager->expects($this->once())
            ->method('createTenantDatabase')
            ->with($dbConfig)
            ->willReturn(true);

        $this->mockManager->expects($this->once())
            ->method('updateTenantDatabaseStatus')
            ->with(5, DatabaseStatusEnum::DATABASE_CREATED);

        $input = new ArrayInput(['--dbid' => '5']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Database tenant_5 created successfully for tenant ID 5', $output->fetch());
    }

    public function testCreateDatabaseWithDbidOptionWhenAlreadyExists(): void
    {
        $dbConfig = $this->createTenantConfig(5, DatabaseStatusEnum::DATABASE_CREATED);

        $this->mockManager->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with(5)
            ->willReturn($dbConfig);

        $this->mockManager->expects($this->never())
            ->method('createTenantDatabase');

        $input = new ArrayInput(['--dbid' => '5']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Database tenant_5 already exists', $output->fetch());
    }

    public function testCreateDatabaseWithDbidOptionWhenTenantNotFound(): void
    {
        $this->mockManager->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with(999)
            ->willThrowException(new RuntimeException('Tenant database with identifier "999" not found'));

        $input = new ArrayInput(['--dbid' => '999']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('Failed to create database for tenant ID 999', $output->fetch());
    }

    public function testCreateDatabaseWithAllOption(): void
    {
        $dbConfigs = [
            $this->createTenantConfig(1, DatabaseStatusEnum::DATABASE_NOT_CREATED),
            $this->createTenantConfig(2, DatabaseStatusEnum::DATABASE_NOT_CREATED),
        ];

        $this->mockManager->expects($this->once())
            ->method('listMissingDatabases')
            ->willReturn($dbConfigs);

        $this->mockManager->expects($this->exactly(2))
            ->method('createTenantDatabase')
            ->willReturn(true);

        $this->mockManager->expects($this->exactly(2))
            ->method('updateTenantDatabaseStatus');

        $input = new ArrayInput(['--all' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('Database tenant_1 created successfully', $outputContent);
        $this->assertStringContainsString('Database tenant_2 created successfully', $outputContent);
    }

    public function testCreateDatabaseWithAllOptionWhenNoDatabasesToCreate(): void
    {
        $this->mockManager->expects($this->once())
            ->method('listMissingDatabases')
            ->willReturn([]);

        $input = new ArrayInput(['--all' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('No new databases to create', $output->fetch());
    }

    public function testCreateDatabaseWithBothDbidAndAllOptionsThrowsError(): void
    {
        $input = new ArrayInput(['--dbid' => '5', '--all' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('Cannot use --dbid and --all options together', $output->fetch());
    }

    public function testCreateDatabaseWithoutOptionsUsesBackwardCompatibleBehavior(): void
    {
        $dbConfigs = [$this->createTenantConfig(1, DatabaseStatusEnum::DATABASE_NOT_CREATED)];

        $this->mockManager->expects($this->once())
            ->method('listMissingDatabases')
            ->willReturn($dbConfigs);

        $this->mockManager->expects($this->once())
            ->method('createTenantDatabase')
            ->willReturn(true);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Database tenant_1 created successfully', $output->fetch());
    }

    public function testCreateDatabaseFailureWithDbidOption(): void
    {
        $dbConfig = $this->createTenantConfig(5, DatabaseStatusEnum::DATABASE_NOT_CREATED);

        $this->mockManager->expects($this->once())
            ->method('getTenantDatabaseById')
            ->willReturn($dbConfig);

        $this->mockManager->expects($this->once())
            ->method('createTenantDatabase')
            ->willReturn(false);

        $input = new ArrayInput(['--dbid' => '5']);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(1, $result);
        $this->assertStringContainsString('Failed to create database', $output->fetch());
    }

    private function createTenantConfig(int $id, DatabaseStatusEnum $status): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            identifier: $id,
            driver: DriverTypeEnum::MYSQL,
            dbStatus: $status,
            host: 'localhost',
            port: 3306,
            dbname: "tenant_{$id}",
            user: 'user',
            password: 'password'
        );
    }
}
