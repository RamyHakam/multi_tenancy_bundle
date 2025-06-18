<?php

namespace Hakam\MultiTenancyBundle;

use Hakam\MultiTenancyBundle\DependencyInjection\Compiler\FixtureTaggingPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class HakamMultiTenancyBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new FixtureTaggingPass());
    }
}
