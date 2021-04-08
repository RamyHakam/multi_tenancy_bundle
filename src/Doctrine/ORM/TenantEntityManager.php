<?php


namespace Hakam\MultiTenancyBundle\Doctrine\ORM;


use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Entity Manager for Tenant Database
 *
 * @author  Ramy Hakam <ramyhakam1@gmial.com>
 */

class TenantEntityManager extends EntityManagerDecorator
{
    public function __construct(EntityManagerInterface $wrapped)
    {
        parent::__construct($wrapped);
    }

}