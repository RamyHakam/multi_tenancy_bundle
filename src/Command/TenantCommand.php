<?php

namespace Hakam\MultiTenancyBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TenantCommand extends  Command
{
    use CommandTrait;
    public function __construct(
        private readonly ManagerRegistry          $registry,
        private readonly ContainerInterface       $container,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
    }
}
