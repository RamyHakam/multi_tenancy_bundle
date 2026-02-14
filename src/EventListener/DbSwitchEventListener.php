<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use Hakam\MultiTenancyBundle\Port\TenantConfigProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class DbSwitchEventListener implements EventSubscriberInterface
{
    private ?array $currentTenantParams = null;
    private ?string $currentTenantDbName = null;
    private ?string $currentTenantIdentifier = null;

    public function __construct(
        private readonly ContainerInterface            $container,
        private readonly TenantConfigProviderInterface $tenantConfigProvider,
        private readonly TenantEntityManager           $tenantEntityManager,
        private readonly string                        $databaseURL,
        private readonly ?EventDispatcherInterface     $eventDispatcher = null,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return
            [
                SwitchDbEvent::class => 'onHakamMultiTenancyBundleEventSwitchDbEvent',
            ];
    }

    public function onHakamMultiTenancyBundleEventSwitchDbEvent(SwitchDbEvent $switchDbEvent): void
    {
        $tenantDbConfigDTO = $this->tenantConfigProvider->getTenantConnectionConfig($switchDbEvent->getDbIndex());

        $previousTenantIdentifier = $this->currentTenantIdentifier;
        $previousDatabaseName = $this->currentTenantDbName;

        $tenantConnection = $this->container->get('doctrine')->getConnection('tenant');

        $params = [
            'dbname' => $tenantDbConfigDTO->dbname,
            'user' => $tenantDbConfigDTO->user ?? $this->parseDatabaseUrl($this->databaseURL)['user'],
            'password' => $tenantDbConfigDTO->password ?? $this->parseDatabaseUrl($this->databaseURL)['password'],
            'host' => $tenantDbConfigDTO->host ?? $this->parseDatabaseUrl($this->databaseURL)['host'],
            'port' => $tenantDbConfigDTO->port ?? $this->parseDatabaseUrl($this->databaseURL)['port'],
        ];

        // Skip if already connected with the same parameters
        if ($this->currentTenantParams !== null && $this->currentTenantParams === $params) {
            return;
        }

        $this->tenantEntityManager->clear();

        $tenantConnection->switchConnection($params);
        $this->currentTenantDbName = $tenantDbConfigDTO->dbname;
        $this->currentTenantIdentifier = $switchDbEvent->getDbIndex();
        $this->currentTenantParams = $params;

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new TenantSwitchedEvent(
                $switchDbEvent->getDbIndex(),
                $tenantDbConfigDTO,
                $previousTenantIdentifier,
                $previousDatabaseName

            ));
        }
    }

    private function parseDatabaseUrl(string $databaseURL): array
    {
        $url = parse_url($databaseURL);
        return [
            'dbname' => substr($url['path'], 1),
            'user' => $url['user'],
            'password' => $url['pass'],
            'host' => $url['host'],
            'port' => $url['port'],
        ];
    }
}
