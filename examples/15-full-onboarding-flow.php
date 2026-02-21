<?php

/**
 * Example 15: Full Tenant Onboarding Flow
 *
 * This example shows the complete flow for onboarding a new tenant:
 * 1. Create tenant config entity
 * 2. Create the database
 * 3. Run migrations
 * 4. Load fixtures
 * 5. Switch to tenant and start using it
 *
 * This can be triggered from an admin panel, API endpoint, or CLI command.
 */

namespace App\Service;

use App\Entity\TenantDbConfig;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Port\TenantDatabaseManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class TenantOnboardingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantDatabaseManagerInterface $tenantManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TenantEntityManager $tenantEntityManager,
        private readonly KernelInterface $kernel,
    ) {}

    /**
     * Full onboarding: config → create DB → migrate → fixtures → ready.
     *
     * @return array{tenant_id: int, database: string, status: string}
     */
    public function onboard(
        string $companyName,
        string $databaseName,
        string $dbHost = '127.0.0.1',
        int $dbPort = 3306,
        string $dbUser = 'tenant_user',
        string $dbPassword = 'secret',
    ): array {
        // Step 1: Create tenant config entity in the main database
        $config = new TenantDbConfig();
        $config->setDbName($databaseName);
        $config->setDriverType(DriverTypeEnum::MYSQL);
        $config->setDbHost($dbHost);
        $config->setDbPort($dbPort);
        $config->setDbUserName($dbUser);
        $config->setDbPassword($dbPassword);
        $config->setDatabaseStatus(DatabaseStatusEnum::DATABASE_NOT_CREATED);
        $config->setCompanyName($companyName);

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $tenantId = $config->getId();

        // Step 2: Create the actual database on the server
        $dto = TenantConnectionConfigDTO::fromArgs(
            identifier: $tenantId,
            driver: DriverTypeEnum::MYSQL,
            dbStatus: DatabaseStatusEnum::DATABASE_NOT_CREATED,
            host: $dbHost,
            port: $dbPort,
            dbname: $databaseName,
            user: $dbUser,
            password: $dbPassword,
        );

        $this->tenantManager->createTenantDatabase($dto);
        $this->tenantManager->updateTenantDatabaseStatus(
            $tenantId,
            DatabaseStatusEnum::DATABASE_CREATED
        );

        // Step 3: Run migrations via the console command
        $this->runCommand('tenant:migrations:migrate', [
            'type' => 'init',
            'dbId' => (string) $tenantId,
            '--allow-no-migration' => true,
        ]);

        // Step 4: Load fixtures
        $this->runCommand('tenant:fixtures:load', [
            'dbId' => (string) $tenantId,
            '--append' => true,
        ]);

        // Step 5: Tenant is ready!
        return [
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'status' => 'ready',
        ];
    }

    /**
     * Switch to a tenant and verify the connection works.
     */
    public function verifyTenant(int $tenantId): bool
    {
        $this->dispatcher->dispatch(new SwitchDbEvent((string) $tenantId));

        $result = $this->tenantEntityManager
            ->getConnection()
            ->executeQuery('SELECT 1')
            ->fetchOne();

        return $result === 1;
    }

    private function runCommand(string $command, array $arguments): string
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge(
            ['command' => $command],
            $arguments
        ));
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $exitCode = $application->run($input, $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Command "%s" failed with exit code %d: %s',
                $command,
                $exitCode,
                $output->fetch()
            ));
        }

        return $output->fetch();
    }
}


// ──────────────────────────────────────────────
// Using the onboarding service from a controller
// ──────────────────────────────────────────────

namespace App\Controller\Admin;

use App\Service\TenantOnboardingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TenantAdminController extends AbstractController
{
    public function __construct(
        private readonly TenantOnboardingService $onboarding,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $result = $this->onboarding->onboard(
            companyName: $data['company_name'],
            databaseName: 'tenant_' . strtolower($data['company_slug']),
        );

        return new JsonResponse($result, 201);
    }
}

/*
# CLI equivalent of the full onboarding flow:

# 1. Insert tenant config into your main database (your app logic)

# 2. Create the database:
php bin/console tenant:database:create --dbid=42

# 3. Run initial migrations:
php bin/console tenant:migrations:migrate init 42

# 4. Load fixtures:
php bin/console tenant:fixtures:load 42 --append

# Done! The tenant is fully set up.
*/
