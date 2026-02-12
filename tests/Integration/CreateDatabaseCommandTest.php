<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateDatabaseCommandTest extends IntegrationTestCase
{
    private CommandTester $commandTester;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/hakam_test_dbs_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $application = new Application(static::$kernel);
        $command = $application->find('tenant:database:create');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        // Clean up temp database files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testCreateDatabaseForSpecificTenantId(): void
    {
        $dbPath = $this->tempDir . '/tenant_create.sqlite';
        $tenant = $this->insertTenantConfig(
            dbName: $dbPath,
            status: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $this->commandTester->execute(['--dbid' => $tenant->getId()]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('created successfully', $this->commandTester->getDisplay());

        // Verify status was updated
        $this->getDefaultEntityManager()->clear();
        $updated = $this->getDefaultEntityManager()
            ->getRepository(Fixtures\Entity\TenantDbConfig::class)
            ->find($tenant->getId());
        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $updated->getDatabaseStatus());
    }

    public function testCreateDatabaseSkipsAlreadyCreatedDatabase(): void
    {
        $tenant = $this->insertTenantConfig(
            dbName: 'already_created.sqlite',
            status: DatabaseStatusEnum::DATABASE_CREATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $this->commandTester->execute(['--dbid' => $tenant->getId()]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('already exists', $this->commandTester->getDisplay());
    }

    public function testCreateAllMissingDatabases(): void
    {
        $dbPath1 = $this->tempDir . '/tenant_all_1.sqlite';
        $dbPath2 = $this->tempDir . '/tenant_all_2.sqlite';

        $this->insertTenantConfig(
            dbName: $dbPath1,
            status: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            driver: DriverTypeEnum::SQLITE,
        );
        $this->insertTenantConfig(
            dbName: $dbPath2,
            status: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            driver: DriverTypeEnum::SQLITE,
        );

        $this->commandTester->execute(['--all' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('created successfully', $this->commandTester->getDisplay());
    }

    public function testCreateDatabaseWithBothOptionsReturnsError(): void
    {
        $this->commandTester->execute(['--dbid' => '1', '--all' => true]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Cannot use', $this->commandTester->getDisplay());
    }

    public function testCreateDatabaseForNonexistentIdReturnsError(): void
    {
        $this->commandTester->execute(['--dbid' => '999999']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Failed', $this->commandTester->getDisplay());
    }
}
