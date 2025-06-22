<?php

namespace Hakam\MultiTenancyBundle\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LoadTenantFixturesHandler
{
    public function __invoke()
    {
        // TODO: Implement __invoke() method.
    }

}