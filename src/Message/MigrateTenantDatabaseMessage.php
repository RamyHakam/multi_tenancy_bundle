<?php

namespace Hakam\MultiTenancyBundle\Message;

class MigrateTenantDatabaseMessage
{
    public function __construct(public readonly int $tenantId) {}
}
