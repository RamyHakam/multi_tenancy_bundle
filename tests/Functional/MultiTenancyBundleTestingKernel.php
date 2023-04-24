<?php

namespace Hakam\MultiTenancyBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Doctrine\Common\Annotations\AnnotationReader;
use Hakam\MultiTenancyBundle\HakamMultiTenancyBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class MultiTenancyBundleTestingKernel extends Kernel
{
    private array $multiTenancyConfig;

    public function __construct(array $multiTenancyConfig = [])
    {
        parent::__construct('test', true);
        $this->multiTenancyConfig = $multiTenancyConfig;
    }

    public function registerBundles(): array
    {
        return [
            new DoctrineBundle(),
            new DoctrineMigrationsBundle(),
            new HakamMultiTenancyBundle()
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {

            $container->register('annotation_reader', AnnotationReader::class);
            $container->loadFromExtension('hakam_multi_tenancy', $this->multiTenancyConfig);
        });
    }
}
