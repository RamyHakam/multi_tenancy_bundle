<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Hakam\MultiTenancyBundle\Doctrine\DBAL\TenantConnectionSwitcher;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

final class DbSwitchEventListener implements ResetInterface
{
    private ?string $currentTenantIdentifier = null;
    private ?string $currentTenantDbName = null;

    public function __construct(
        private readonly TenantConnectionSwitcher $connectionSwitcher,
        private readonly TenantConfigProviderInterface $tenantConfigProvider,
        private readonly TenantEntityManager $tenantEntityManager,
        private readonly string $databaseURL,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function onSwitchDb(SwitchDbEvent $event): void
    {
        $tenantIdentifier = $event->getDbIndex();

        if ($this->currentTenantIdentifier === $tenantIdentifier) {
            return;
        }

        $tenantConfig = $this->tenantConfigProvider
            ->getTenantConnectionConfig($tenantIdentifier);

        $previousTenantIdentifier = $this->currentTenantIdentifier;
        $previousDatabaseName = $this->currentTenantDbName;

        $params = $this->buildConnectionParams($tenantConfig);

        // Clear EM before switching
        $this->tenantEntityManager->clear();

        $this->connectionSwitcher->switchConnection($params);

        $this->currentTenantIdentifier = $tenantIdentifier;
        $this->currentTenantDbName = $tenantConfig->dbname;

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(
                new TenantSwitchedEvent(
                    $tenantIdentifier,
                    $tenantConfig,
                    $previousTenantIdentifier,
                    $previousDatabaseName
                )
            );
        }
    }

    private function buildConnectionParams(object $tenantConfig): array
    {
        $defaults = $this->parseDatabaseUrl($this->databaseURL);

        $params = [
            'dbname'   => (string) $tenantConfig->dbname,
            'user'     => (string) ($tenantConfig->user ?? $defaults['user']),
            'password' => (string) ($tenantConfig->password ?? $defaults['password']),
            'host'     => (string) ($tenantConfig->host ?? $defaults['host']),
            'port'     => (string) ($tenantConfig->port ?? $defaults['port']),
        ];

        ksort($params);

        return $params;
    }

    private function parseDatabaseUrl(string $databaseURL): array
    {
        $url = parse_url($databaseURL);

        return [
            'dbname'   => isset($url['path']) ? ltrim($url['path'], '/') : null,
            'user'     => $url['user'] ?? null,
            'password' => $url['pass'] ?? null,
            'host'     => $url['host'] ?? null,
            'port'     => $url['port'] ?? null,
        ];
    }

    public function reset(): void
    {
        $this->currentTenantIdentifier = null;
        $this->currentTenantDbName = null;
    }
}
