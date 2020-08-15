<?php


namespace Hakam\DoctrineDbSwitcherBundle\EventListener;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Hakam\DoctrineDbSwitcherBundle\Event\SwitchDbEvent;
use Hakam\DoctrineDbSwitcherBundle\Services\DbConfigService;
use Hakam\DoctrineDbSwitcherBundle\Services\TenantDbConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class DbSwitchEventListener implements EventSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var DbConfigService
     */
    private $dbConfigService;

    public function __construct(ContainerInterface $container,DbConfigService $dbConfigService)
    {
        $this->container = $container;
        $this->dbConfigService = $dbConfigService;
    }

    public static function getSubscribedEvents()
    {
      return
      [
          SwitchDbEvent::class => 'onHakamDoctrineDbSwitcherBundleEventSwitchDbEvent'
      ];
    }

    public function onHakamDoctrineDbSwitcherBundleEventSwitchDbEvent( SwitchDbEvent $switchDbEvent)
    {
        /** @var TenantDbConfigurationInterface $dbConfig */
        $dbConfig = $this->dbConfigService->findDbConfig($switchDbEvent->getDbIndex());

        $tenantConnection = $this->container->get('doctrine')->getConnection('tenant');
        $tenantConnection->changeParams($dbConfig->getDbName(), $dbConfig->getDbUsername(), $dbConfig->getDbPassword());
        $tenantConnection->reconnect();
    }
}