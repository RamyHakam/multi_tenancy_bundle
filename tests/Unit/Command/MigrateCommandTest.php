<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Command\MigrateCommand;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrateCommandTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private ContainerInterface&MockObject $container;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private TenantDatabaseManagerInterface&MockObject $tenantDatabaseManager;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->tenantDatabaseManager = $this->createMock(TenantDatabaseManagerInterface::class);
    }

    /**
     * Create a testable MigrateCommand that skips actual migration execution
     */
    private function createTestableMigrateCommand(): MigrateCommand
    {
        return new class(
            $this->registry,
            $this->container,
            $this->eventDispatcher,
            $this->tenantDatabaseManager
        ) extends MigrateCommand {
            protected function runMigrateCommand(InputInterface $input, OutputInterface $output): void
            {
                // Skip actual migration execution in tests
            }
        };
    }

    /**
     * Test that dbId argument is now respected and shows deprecation warning
     */
    public function testDbIdArgumentIsRespectedWithDeprecationWarning(): void
    {
        $tenantDb = TenantConnectionConfigDTO::fromArgs(
            identifier: 'TENANT_123',
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 3306,
            dbname: 'tenant_db',
            user: 'user',
            password: 'pass'
        );

        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with('TENANT_123')
            ->willReturn($tenantDb);

        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('updateTenantDatabaseStatus')
            ->with('TENANT_123', DatabaseStatusEnum::DATABASE_MIGRATED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry
            ->expects($this->once())
            ->method('getManager')
            ->willReturn($entityManager);

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $command = $this->createTestableMigrateCommand();
        $input = new StringInput('init TENANT_123');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $outputContent = $output->fetch();
        
        // Verify the command succeeded
        $this->assertEquals(0, $result);
        
        // Verify deprecation warning is shown
        $this->assertStringContainsString('DEPRECATION: The "dbId" argument is deprecated', $outputContent);
        $this->assertStringContainsString('will be removed in v4.0', $outputContent);
        
        // Verify the specific database was targeted
        $this->assertStringContainsString('Migrating specific database with identifier: TENANT_123', $outputContent);
        $this->assertStringContainsString('Database with identifier "TENANT_123" migrated successfully', $outputContent);
    }

    /**
     * Test that dbId validation works for init type with wrong status
     */
    public function testDbIdValidationFailsForInitWithWrongStatus(): void
    {
        $tenantDb = TenantConnectionConfigDTO::fromArgs(
            identifier: 'TENANT_456',
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_MIGRATED, // Wrong status for init
            host: 'localhost',
            port: 3306,
            dbname: 'tenant_db',
            user: 'user',
            password: 'pass'
        );

        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with('TENANT_456')
            ->willReturn($tenantDb);

        $command = $this->createTestableMigrateCommand();
        $input = new StringInput('init TENANT_456');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $outputContent = $output->fetch();
        
        // Verify the command failed
        $this->assertEquals(1, $result);
        
        // Verify error message is shown
        $this->assertStringContainsString('Database "TENANT_456" is not in CREATED status', $outputContent);
        $this->assertStringContainsString('Current status: DATABASE_MIGRATED', $outputContent);
    }

    /**
     * Test that dbId validation works for update type with wrong status
     */
    public function testDbIdValidationFailsForUpdateWithWrongStatus(): void
    {
        $tenantDb = TenantConnectionConfigDTO::fromArgs(
            identifier: 'TENANT_789',
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED, // Wrong status for update
            host: 'localhost',
            port: 3306,
            dbname: 'tenant_db',
            user: 'user',
            password: 'pass'
        );

        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with('TENANT_789')
            ->willReturn($tenantDb);

        $command = $this->createTestableMigrateCommand();
        $input = new StringInput('update TENANT_789');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $outputContent = $output->fetch();
        
        // Verify the command failed
        $this->assertEquals(1, $result);
        
        // Verify error message is shown
        $this->assertStringContainsString('Database "TENANT_789" is not in MIGRATED status', $outputContent);
        $this->assertStringContainsString('Current status: DATABASE_CREATED', $outputContent);
    }

    /**
     * Test that non-existent dbId shows appropriate error
     */
    public function testNonExistentDbIdShowsError(): void
    {
        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('getTenantDatabaseById')
            ->with('NON_EXISTENT')
            ->willThrowException(new \RuntimeException('Tenant database not found'));

        $command = $this->createTestableMigrateCommand();
        $input = new StringInput('init NON_EXISTENT');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $outputContent = $output->fetch();
        
        // Verify the command failed
        $this->assertEquals(1, $result);
        
        // Verify error message is shown
        $this->assertStringContainsString('Tenant database with identifier "NON_EXISTENT" not found', $outputContent);
        $this->assertStringContainsString('Tenant database not found', $outputContent);
    }

    /**
     * Test that batch migration still works when no dbId is provided
     */
    public function testBatchMigrationWorksWithoutDbId(): void
    {
        $tenantDb1 = TenantConnectionConfigDTO::fromArgs(
            identifier: 'TENANT_1',
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 3306,
            dbname: 'tenant1_db',
            user: 'user',
            password: 'pass'
        );

        $tenantDb2 = TenantConnectionConfigDTO::fromArgs(
            identifier: 'TENANT_2',
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_CREATED,
            host: 'localhost',
            port: 3306,
            dbname: 'tenant2_db',
            user: 'user',
            password: 'pass'
        );

        $this->tenantDatabaseManager
            ->expects($this->once())
            ->method('getTenantDbListByDatabaseStatus')
            ->with(DatabaseStatusEnum::DATABASE_CREATED)
            ->willReturn([$tenantDb1, $tenantDb2]);

        // Track calls to updateTenantDatabaseStatus (PHPUnit 10 compatible)
        $updateCalls = [];
        $this->tenantDatabaseManager
            ->expects($this->exactly(2))
            ->method('updateTenantDatabaseStatus')
            ->willReturnCallback(function (string $identifier, DatabaseStatusEnum $status) use (&$updateCalls): bool {
                $updateCalls[] = [$identifier, $status];
                return true;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry
            ->expects($this->exactly(2))
            ->method('getManager')
            ->willReturn($entityManager);

        $entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $command = $this->createTestableMigrateCommand();
        $input = new StringInput('init'); // No dbId provided
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $outputContent = $output->fetch();
        
        // Verify the command succeeded
        $this->assertEquals(0, $result);
        
        // Verify the correct updates were called
        $this->assertEquals(['TENANT_1', DatabaseStatusEnum::DATABASE_MIGRATED], $updateCalls[0]);
        $this->assertEquals(['TENANT_2', DatabaseStatusEnum::DATABASE_MIGRATED], $updateCalls[1]);
        
        // Verify no deprecation warning is shown (since dbId wasn't used)
        $this->assertStringNotContainsString('DEPRECATION:', $outputContent);
        
        // Verify batch migration messages
        $this->assertStringContainsString('Migrating the new databases', $outputContent);
        $this->assertStringContainsString('All databases migrated successfully', $outputContent);
    }
}
