<?php

namespace Hakam\MultiTenancyBundle\EventListener;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Hakam\MultiTenancyBundle\Services\DbConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class DbSwitchEventListener implements EventSubscriberInterface
{
    private ContainerInterface $container;

    private DbConfigService $dbConfigService;

    public function __construct(ContainerInterface $container, DbConfigService $dbConfigService)
    {
        $this->container = $container;
        $this->dbConfigService = $dbConfigService;
    }

    public static function getSubscribedEvents()
    {
        return
        [
            SwitchDbEvent::class => 'onHakamMultiTenancyBundleEventSwitchDbEvent',
        ];
    }

    public function onHakamMultiTenancyBundleEventSwitchDbEvent(SwitchDbEvent $switchDbEvent)
    {
        $dbConfig = $this->dbConfigService->findDbConfig($switchDbEvent->getDbIndex());
        $tenantConnection = $this->container->get('doctrine')->getConnection('tenant');
        $params = [
            'dbname' => $dbConfig->getDbName(),
            'user' => $dbConfig->getDbUsername(),
            'password' => $dbConfig->getDbPassword(),
        ];
        $tenantConnection->switchConnection($params);
    }
}
