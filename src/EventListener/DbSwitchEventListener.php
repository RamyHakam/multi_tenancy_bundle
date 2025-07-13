<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Port\TenantConnectionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class DbSwitchEventListener implements EventSubscriberInterface
{

    public function __construct(
        private readonly ContainerInterface               $container,
        private readonly TenantConnectionManagerInterface $tenantConfigProvider,
        private readonly TenantEntityManager              $tenantEntityManager,
        private readonly string                           $databaseURL,
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
        $tenantConnection = $this->container->get('doctrine')->getConnection('tenant');

        $params = [
            'dbname' => $tenantDbConfigDTO->dbname,
            'user' => $tenantDbConfigDTO->user ?? $this->parseDatabaseUrl($this->databaseURL)['user'],
            'password' => $tenantDbConfigDTO->password ?? $this->parseDatabaseUrl($this->databaseURL)['password'],
            'host' => $tenantDbConfigDTO->host ?? $this->parseDatabaseUrl($this->databaseURL)['host'],
            'port' => $tenantDbConfigDTO->port ?? $this->parseDatabaseUrl($this->databaseURL)['port'],
        ];

        //clear the current entity manager to avoid Doctrine cache issues
        $this->tenantEntityManager->clear();

        $tenantConnection->switchConnection($params);
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
