<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

final class DbSwitchEventListener implements EventSubscriberInterface, ResetInterface
{
    private ?string $currentTenantIdentifier = null;
    private ?string $currentTenantDbName = null;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly TenantConfigProviderInterface $tenantConfigProvider,
        private readonly TenantEntityManager $tenantEntityManager,
        private readonly string $databaseURL,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SwitchDbEvent::class => 'onSwitchDb',
        ];
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

        $connection = $this->doctrine->getConnection('tenant');

        // Clear EM before switching
        $this->tenantEntityManager->clear();

        $connection->switchConnection($params);

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
