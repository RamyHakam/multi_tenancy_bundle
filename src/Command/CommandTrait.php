<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Component\Console\Input\InputInterface;
use \ReflectionProperty;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
trait CommandTrait
{
    /**
     * Clears the Doctrine ORM metadata cache on the given entity manager.
     * This forces the next metadata access to re-run all loadClassMetadata listeners
     * (including TenantMetadataListener), ensuring a clean state after schema manipulation.
     */
    protected function clearTenantEmMetadata(EntityManagerInterface $em): void
    {
        $em->clear();
        $factory = $em->getMetadataFactory();
        $class = new \ReflectionClass($factory);
        do {
            foreach ($class->getProperties(ReflectionProperty::IS_PRIVATE) as $prop) {
                if ($prop->getName() === 'loadedMetadata') {
                    $prop->setAccessible(true);
                    $prop->setValue($factory, []);
                    return;
                }
            }
        } while ($class = $class->getParentClass());
    }

    protected function getDependencyFactory(InputInterface $input): DependencyFactory
    {
            $switchEvent = new SwitchDbEvent($input->getArgument('dbId')?? null);
            $this->eventDispatcher->dispatch($switchEvent);

        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager('tenant');

        $tenantMigrationConfig = new ConfigurationArray(
            $this->container->getParameter('tenant_doctrine_migration')
        );

        return DependencyFactory::fromEntityManager($tenantMigrationConfig, new ExistingEntityManager($em));
    }
}
