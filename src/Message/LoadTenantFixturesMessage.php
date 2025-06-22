<?php

namespace Hakam\MultiTenancyBundle\Message;

class LoadTenantFixturesMessage
{
    public function __construct(public readonly int $tenantId) {}
}
