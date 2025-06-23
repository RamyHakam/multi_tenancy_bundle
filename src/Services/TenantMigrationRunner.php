<?php

namespace Hakam\MultiTenancyBundle\Services;

use Hakam\MultiTenancyBundle\Command\MigrateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class TenantMigrationRunner
{
    public function __construct(private  readonly  MigrateCommand $migrateCommand)
    {
    }

    /**
     * @throws \Exception
     */
    public function runMigrations(int $tenantDbId): void
    {
        $app = new Application();
        $app->setAutoExit(false);

        $input = new ArrayInput([
            'type' => 'init',
            'dbId' => $tenantDbId,
        ]);
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $exitCode = $this->migrateCommand->run($input, $output);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Tenant migration failed: ' . $output->fetch());
        }
    }
}